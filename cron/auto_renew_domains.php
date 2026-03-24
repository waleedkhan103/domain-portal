<?php
/**
 * Auto-Renewal Cron Job
 * Run daily: 0 2 * * * php /path/to/cron/auto_renew_domains.php
 *
 * Automatically renews domains that have auto_renew=1 and expire within 30 days
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/OnlineNICAPI.php';

$api = new OnlineNICAPI();
$today = date('Y-m-d');
$renewThreshold = date('Y-m-d', strtotime('+30 days'));

// Find domains with auto-renew enabled expiring in next 30 days
$sql = "SELECT d.*, u.email, u.first_name
        FROM domains d
        JOIN users u ON u.id = d.user_id
        WHERE d.auto_renew = 1
          AND d.status = 'active'
          AND d.expiry_date BETWEEN '$today' AND '$renewThreshold'
          AND d.expiry_date >= CURDATE()
        ORDER BY d.expiry_date ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
  error_log('[Auto-Renew Cron] Query failed: ' . mysqli_error($conn));
  exit(1);
}

$renewed = 0;
$failed = 0;

while ($domain = mysqli_fetch_assoc($result)) {
  $domainId = $domain['id'];
  $domainName = $domain['domain_name'];
  $userId = $domain['user_id'];
  $userEmail = $domain['email'];
  $userName = $domain['first_name'];

  echo "[" . date('Y-m-d H:i:s') . "] Processing: $domainName (expires: {$domain['expiry_date']})\n";

  // Default renewal period: 1 year
  $renewPeriod = 1;

  // Call OnlineNIC API to renew domain
  $apiResult = $api->renewDomain($domainName, $renewPeriod);

  if ($apiResult['success']) {
    // Calculate new expiry
    $currentExpiry = $domain['expiry_date'];
    $newExpiry = date('Y-m-d', strtotime($currentExpiry . " +{$renewPeriod} year"));

    // Update database
    $updateSql = "UPDATE domains
                  SET expiry_date = '$newExpiry',
                      expires_at = '$newExpiry',
                      updated_at = NOW()
                  WHERE id = $domainId";

    if (mysqli_query($conn, $updateSql)) {
      // Log activity
      logActivity($userId, $domainId, 'auto_renewal', "Domain auto-renewed for $renewPeriod year(s)");

      // Create renewal order for billing
      $orderNumber = generateOrderNumber();
      $price = 12.99; // Default price, should fetch from pricing table
      $total = $price * $renewPeriod;

      $orderSql = "INSERT INTO orders (user_id, order_number, total_amount, status, created_at)
                   VALUES ($userId, '$orderNumber', $total, 'completed', NOW())";
      mysqli_query($conn, $orderSql);
      $orderId = mysqli_insert_id($conn);

      // Create order item
      if ($orderId) {
        $itemSql = "INSERT INTO order_items (order_id, domain_name, operation_type, period, price, status)
                    VALUES ($orderId, '$domainName', 'renewal', $renewPeriod, $price, 'completed')";
        mysqli_query($conn, $itemSql);

        // Create transaction
        $transSql = "INSERT INTO transactions (user_id, order_id, type, amount, description)
                     VALUES ($userId, $orderId, 'auto_renewal', $total, 'Auto-renewal: $domainName')";
        mysqli_query($conn, $transSql);
      }

      // TODO: Send email notification (requires SMTP setup)
      // $subject = "Domain Auto-Renewed: $domainName";
      // $message = "Hi $userName,\n\nYour domain $domainName has been automatically renewed for $renewPeriod year(s).\nNew expiry date: $newExpiry\n\nThank you!";
      // @mail($userEmail, $subject, $message, "From: noreply@domainportal.com");

      echo "[SUCCESS] $domainName renewed until $newExpiry\n";
      $renewed++;
    } else {
      error_log("[Auto-Renew Cron] DB update failed for $domainName: " . mysqli_error($conn));
      $failed++;
    }
  } else {
    error_log("[Auto-Renew Cron] API renewal failed for $domainName: " . ($apiResult['message'] ?? 'Unknown error'));

    // TODO: Send failure notification to user
    // $subject = "Auto-Renewal Failed: $domainName";
    // $message = "Hi $userName,\n\nWe were unable to auto-renew your domain $domainName.\nPlease renew manually to avoid expiration.\n\nReason: " . ($apiResult['message'] ?? 'API error');
    // @mail($userEmail, $subject, $message, "From: noreply@domainportal.com");

    $failed++;
  }

  // Small delay to avoid API rate limits
  usleep(500000); // 0.5 seconds
}

echo "\n=== Auto-Renewal Summary ===\n";
echo "Successfully renewed: $renewed\n";
echo "Failed: $failed\n";
echo "============================\n";

if ($renewed > 0 || $failed > 0) {
  error_log("[Auto-Renew Cron] Completed: $renewed renewed, $failed failed");
}

exit(0);

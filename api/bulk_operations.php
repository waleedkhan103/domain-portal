<?php
/**
 * api/bulk_operations.php
 * Handles bulk operations on multiple domains
 */

session_start();

// Prevent HTML output
while (ob_get_level() > 0) {
  ob_end_clean();
}

ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Error handlers
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  jsonResponse(false, "Error [$errno]: $errstr");
});

register_shutdown_function(function () {
  $error = error_get_last();
  if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE])) {
    jsonResponse(false, "Fatal: " . $error['message']);
  }
});

try {
  require_once __DIR__ . '/../config/database.php';
  require_once __DIR__ . '/../includes/functions.php';
} catch (Exception $e) {
  jsonResponse(false, "Include error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'POST method required');
}

if (!isLoggedIn()) {
  jsonResponse(false, 'Authentication required');
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$domainIds = json_decode($_POST['domain_ids'] ?? '[]', true);

if (!is_array($domainIds) || empty($domainIds)) {
  jsonResponse(false, 'No domains selected');
}

// Sanitize domain IDs
$domainIds = array_map('intval', $domainIds);
$domainIds = array_filter($domainIds, function($id) { return $id > 0; });

if (empty($domainIds)) {
  jsonResponse(false, 'Invalid domain IDs');
}

// Build WHERE clause for SQL
$domainIdsStr = implode(',', $domainIds);

// Verify all domains belong to the user
$verifySql = "SELECT COUNT(*) as count FROM domains WHERE id IN ($domainIdsStr) AND user_id = $userId";
$verifyResult = mysqli_query($conn, $verifySql);
$verifyRow = mysqli_fetch_assoc($verifyResult);

if ($verifyRow['count'] != count($domainIds)) {
  jsonResponse(false, 'One or more domains do not belong to you or do not exist');
}

switch ($action) {

  case 'lock':
    $sql = "UPDATE domains SET is_locked = 1 WHERE id IN ($domainIdsStr) AND user_id = $userId";

    if (mysqli_query($conn, $sql)) {
      $affected = mysqli_affected_rows($conn);
      logActivity($userId, null, 'bulk_lock', "Locked $affected domains");
      jsonResponse(true, "Successfully locked $affected domain(s)", ['affected' => $affected]);
    } else {
      jsonResponse(false, 'Failed to lock domains: ' . mysqli_error($conn));
    }
    break;

  case 'unlock':
    $sql = "UPDATE domains SET is_locked = 0 WHERE id IN ($domainIdsStr) AND user_id = $userId";

    if (mysqli_query($conn, $sql)) {
      $affected = mysqli_affected_rows($conn);
      logActivity($userId, null, 'bulk_unlock', "Unlocked $affected domains");
      jsonResponse(true, "Successfully unlocked $affected domain(s)", ['affected' => $affected]);
    } else {
      jsonResponse(false, 'Failed to unlock domains: ' . mysqli_error($conn));
    }
    break;

  case 'enable_privacy':
    // Get domains and calculate TLD-specific privacy fees
    $getDomainsSql = "SELECT id, domain_name FROM domains WHERE id IN ($domainIdsStr) AND user_id = $userId";
    $domainsResult = mysqli_query($conn, $getDomainsSql);

    $updated = 0;
    while ($domain = mysqli_fetch_assoc($domainsResult)) {
      $pricing = getTldPricing($domain['domain_name']);
      $privacyFee = $pricing['privacy_price'];

      $updateSql = "UPDATE domains SET privacy_enabled = 1, privacy_fee = $privacyFee WHERE id = {$domain['id']}";
      if (mysqli_query($conn, $updateSql)) {
        $updated++;
      }
    }

    logActivity($userId, null, 'bulk_privacy_enable', "Enabled privacy for $updated domains");
    jsonResponse(true, "Successfully enabled privacy for $updated domain(s)", ['affected' => $updated]);
    break;

  case 'disable_privacy':
    $sql = "UPDATE domains SET privacy_enabled = 0, privacy_fee = 0.00 WHERE id IN ($domainIdsStr) AND user_id = $userId";

    if (mysqli_query($conn, $sql)) {
      $affected = mysqli_affected_rows($conn);
      logActivity($userId, null, 'bulk_privacy_disable', "Disabled privacy for $affected domains");
      jsonResponse(true, "Successfully disabled privacy for $affected domain(s)", ['affected' => $affected]);
    } else {
      jsonResponse(false, 'Failed to disable privacy: ' . mysqli_error($conn));
    }
    break;

  case 'renew':
    // Add domains to cart for renewal
    $getDomainsSql = "SELECT id, domain_name, expiry_date FROM domains WHERE id IN ($domainIdsStr) AND user_id = $userId";
    $domainsResult = mysqli_query($conn, $getDomainsSql);

    $addedToCart = 0;
    while ($domain = mysqli_fetch_assoc($domainsResult)) {
      $domainName = $domain['domain_name'];
      $domainNameEsc = mysqli_real_escape_string($conn, $domainName);

      // Check if already in cart
      $checkSql = "SELECT id FROM cart WHERE user_id = $userId AND domain_name = '$domainNameEsc' AND operation_type = 'renew'";
      $checkResult = mysqli_query($conn, $checkSql);

      if (mysqli_num_rows($checkResult) > 0) {
        continue; // Skip if already in cart
      }

      // Get TLD-specific renewal pricing
      $pricing = getTldPricing($domainName);
      $renewalPrice = $pricing['renewal_price'];

      // Add to cart
      $insertSql = "INSERT INTO cart (user_id, domain_name, operation_type, period, price)
                    VALUES ($userId, '$domainNameEsc', 'renew', 1, $renewalPrice)";

      if (mysqli_query($conn, $insertSql)) {
        $addedToCart++;
      }
    }

    logActivity($userId, null, 'bulk_renew_cart', "Added $addedToCart domains to cart for renewal");
    jsonResponse(true, "Added $addedToCart domain(s) to cart for renewal", [
      'affected' => $addedToCart,
      'redirect' => '/pages/cart.php'
    ]);
    break;

  case 'delete':
    // Soft delete - mark as deleted/inactive rather than removing from database
    $sql = "UPDATE domains SET status = 'deleted' WHERE id IN ($domainIdsStr) AND user_id = $userId";

    if (mysqli_query($conn, $sql)) {
      $affected = mysqli_affected_rows($conn);
      logActivity($userId, null, 'bulk_delete', "Deleted $affected domains");
      jsonResponse(true, "Successfully deleted $affected domain(s)", ['affected' => $affected]);
    } else {
      jsonResponse(false, 'Failed to delete domains: ' . mysqli_error($conn));
    }
    break;

  case 'auto_renew_on':
    $sql = "UPDATE domains SET auto_renew = 1 WHERE id IN ($domainIdsStr) AND user_id = $userId";

    if (mysqli_query($conn, $sql)) {
      $affected = mysqli_affected_rows($conn);
      logActivity($userId, null, 'bulk_auto_renew_on', "Enabled auto-renew for $affected domains");
      jsonResponse(true, "Auto-renew enabled for $affected domain(s)", ['affected' => $affected]);
    } else {
      jsonResponse(false, 'Failed to enable auto-renew: ' . mysqli_error($conn));
    }
    break;

  case 'auto_renew_off':
    $sql = "UPDATE domains SET auto_renew = 0 WHERE id IN ($domainIdsStr) AND user_id = $userId";

    if (mysqli_query($conn, $sql)) {
      $affected = mysqli_affected_rows($conn);
      logActivity($userId, null, 'bulk_auto_renew_off', "Disabled auto-renew for $affected domains");
      jsonResponse(true, "Auto-renew disabled for $affected domain(s)", ['affected' => $affected]);
    } else {
      jsonResponse(false, 'Failed to disable auto-renew: ' . mysqli_error($conn));
    }
    break;

  default:
    jsonResponse(false, 'Invalid action');
}

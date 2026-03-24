<?php
/**
 * api/transfer_operations.php
 * Handles domain transfer operations
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
  require_once __DIR__ . '/OnlineNICAPI.php';
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

switch ($action) {

  case 'check_transfer':
    $domain = clean($_POST['domain'] ?? '');

    if (empty($domain)) {
      jsonResponse(false, 'Domain name is required');
    }

    if (!isValidDomain($domain)) {
      jsonResponse(false, 'Invalid domain format');
    }

    // Get TLD-specific transfer pricing
    $pricing = getTldPricing($domain);
    $transferPrice = $pricing['transfer_price'];

    // If transfer price is 0, use registration price (common practice)
    if ($transferPrice == 0) {
      $transferPrice = $pricing['registration_price'];
    }

    jsonResponse(true, 'Transfer pricing retrieved', [
      'domain' => $domain,
      'transfer_price' => $transferPrice,
      'includes_renewal' => true,
      'renewal_period' => 1
    ]);
    break;

  case 'add_transfer_to_cart':
    $domain = clean($_POST['domain'] ?? '');
    $authCode = clean($_POST['auth_code'] ?? '');

    if (empty($domain)) {
      jsonResponse(false, 'Domain name is required');
    }

    if (empty($authCode)) {
      jsonResponse(false, 'Authorization code is required');
    }

    if (!isValidDomain($domain)) {
      jsonResponse(false, 'Invalid domain format');
    }

    // Check if domain is already in cart
    $domainEsc = mysqli_real_escape_string($conn, $domain);
    $checkSql = "SELECT id FROM cart WHERE user_id = $userId AND domain_name = '$domainEsc'";
    $checkResult = mysqli_query($conn, $checkSql);

    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
      jsonResponse(false, 'Domain already in cart');
    }

    // Check if domain is already owned by this user
    $ownedSql = "SELECT id FROM domains WHERE user_id = $userId AND domain_name = '$domainEsc'";
    $ownedResult = mysqli_query($conn, $ownedSql);

    if ($ownedResult && mysqli_num_rows($ownedResult) > 0) {
      jsonResponse(false, 'You already own this domain');
    }

    // Get TLD-specific transfer pricing
    $pricing = getTldPricing($domain);
    $transferPrice = $pricing['transfer_price'];

    // If transfer price is 0, use registration price
    if ($transferPrice == 0) {
      $transferPrice = $pricing['registration_price'];
    }

    // Add to cart
    $authCodeEsc = mysqli_real_escape_string($conn, $authCode);
    $operationType = 'transfer';
    $period = 1; // Transfers include 1 year renewal

    $insertSql = "INSERT INTO cart (user_id, domain_name, operation_type, period, price, auth_code)
                  VALUES ($userId, '$domainEsc', '$operationType', $period, $transferPrice, '$authCodeEsc')";

    if (mysqli_query($conn, $insertSql)) {
      logActivity($userId, null, 'transfer_cart_add', "Added transfer for $domain to cart");
      jsonResponse(true, 'Transfer added to cart successfully', [
        'domain' => $domain,
        'price' => $transferPrice,
        'cart_count' => getCartCount($userId)
      ]);
    } else {
      jsonResponse(false, 'Failed to add transfer to cart: ' . mysqli_error($conn));
    }
    break;

  case 'check_transfer_status':
    $domainId = (int) ($_POST['domain_id'] ?? 0);

    if ($domainId <= 0) {
      jsonResponse(false, 'Invalid domain ID');
    }

    // Verify domain belongs to user
    $sql = "SELECT d.*, oi.status as order_status, oi.id as order_item_id
            FROM domains d
            LEFT JOIN order_items oi ON oi.domain_name = d.domain_name AND oi.operation_type = 'transfer'
            WHERE d.id = $domainId AND d.user_id = $userId
            LIMIT 1";

    $result = mysqli_query($conn, $sql);

    if (!$result || mysqli_num_rows($result) === 0) {
      jsonResponse(false, 'Domain not found or access denied');
    }

    $domain = mysqli_fetch_assoc($result);

    // Check if there's a pending transfer
    $transferSql = "SELECT * FROM domain_transfers
                    WHERE domain_id = $domainId
                    ORDER BY created_at DESC LIMIT 1";

    $transferResult = mysqli_query($conn, $transferSql);
    $transfer = $transferResult ? mysqli_fetch_assoc($transferResult) : null;

    jsonResponse(true, 'Transfer status retrieved', [
      'domain' => $domain['domain_name'],
      'transfer_status' => $transfer ? $transfer['status'] : 'none',
      'transfer_initiated' => $transfer ? $transfer['created_at'] : null,
      'estimated_completion' => $transfer ? date('Y-m-d', strtotime($transfer['created_at'] . ' +7 days')) : null
    ]);
    break;

  default:
    jsonResponse(false, 'Invalid action');
}

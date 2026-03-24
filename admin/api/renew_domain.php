<?php
if (session_status() === PHP_SESSION_NONE) session_start();
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../api/OnlineNICAPI.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
  jsonResponse(false, 'Invalid request method');
$posted = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($posted)) {
  http_response_code(403);
  jsonResponse(false, 'Invalid CSRF token');
}

global $conn;
$domainId = (int) ($_POST['domain_id'] ?? 0);
$period = (int) ($_POST['period'] ?? 1);
if (!$domainId)
  jsonResponse(false, 'Missing domain id');
if ($period <= 0)
  $period = 1;

// fetch domain
$domain = null;
if ($stmt = $conn->prepare("SELECT id, domain_name, user_id, COALESCE(expiry_date, expires_at) AS expiry_date FROM domains WHERE id = ? LIMIT 1")) {
  $stmt->bind_param('i', $domainId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $res->num_rows > 0)
    $domain = $res->fetch_assoc();
  $stmt->close();
}
if (!$domain)
  jsonResponse(false, 'Domain not found');

$api = new OnlineNICAPI();
$apiResult = $api->renewDomain($domain['domain_name'], $period);
if ($apiResult['success']) {
  $newExpiryDate = $apiResult['data']['expdate'] ?? date('Y-m-d', strtotime("+{$period} years"));
  // update both expiry_date and expires_at if present
  if ($stmt = $conn->prepare("UPDATE domains SET expiry_date = ?, expires_at = ? WHERE id = ?")) {
    $stmt->bind_param('ssi', $newExpiryDate, $newExpiryDate, $domainId);
    $stmt->execute();
    $stmt->close();
  } else {
    mysqli_query($conn, "UPDATE domains SET expiry_date='" . mysqli_real_escape_string($conn, $newExpiryDate) . "', expires_at='" . mysqli_real_escape_string($conn, $newExpiryDate) . "' WHERE id = $domainId");
  }

  // create a minimal renewal order
  $orderNumber = generateOrderNumber();
  $amount = $apiResult['data']['amount'] ?? 0;
  $adminId = $_SESSION['admin_id'] ?? 0;
  if ($stmt2 = $conn->prepare("INSERT INTO orders (order_number, user_id, total_amount, status, created_at) VALUES (?, ?, ?, ?, NOW())")) {
    $userId = (int) ($domain['user_id'] ?? 0);
    $status = 'completed';
    $stmt2->bind_param('sids', $orderNumber, $userId, $amount, $status);
    $stmt2->execute();
    $orderId = $stmt2->insert_id;
    $stmt2->close();
  } else {
    $userId = (int) ($domain['user_id'] ?? 0);
    mysqli_query($conn, "INSERT INTO orders (order_number, user_id, total_amount, status, created_at) VALUES ('" . mysqli_real_escape_string($conn, $orderNumber) . "', $userId, $amount, 'completed', NOW())");
    $orderId = mysqli_insert_id($conn);
  }

  // log renewal
  logActivity($adminId, $domainId, 'domain_renewed', "Domain renewed for {$period} year(s)");

  jsonResponse(true, 'Domain renewed successfully', ['new_expiry_date' => $newExpiryDate, 'order_id' => $orderId, 'csrf_token' => generateCSRFToken()]);
}

jsonResponse(false, $apiResult['message'] ?? 'Failed to renew domain');

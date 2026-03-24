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
if (!$domainId)
  jsonResponse(false, 'Missing domain id');

// fetch domain
$domain = null;
if ($stmt = $conn->prepare("SELECT id, domain_name, is_locked FROM domains WHERE id = ? LIMIT 1")) {
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
$current = (int) ($domain['is_locked'] ?? 0);
$newState = $current ? 0 : 1;
$apiResult = $api->updateDomainStatus($domain['domain_name'], $newState);
if ($apiResult['success']) {
  if ($stmt = $conn->prepare("UPDATE domains SET is_locked = ? WHERE id = ?")) {
    $stmt->bind_param('ii', $newState, $domainId);
    $stmt->execute();
    $stmt->close();
  } else {
    mysqli_query($conn, "UPDATE domains SET is_locked = " . ($newState ? 1 : 0) . " WHERE id = $domainId");
  }
  $adminId = $_SESSION['admin_id'] ?? 0;
  logActivity($adminId, $domainId, $newState ? 'domain_locked' : 'domain_unlocked', $newState ? 'Domain locked by admin' : 'Domain unlocked by admin');
  jsonResponse(true, 'Domain lock status updated', ['is_locked' => $newState, 'csrf_token' => generateCSRFToken()]);
}

jsonResponse(false, $apiResult['message'] ?? 'Failed to update lock status');

<?php
session_start();
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
if ($stmt = $conn->prepare("SELECT id, domain_name FROM domains WHERE id = ? LIMIT 1")) {
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
$apiResult = $api->getAuthCode($domain['domain_name']);
if ($apiResult['success']) {
  $authCode = $apiResult['data']['Transfercode'] ?? $apiResult['data']['auth_code'] ?? '';
  if ($stmt = $conn->prepare("UPDATE domains SET auth_code = ? WHERE id = ?")) {
    $stmt->bind_param('si', $authCode, $domainId);
    $stmt->execute();
    $stmt->close();
  } else {
    mysqli_query($conn, "UPDATE domains SET auth_code='" . mysqli_real_escape_string($conn, $authCode) . "' WHERE id = $domainId");
  }
  $adminId = $_SESSION['admin_id'] ?? 0;
  logActivity($adminId, $domainId, 'auth_code_retrieved', 'Admin retrieved auth code');
  jsonResponse(true, 'Auth code retrieved', ['auth_code' => $authCode]);
}

jsonResponse(false, $apiResult['message'] ?? 'Failed to retrieve auth code');

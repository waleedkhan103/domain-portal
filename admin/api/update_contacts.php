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

// collect contacts fields
$contacts = [
  'registrant' => [
    'name' => $_POST['reg_name'] ?? '',
    'email' => $_POST['reg_email'] ?? '',
    'phone' => $_POST['reg_phone'] ?? '',
    'address' => $_POST['reg_address'] ?? '',
  ],
  'admin' => [
    'name' => $_POST['admin_name'] ?? '',
    'email' => $_POST['admin_email'] ?? '',
    'phone' => $_POST['admin_phone'] ?? '',
    'address' => $_POST['admin_address'] ?? '',
  ],
  'tech' => [
    'name' => $_POST['tech_name'] ?? '',
    'email' => $_POST['tech_email'] ?? '',
    'phone' => $_POST['tech_phone'] ?? '',
    'address' => $_POST['tech_address'] ?? '',
  ],
  'billing' => [
    'name' => $_POST['bill_name'] ?? '',
    'email' => $_POST['bill_email'] ?? '',
    'phone' => $_POST['bill_phone'] ?? '',
    'address' => $_POST['bill_address'] ?? '',
  ],
  'privacy' => isset($_POST['privacy']) && $_POST['privacy'] === '1'
];

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
$apiResult = $api->updateContacts($domain['domain_name'], $contacts);
if ($apiResult['success']) {
  // optionally persist to local DB if schema exists; here we only log
  $adminId = $_SESSION['admin_id'] ?? 0;
  logActivity($adminId, $domainId, 'contacts_updated', 'Admin updated contacts');
  jsonResponse(true, 'Contacts updated successfully');
}

jsonResponse(false, $apiResult['message'] ?? 'Failed to update contacts');

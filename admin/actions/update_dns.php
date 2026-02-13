<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../api/OnlineNICAPI.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
  jsonResponse(false, 'Invalid request method');

$postedToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($postedToken)) {
  http_response_code(403);
  jsonResponse(false, 'Invalid CSRF token');
}

global $conn;

$domainId = (int) ($_POST['domain_id'] ?? 0);
$dns1 = clean($_POST['dns1'] ?? '');
$dns2 = clean($_POST['dns2'] ?? '');
$dns3 = clean($_POST['dns3'] ?? '');
$dns4 = clean($_POST['dns4'] ?? '');

if (!$domainId)
  jsonResponse(false, 'Missing domain id');

// Prepared select for domain
$domain = null;
if ($stmt = $conn->prepare("SELECT * FROM domains WHERE id = ? LIMIT 1")) {
  $stmt->bind_param('i', $domainId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $res->num_rows > 0) {
    $domain = $res->fetch_assoc();
  }
  $stmt->close();
} else {
  // fallback
  $sql = "SELECT * FROM domains WHERE id = " . $domainId . " LIMIT 1";
  $res = mysqli_query($conn, $sql);
  if ($res && mysqli_num_rows($res) > 0) {
    $domain = mysqli_fetch_assoc($res);
  }
}

if (!$domain)
  jsonResponse(false, 'Domain not found');

$api = new OnlineNICAPI();
$dnsServers = [];
if ($dns1)
  $dnsServers['dns1'] = $dns1;
if ($dns2)
  $dnsServers['dns2'] = $dns2;
if ($dns3)
  $dnsServers['dns3'] = $dns3;
if ($dns4)
  $dnsServers['dns4'] = $dns4;

$apiResult = $api->updateDomainDNS($domain['domain_name'], $dnsServers);
if ($apiResult['success']) {
  // Prepared update for domains
  if ($stmt = $conn->prepare("UPDATE domains SET dns1 = ?, dns2 = ?, dns3 = ?, dns4 = ? WHERE id = ?")) {
    $stmt->bind_param('ssssi', $dns1, $dns2, $dns3, $dns4, $domainId);
    $stmt->execute();
    $stmt->close();
  } else {
    $u1 = mysqli_real_escape_string($conn, $dns1);
    $u2 = mysqli_real_escape_string($conn, $dns2);
    $u3 = mysqli_real_escape_string($conn, $dns3);
    $u4 = mysqli_real_escape_string($conn, $dns4);
    $upd = "UPDATE domains SET dns1='$u1', dns2='$u2', dns3='$u3', dns4='$u4' WHERE id = " . $domainId;
    mysqli_query($conn, $upd);
  }
  $adminId = $_SESSION['admin_id'] ?? 0;
  logActivity($adminId, $domainId, 'admin_dns_update', 'Admin updated DNS servers');
  jsonResponse(true, 'DNS servers updated successfully');
}

jsonResponse(false, $apiResult['message'] ?? 'Failed to update DNS');

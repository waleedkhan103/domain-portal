<?php
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'Invalid request method');
}

if (!isLoggedIn())
  jsonResponse(false, 'Not authenticated');

$user = getCurrentUser();
$domainId = isset($_POST['domain_id']) ? (int) $_POST['domain_id'] : 0;
$period = isset($_POST['period']) ? (int) $_POST['period'] : 1;
if (!$domainId)
  jsonResponse(false, 'Missing domain id');
if ($period <= 0)
  $period = 1;

// Verify domain belongs to user
$stmt = mysqli_prepare($conn, "SELECT id, domain_name, expiry_date FROM domains WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $domainId, $user['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$domain = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
if (!$domain)
  jsonResponse(false, 'Domain not found');

$currentExpiry = $domain['expiry_date'] ?: date('Y-m-d');
$newExpiry = date('Y-m-d', strtotime($currentExpiry . " +{$period} years"));

$stmt = mysqli_prepare($conn, "UPDATE domains SET expiry_date = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'si', $newExpiry, $domainId);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok)
  jsonResponse(false, 'Failed to renew');

// Log
logActivity($user['id'], $domainId, 'domain_renewed', "Renewed {$domain['domain_name']} for {$period} year(s)");

jsonResponse(true, 'Domain renewed', ['expiry_date' => $newExpiry]);

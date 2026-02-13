<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/OnlineNICAPI.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
  jsonResponse(false, 'Invalid request method');
if (!isLoggedIn())
  jsonResponse(false, 'Not authenticated');

$user = getCurrentUser();
$userId = $user['id'];

$domainId = (int) ($_POST['domain_id'] ?? 0);
$dns1 = clean($_POST['dns1'] ?? '');
$dns2 = clean($_POST['dns2'] ?? '');
$dns3 = clean($_POST['dns3'] ?? '');
$dns4 = clean($_POST['dns4'] ?? '');

if (!$domainId)
  jsonResponse(false, 'Missing domain id');

$sql = "SELECT * FROM domains WHERE id = $domainId AND user_id = $userId";
$res = mysqli_query($conn, $sql);
if (mysqli_num_rows($res) === 0)
  jsonResponse(false, 'Domain not found');
$domain = mysqli_fetch_assoc($res);

$api = new OnlineNICAPI();
$dnsServers = ['dns1' => $dns1, 'dns2' => $dns2];
if ($dns3)
  $dnsServers['dns3'] = $dns3;
if ($dns4)
  $dnsServers['dns4'] = $dns4;

$apiResult = $api->updateDomainDNS($domain['domain_name'], $dnsServers);
if ($apiResult['success']) {
  $sql = "UPDATE domains SET dns1='" . mysqli_real_escape_string($conn, $dns1) . "', dns2='" . mysqli_real_escape_string($conn, $dns2) . "', dns3='" . mysqli_real_escape_string($conn, $dns3) . "', dns4='" . mysqli_real_escape_string($conn, $dns4) . "' WHERE id = $domainId";
  mysqli_query($conn, $sql);
  logActivity($userId, $domainId, 'dns_update', 'Updated DNS servers');
  jsonResponse(true, 'DNS servers updated successfully');
}

jsonResponse(false, $apiResult['message'] ?? 'Failed to update DNS');

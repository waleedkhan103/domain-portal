<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/OnlineNICAPI.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
  jsonResponse(false, 'Invalid request method');
if (!isLoggedIn())
  jsonResponse(false, 'Not authenticated');

$user = getCurrentUser();
$userId = $user['id'];

$domainId = (int) ($_POST['domain_id'] ?? 0);
if (!$domainId)
  jsonResponse(false, 'Missing domain id');

$sql = "SELECT * FROM domains WHERE id = $domainId AND user_id = $userId";
$res = mysqli_query($conn, $sql);
if (mysqli_num_rows($res) === 0)
  jsonResponse(false, 'Domain not found');
$domain = mysqli_fetch_assoc($res);

$api = new OnlineNICAPI();
$newLockState = !$domain['is_locked'];
$apiResult = $api->updateDomainStatus($domain['domain_name'], $newLockState);

if ($apiResult['success']) {
  $sql = "UPDATE domains SET is_locked = " . ($newLockState ? 1 : 0) . " WHERE id = $domainId";
  mysqli_query($conn, $sql);
  $action = $newLockState ? 'locked' : 'unlocked';
  logActivity($userId, $domainId, 'domain_lock_toggle', "Domain $action");
  jsonResponse(true, 'Domain lock status updated', ['is_locked' => $newLockState]);
}

jsonResponse(false, $apiResult['message'] ?? 'Failed to update lock');

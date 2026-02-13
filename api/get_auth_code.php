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
$apiResult = $api->getAuthCode($domain['domain_name']);

if ($apiResult['success']) {
  $authCode = $apiResult['data']['Transfercode'] ?? '';
  $sql = "UPDATE domains SET auth_code = '" . mysqli_real_escape_string($conn, $authCode) . "' WHERE id = $domainId";
  mysqli_query($conn, $sql);
  logActivity($userId, $domainId, 'auth_code_retrieved', 'Retrieved auth code');
  jsonResponse(true, 'Auth code retrieved', ['auth_code' => $authCode]);
}

jsonResponse(false, $apiResult['message'] ?? 'Failed to retrieve auth code');

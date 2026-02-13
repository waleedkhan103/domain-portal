<?php
session_start();

// Prevent HTML output in API
while (ob_get_level() > 0) {
  ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Error handler to prevent HTML output
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  jsonResponse(false, 'Internal server error');
});

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/OnlineNICAPI.php';

if (!isLoggedIn()) {
  jsonResponse(false, 'Please login first');
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];
$api = new OnlineNICAPI();

switch ($action) {
  case 'update_dns':
    $domainId = (int) ($_POST['domain_id'] ?? 0);
    $dns1 = clean($_POST['dns1'] ?? '');
    $dns2 = clean($_POST['dns2'] ?? '');
    $dns3 = clean($_POST['dns3'] ?? '');
    $dns4 = clean($_POST['dns4'] ?? '');

    // Get domain
    $sql = "SELECT * FROM domains WHERE id = $domainId AND user_id = $userId";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) === 0) {
      jsonResponse(false, 'Domain not found');
    }

    $domain = mysqli_fetch_assoc($result);

    // Update via API
    $dnsServers = ['dns1' => $dns1, 'dns2' => $dns2];
    if ($dns3)
      $dnsServers['dns3'] = $dns3;
    if ($dns4)
      $dnsServers['dns4'] = $dns4;

    $apiResult = $api->updateDomainDNS($domain['domain_name'], $dnsServers);

    if ($apiResult['success']) {
      // Update database
      $sql = "UPDATE domains SET dns1='$dns1', dns2='$dns2', dns3='$dns3', dns4='$dns4' 
                    WHERE id = $domainId";
      mysqli_query($conn, $sql);

      logActivity($userId, $domainId, 'dns_update', 'Updated DNS servers');
      jsonResponse(true, 'DNS servers updated successfully');
    } else {
      jsonResponse(false, $apiResult['message']);
    }
    break;

  case 'toggle_lock':
    $domainId = (int) ($_POST['domain_id'] ?? 0);

    $sql = "SELECT * FROM domains WHERE id = $domainId AND user_id = $userId";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) === 0) {
      jsonResponse(false, 'Domain not found');
    }

    $domain = mysqli_fetch_assoc($result);
    $newLockState = !$domain['is_locked'];

    $apiResult = $api->updateDomainStatus($domain['domain_name'], $newLockState);

    if ($apiResult['success']) {
      $sql = "UPDATE domains SET is_locked = " . ($newLockState ? 1 : 0) . " WHERE id = $domainId";
      mysqli_query($conn, $sql);

      $action = $newLockState ? 'locked' : 'unlocked';
      logActivity($userId, $domainId, 'domain_lock_toggle', "Domain $action");

      jsonResponse(true, 'Domain lock status updated', ['is_locked' => $newLockState]);
    } else {
      jsonResponse(false, $apiResult['message']);
    }
    break;

  case 'get_auth_code':
    $domainId = (int) ($_POST['domain_id'] ?? 0);

    $sql = "SELECT * FROM domains WHERE id = $domainId AND user_id = $userId";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) === 0) {
      jsonResponse(false, 'Domain not found');
    }

    $domain = mysqli_fetch_assoc($result);

    $apiResult = $api->getAuthCode($domain['domain_name']);

    if ($apiResult['success']) {
      $authCode = $apiResult['data']['Transfercode'] ?? '';

      // Update in database
      $sql = "UPDATE domains SET auth_code = '$authCode' WHERE id = $domainId";
      mysqli_query($conn, $sql);

      logActivity($userId, $domainId, 'auth_code_retrieved', 'Retrieved auth code');

      jsonResponse(true, 'Auth code retrieved', ['auth_code' => $authCode]);
    } else {
      jsonResponse(false, $apiResult['message']);
    }
    break;

  case 'renew_domain':
    $domainId = (int) ($_POST['domain_id'] ?? 0);
    $period = (int) ($_POST['period'] ?? 1);

    $sql = "SELECT * FROM domains WHERE id = $domainId AND user_id = $userId";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) === 0) {
      jsonResponse(false, 'Domain not found');
    }

    $domain = mysqli_fetch_assoc($result);

    $apiResult = $api->renewDomain($domain['domain_name'], $period);

    if ($apiResult['success']) {
      $newExpiryDate = $apiResult['data']['expdate'] ?? date('Y-m-d', strtotime("+$period years"));

      // Update domain
      $sql = "UPDATE domains SET expiry_date = '$newExpiryDate' WHERE id = $domainId";
      mysqli_query($conn, $sql);

      // Log renewal
      $sql = "INSERT INTO renewal_history (domain_id, old_expiry_date, new_expiry_date, period, amount) 
                    VALUES ($domainId, '{$domain['expiry_date']}', '$newExpiryDate', $period, 0)";
      mysqli_query($conn, $sql);

      logActivity($userId, $domainId, 'domain_renewed', "Domain renewed for $period year(s)");

      jsonResponse(true, 'Domain renewed successfully', ['new_expiry_date' => $newExpiryDate]);
    } else {
      jsonResponse(false, $apiResult['message']);
    }
    break;

  case 'get_domain_info':
    $domainId = (int) ($_POST['domain_id'] ?? 0);

    $sql = "SELECT * FROM domains WHERE id = $domainId AND user_id = $userId";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) === 0) {
      jsonResponse(false, 'Domain not found');
    }

    $domain = mysqli_fetch_assoc($result);

    $apiResult = $api->getDomainInfo($domain['domain_name']);

    if ($apiResult['success']) {
      jsonResponse(true, 'Domain info retrieved', $apiResult['data']);
    } else {
      jsonResponse(false, $apiResult['message']);
    }
    break;

  default:
    jsonResponse(false, 'Invalid action');
}
?>
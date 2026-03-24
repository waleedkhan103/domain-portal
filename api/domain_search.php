<?php
/**
 * api/domain_search.php
 * JSON-only API endpoint
 */

session_start();

/* ───────── Prevent any HTML leakage ───────── */
while (ob_get_level() > 0) {
  ob_end_clean();
}

ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Set custom error handler to prevent HTML output
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'success' => false,
    'message' => "Error [$errno]: $errstr in " . basename($errfile) . ":$errline",
    'data' => []
  ]);
  exit;
});

// Catch fatal errors
register_shutdown_function(function () {
  $error = error_get_last();
  if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE])) {
    while (ob_get_level() > 0) {
      ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'success' => false,
      'message' => "Fatal: " . $error['message'],
      'data' => []
    ]);
  }
});

/* ───────── Includes ───────── */
try {
  require_once __DIR__ . '/../config/database.php';
  require_once __DIR__ . '/../includes/functions.php';
  require_once __DIR__ . '/OnlineNICAPI.php';
} catch (Exception $e) {
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'success' => false,
    'message' => 'Include error: ' . $e->getMessage(),
    'data' => []
  ]);
  exit;
}

/* ───────── Only POST allowed ───────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'POST method required');
}

/* ───────── Validate action ───────── */
$action = $_POST['action'] ?? '';

if (!in_array($action, ['check', 'check_multiple'])) {
  jsonResponse(false, 'Invalid action');
}

/* ───────── Create API instance ───────── */
$api = new OnlineNICAPI();

/* ───────── SINGLE DOMAIN CHECK ───────── */
if ($action === 'check') {

  $domain = clean($_POST['domain'] ?? '');

  if (empty($domain)) {
    jsonResponse(false, 'Domain is required');
  }

  if (!isValidDomain($domain)) {
    jsonResponse(false, 'Invalid domain format');
  }

  $result = $api->getCachedDomainCheck($domain, '1');

  if (!$result['success']) {
    jsonResponse(false, $result['message'] ?? 'API error');
  }

  $data = $result['data'] ?? [];

  // Get TLD-specific pricing from database
  $pricing = getTldPricing($domain);

  // Use API prices if available, otherwise use our TLD pricing
  $apiPrices = $data['Prices'] ?? [];
  $finalPrices = [];

  if (!empty($apiPrices)) {
    $finalPrices = $apiPrices;
  } else {
    $finalPrices[] = [
      'price' => number_format($pricing['registration_price'], 2),
      'type' => 'registration',
      'period' => 1
    ];
  }

  jsonResponse(true, 'Success', [
    'domain' => $data['domain'] ?? $domain,
    'available' => ($data['avail'] ?? 0) == 1,
    'premium' => ($data['Premium'] ?? 'false') === 'true',
    'prices' => $finalPrices,
    'registration_price' => $pricing['registration_price'],
    'renewal_price' => $pricing['renewal_price'],
    'privacy_price' => $pricing['privacy_price']
  ]);
}

/* ───────── MULTIPLE DOMAIN CHECK ───────── */
if ($action === 'check_multiple') {

  $domains = json_decode($_POST['domains'] ?? '[]', true);

  if (!is_array($domains) || empty($domains)) {
    jsonResponse(false, 'Invalid domains list');
  }

  $results = [];

  foreach ($domains as $domain) {
    $domain = clean($domain);

    if (!isValidDomain($domain)) {
      continue;
    }

    $result = $api->getCachedDomainCheck($domain, '1');

    if ($result['success']) {
      $data = $result['data'] ?? [];

      // Get TLD-specific pricing
      $pricing = getTldPricing($domain);
      $price = $data['Prices'][0]['price'] ?? $pricing['registration_price'];

      $results[] = [
        'domain' => $domain,
        'available' => ($data['avail'] ?? 0) == 1,
        'price' => number_format($price, 2),
        'renewal_price' => number_format($pricing['renewal_price'], 2)
      ];
    }
  }

  jsonResponse(true, 'Success', $results);
}

<?php
/**
 * api/mock_domains.php
 * Mock API for domain operations - Perfect for development
 * This endpoint returns realistic test data without external API dependency
 */

session_start();

// Prevent HTML output
while (ob_get_level() > 0) {
  ob_end_clean();
}

ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Load functions first so jsonResponse is available
require_once __DIR__ . '/../includes/functions.php';

// Error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  jsonResponse(false, "Error [$errno]: $errstr in " . basename($errfile) . ":$errline", []);
});

// Mock domain database
$mock_domains = [
  'google.com' => ['available' => false, 'premium' => true, 'price' => 89.99],
  'facebook.com' => ['available' => false, 'premium' => true, 'price' => 79.99],
  'example.com' => ['available' => false, 'premium' => false, 'price' => 12.99],
  'test.com' => ['available' => true, 'premium' => false, 'price' => 12.99],
  'myproject.com' => ['available' => true, 'premium' => false, 'price' => 12.99],
  'startupname.io' => ['available' => true, 'premium' => false, 'price' => 35.99],
  'innovation.tech' => ['available' => true, 'premium' => true, 'price' => 45.99],
  'blog.net' => ['available' => true, 'premium' => false, 'price' => 13.99],
];

// Popular TLDs with pricing
$tld_pricing = [
  'com' => 12.99,
  'net' => 13.99,
  'org' => 14.99,
  'io' => 35.99,
  'co' => 25.99,
  'tech' => 45.99,
  'app' => 15.99,
  'dev' => 15.99,
];

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// SINGLE DOMAIN CHECK
if ($action === 'check') {
  $domain = strtolower(trim($_POST['domain'] ?? $_GET['domain'] ?? ''));

  if (empty($domain)) {
    jsonResponse(false, 'Domain is required');
  }

  // Simulate slight delay
  usleep(200000);

  $available = isset($mock_domains[$domain]) ? $mock_domains[$domain]['available'] : (crc32($domain) % 3 == 0);
  $premium = isset($mock_domains[$domain]) ? $mock_domains[$domain]['premium'] : (crc32($domain) % 5 == 0);

  // Extract TLD
  $parts = explode('.', $domain);
  $tld = end($parts);
  $price = $tld_pricing[$tld] ?? 12.99;

  if ($premium) {
    $price = $price * 3; // Premium domains cost 3x
  }

  // When domain is taken, generate suggestions
  $suggestions = [];
  $parts = explode('.', $domain);
  $name = count($parts) >= 2 ? implode('.', array_slice($parts, 0, -1)) : $domain;
  $currentTld = end($parts);
  if (!$available) {
    $altTlds = ['com', 'net', 'org', 'io', 'co'];
    foreach ($altTlds as $t) {
      if ($t !== $currentTld) {
        $altDomain = $name . '.' . $t;
        $altAvail = isset($mock_domains[$altDomain]) ? $mock_domains[$altDomain]['available'] : (crc32($altDomain) % 3 !== 0);
        $altPrice = $tld_pricing[$t] ?? 12.99;
        $suggestions[] = ['domain' => $altDomain, 'available' => $altAvail, 'price' => $altPrice];
      }
    }
    $prefixes = ['get', 'my', 'the', 'try'];
    foreach ($prefixes as $pf) {
      $varDomain = $pf . $name . '.' . $currentTld;
      $varAvail = isset($mock_domains[$varDomain]) ? $mock_domains[$varDomain]['available'] : (crc32($varDomain) % 3 !== 0);
      $varPrice = $tld_pricing[$currentTld] ?? 12.99;
      $suggestions[] = ['domain' => $varDomain, 'available' => $varAvail, 'price' => $varPrice];
    }
  }

  jsonResponse(true, 'Domain check successful', [
    'domain' => $domain,
    'available' => $available,
    'premium' => $premium,
    'price' => $price,
    'renewal_price' => $price * 1.1,
    'tld' => $tld,
    'message' => $available ? 'Available for registration' : 'Already registered',
    'suggestions' => $suggestions
  ]);
}

// MULTIPLE DOMAIN CHECK
if ($action === 'check_multiple') {
  $domains = json_decode($_POST['domains'] ?? '[]', true);

  if (!is_array($domains) || empty($domains)) {
    jsonResponse(false, 'Invalid domains list');
  }

  $results = [];
  foreach ($domains as $domain) {
    $domain = strtolower(trim($domain));

    if (empty($domain))
      continue;

    $available = isset($mock_domains[$domain]) ? $mock_domains[$domain]['available'] : (crc32($domain) % 3 == 0);
    $premium = isset($mock_domains[$domain]) ? $mock_domains[$domain]['premium'] : (crc32($domain) % 5 == 0);

    $parts = explode('.', $domain);
    $tld = end($parts);
    $price = $tld_pricing[$tld] ?? 12.99;

    if ($premium) {
      $price = $price * 3;
    }

    $results[] = [
      'domain' => $domain,
      'available' => $available,
      'premium' => $premium,
      'price' => $price,
      'tld' => $tld
    ];
  }

  jsonResponse(true, 'Batch check successful', ['results' => $results]);
}

// REGISTER DOMAIN (Mock)
if ($action === 'register') {
  $domain = strtolower(trim($_POST['domain'] ?? ''));
  $period = (int) ($_POST['period'] ?? 1);

  if (empty($domain)) {
    jsonResponse(false, 'Domain is required');
  }

  // Simulate processing time
  sleep(1);

  $parts = explode('.', $domain);
  $tld = end($parts);
  $price = $tld_pricing[$tld] ?? 12.99;
  $price = $price * $period;

  jsonResponse(true, 'Domain registered successfully', [
    'domain' => $domain,
    'registration_id' => 'REG' . date('Ymd') . rand(100000, 999999),
    'registered_date' => date('Y-m-d'),
    'expiry_date' => date('Y-m-d', strtotime("+$period year")),
    'period' => $period,
    'total_cost' => $price,
    'status' => 'active',
    'nameservers' => [
      'ns1.example.com',
      'ns2.example.com'
    ]
  ]);
}

// RENEW DOMAIN (Mock)
if ($action === 'renew') {
  $domain = strtolower(trim($_POST['domain'] ?? ''));
  $period = (int) ($_POST['period'] ?? 1);

  if (empty($domain)) {
    jsonResponse(false, 'Domain is required');
  }

  sleep(1);

  $parts = explode('.', $domain);
  $tld = end($parts);
  $price = $tld_pricing[$tld] ?? 12.99;
  $price = $price * $period;

  jsonResponse(true, 'Domain renewed successfully', [
    'domain' => $domain,
    'renewal_id' => 'REN' . date('Ymd') . rand(100000, 999999),
    'new_expiry_date' => date('Y-m-d', strtotime("+$period year")),
    'period' => $period,
    'total_cost' => $price
  ]);
}

// GET DOMAIN INFO (Mock)
if ($action === 'info') {
  $domain = strtolower(trim($_POST['domain'] ?? ''));

  if (empty($domain)) {
    jsonResponse(false, 'Domain is required');
  }

  jsonResponse(true, 'Domain information retrieved', [
    'domain' => $domain,
    'status' => 'active',
    'registered_date' => date('Y-m-d', strtotime('-2 years')),
    'expiry_date' => date('Y-m-d', strtotime('+1 year')),
    'registrar' => 'MockNIC',
    'nameservers' => [
      'ns1.example.com',
      'ns2.example.com'
    ],
    'registry_status' => 'clientTransferProhibited',
    'whois_privacy' => 'enabled'
  ]);
}

// GET PRICING
if ($action === 'pricing') {
  jsonResponse(true, 'Pricing retrieved', [
    'tlds' => $tld_pricing,
    'notice' => 'Prices are for 1 year registration'
  ]);
}

jsonResponse(false, 'Invalid action. Use: check, check_multiple, register, renew, info, or pricing');
?>
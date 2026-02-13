<?php
// Test OnlineNIC API Connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/onlinenic.php';
require_once __DIR__ . '/api/OnlineNICAPI.php';

echo "<h1>OnlineNIC API Test</h1>";

echo "<h2>Configuration:</h2>";
echo "User: " . ONLINENIC_USER . "<br>";
echo "Environment: " . ONLINENIC_ENV . "<br>";
echo "API URL: " . getOnlineNICBaseURL() . "<br>";

echo "<h2>Testing Domain Check:</h2>";

$api = new OnlineNICAPI();

// Test simple domain
$testDomain = 'example.com';
echo "<p>Checking domain: <strong>$testDomain</strong></p>";

$result = $api->checkDomain($testDomain, '1');

echo "<h3>Result:</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";

if ($result['success']) {
  echo "<p style='color: green;'>✓ API is working!</p>";
  echo "<p>Domain: " . ($result['data']['domain'] ?? 'N/A') . "</p>";
  echo "<p>Available: " . (($result['data']['avail'] ?? 0) == 1 ? 'Yes' : 'No') . "</p>";
} else {
  echo "<p style='color: red;'>✗ API Error: " . $result['message'] . "</p>";
  echo "<p>Error Code: " . $result['code'] . "</p>";
}

echo "<hr>";
echo "<h2>Testing Multiple Domains:</h2>";

$domains = ['test.com', 'example.net', 'sample.org'];

foreach ($domains as $domain) {
  echo "<p>Checking: <strong>$domain</strong>... ";
  $result = $api->checkDomain($domain, '1');

  if ($result['success']) {
    $avail = ($result['data']['avail'] ?? 0) == 1 ? 'Available' : 'Taken';
    echo "<span style='color: " . ($avail == 'Available' ? 'green' : 'red') . "'>$avail</span>";
  } else {
    echo "<span style='color: orange;'>Error: " . $result['message'] . "</span>";
  }
  echo "</p>";
}
?>
<?php
echo "=== Database Connection Test ===\n\n";

require_once 'config/database.php';
global $conn;

echo "1. Connection Status:\n";
if ($conn) {
  echo "   ✓ Database CONNECTED\n\n";

  // Test queries
  echo "2. Testing tables:\n";
  $tables = ['users', 'domains', 'orders'];
  foreach ($tables as $table) {
    $result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM $table");
    if ($result) {
      $row = mysqli_fetch_assoc($result);
      echo "   ✓ $table: " . $row['cnt'] . " records\n";
    } else {
      echo "   ✗ $table: " . mysqli_error($conn) . "\n";
    }
  }
} else {
  echo "   ✗ Database NOT CONNECTED\n";
  echo "   → Using mock data fallback\n\n";
  echo "2. Dashboard API will use mock data\n";
}

echo "\n3. Testing Dashboard API response:\n";
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
$_SERVER['HTTP_ACCEPT'] = 'application/json';

// Capture API output
ob_start();
include 'admin/api/dashboard_data.php';
$apiOutput = ob_get_clean();

$json = json_decode($apiOutput, true);
if ($json && isset($json['success'])) {
  echo "   ✓ API Success: " . ($json['success'] ? 'Yes' : 'No') . "\n";
  if (isset($json['data'])) {
    echo "   ✓ Total Domains: " . ($json['data']['total_domains'] ?? 'N/A') . "\n";
    echo "   ✓ Recent Domains: " . count($json['data']['recent_domains'] ?? []) . "\n";
    echo "   ✓ Recent Orders: " . count($json['data']['recent_orders'] ?? []) . "\n";
  }
} else {
  echo "   ✗ API Error or Invalid JSON\n";
  echo "   → First 100 chars: " . substr($apiOutput, 0, 100) . "\n";
}

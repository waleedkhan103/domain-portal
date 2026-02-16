<?php
/**
 * Admin Dashboard Diagnostic Test
 * Run this to verify your admin setup is working
 */

echo "=== Admin Dashboard Diagnostic ===\n\n";

// 1. Check config files
echo "1. Checking configuration files...\n";
$configFiles = [
  'config/paths.php',
  'config/database.php',
  'config/admin.php',
  'admin/includes/auth.php',
  'admin/includes/csrf.php',
];

foreach ($configFiles as $file) {
  $exists = file_exists($file);
  echo "   " . ($exists ? "✓" : "✗") . " $file\n";
}

// 2. Test admin credentials
echo "\n2. Admin Credentials (from config):\n";
require_once 'config/admin.php';
if (defined('ADMIN_USERNAME') && defined('ADMIN_PASSWORD')) {
  echo "   ✓ Username: " . ADMIN_USERNAME . "\n";
  echo "   ✓ Password: " . (strlen(ADMIN_PASSWORD) > 0 ? "[set]" : "[empty]") . "\n";
  echo "   ✓ DB-only admins required: " . (ADMIN_REQUIRE_DB ? "Yes" : "No") . "\n";
} else {
  echo "   ✗ Admin credentials not configured\n";
}

// 3. Test database connection
echo "\n3. Database Connection:\n";
require_once 'config/database.php';
global $conn;
if ($conn) {
  echo "   ✓ Connected to database: " . DB_NAME . "\n";
} else {
  echo "   ✗ Database connection failed (this is OK if MySQL service is not running)\n";
  echo "   → Mock data will be used for testing\n";
}

// 4. Check session
echo "\n4. Session Status:\n";
session_start();
if (isset($_SESSION['admin_logged_in'])) {
  echo "   ✓ Admin logged in\n";
} else {
  echo "   ✗ Not logged in (expected)\n";
  echo "   → Please visit: /admin/login.php\n";
}

// 5. Test mock data API response
echo "\n5. Testing Mock Data API:\n";
ob_start();
include 'admin/api/dashboard_data_mock.php';
$output = ob_get_clean();

if ($output && $json = json_decode($output, true)) {
  echo "   ✓ Mock data API working\n";
  echo "   ✓ Has mock data: " . (isset($json['data']) ? "Yes" : "No") . "\n";
  if (isset($json['data'])) {
    echo "   ✓ Total domains: " . ($json['data']['total_domains'] ?? 'N/A') . "\n";
    echo "   ✓ Recent domains: " . (count($json['data']['recent_domains'] ?? []) ?? 0) . "\n";
    echo "   ✓ Recent orders: " . (count($json['data']['recent_orders'] ?? []) ?? 0) . "\n";
  }
} else {
  echo "   ✗ Mock data API failed\n";
  echo "   → Output: " . substr($output, 0, 100) . "...\n";
}

// 6. Test search mock API
echo "\n6. Testing Mock Search API:\n";
$_GET['q'] = 'example';
ob_start();
include 'admin/api/dashboard_search_mock.php';
$output = ob_get_clean();

if ($output && $json = json_decode($output, true)) {
  echo "   ✓ Mock search API working\n";
  echo "   ✓ Search results: " . count($json['data'] ?? []) . " items\n";
} else {
  echo "   ✗ Mock search API failed\n";
}

// 7. Summary
echo "\n7. Summary:\n";
echo "   TO GET STARTED:\n";
echo "   1. Visit: http://localhost/admin/login.php\n";
echo "   2. Login with: admin / admin\n";
echo "   3. Access dashboard: http://localhost/admin/dashboard.php\n";
echo "   4. Mock data will be displayed (database is optional for testing)\n";

echo "\n=== Test Complete ===\n";

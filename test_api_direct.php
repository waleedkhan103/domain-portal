<?php
// Direct test - simulate what the dashboard JS does
echo "Testing Dashboard API endpoints...\n\n";

// 1. Test mock data API
echo "1. Testing dashboard_data_mock.php:\n";
ob_start();
include 'admin/api/dashboard_data_mock.php';
$mockOutput = ob_get_clean();

if ($mockOutput) {
  $json = json_decode($mockOutput, true);
  if ($json && isset($json['data'])) {
    echo "✓ Mock data API working\n";
    echo "  - Total domains: " . ($json['data']['total_domains'] ?? 'missing') . "\n";
    echo "  - Recent domains: " . count($json['data']['recent_domains'] ?? []) . "\n";
    echo "  - Recent orders: " . count($json['data']['recent_orders'] ?? []) . "\n";
  } else {
    echo "✗ Mock data API returned invalid JSON\n";
    echo "  Output: " . substr($mockOutput, 0, 200) . "\n";
  }
} else {
  echo "✗ Mock data API returned empty\n";
}

// 2. Test mock search API
echo "\n2. Testing dashboard_search_mock.php:\n";
$_GET['q'] = 'example';
ob_start();
include 'admin/api/dashboard_search_mock.php';
$searchOutput = ob_get_clean();

if ($searchOutput) {
  $json = json_decode($searchOutput, true);
  if ($json && isset($json['data'])) {
    echo "✓ Mock search API working\n";
    echo "  - Results: " . count($json['data']) . "\n";
  } else {
    echo "✗ Mock search API returned invalid JSON\n";
  }
} else {
  echo "✗ Mock search API returned empty\n";
}

// 3. Test if actual dashboard_data.php would work
echo "\n3. Testing dashboard_data.php (should fallback to mock if no DB):\n";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
ob_start();
include 'admin/api/dashboard_data.php';
$dataOutput = ob_get_clean();

if ($dataOutput) {
  $json = json_decode($dataOutput, true);
  if ($json && isset($json['data'])) {
    echo "✓ Dashboard data API working\n";
    echo "  - Total domains: " . ($json['data']['total_domains'] ?? 'missing') . "\n";
  } else {
    echo "✗ Dashboard data API returned invalid JSON\n";
    echo "  First 100 chars: " . substr($dataOutput, 0, 100) . "\n";
  }
} else {
  echo "✗ Dashboard data API returned empty\n";
}

echo "\n=== Complete ===\n";

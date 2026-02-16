<?php
// Simulate what a browser would do - direct API call without output buffering
echo "Testing API as browser would call it:\n\n";

// Test 1: Call dashboard_data API directly
echo "API Response from admin/api/dashboard_data.php:\n";
echo "================================================\n";

// Set up session like the browser would
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Create a fake request for the server
$_SERVER['REQUEST_METHOD'] = 'GET';

// Call the API directly
$_SERVER['HTTP_ACCEPT'] = 'application/json';
include 'admin/api/dashboard_data.php';

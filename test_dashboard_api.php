<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;

// Mock server setup for API
$_SERVER['REQUEST_URI'] = '/admin/api/dashboard_data.php';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once 'config/database.php';
global $conn;

echo "=== Dashboard API Test ===\n";
echo "DB Connection: " . ($conn ? "OK" : "FAILED") . "\n";

if (!$conn) {
  die("Cannot connect to database\n");
}

// Test: Count domains
$r = mysqli_query($conn, 'SELECT COUNT(*) as cnt FROM domains');
$domainCnt = ($r ? mysqli_fetch_assoc($r)['cnt'] : 0);

// Test: Count orders
$r = mysqli_query($conn, 'SELECT COUNT(*) as cnt FROM orders');
$orderCnt = ($r ? mysqli_fetch_assoc($r)['cnt'] : 0);

// Test: Count users
$r = mysqli_query($conn, 'SELECT COUNT(*) as cnt FROM users');
$userCnt = ($r ? mysqli_fetch_assoc($r)['cnt'] : 0);

echo "Domains: $domainCnt\n";
echo "Orders: $orderCnt\n";
echo "Users: $userCnt\n";

// Now test the actual API
echo "\n=== Running API ===\n";
require_once 'admin/api/dashboard_data.php';

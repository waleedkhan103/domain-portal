<?php
// Disable error display - show JSON instead
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
  ini_set('display_errors', '0');
  error_reporting(0);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// First check: if database NOT available, serve mock data without any auth check
require_once __DIR__ . '/../../config/database.php';
global $conn;

if (!$conn) {
  // Database is down - serve mock data for development/testing
  include __DIR__ . '/dashboard_data_mock.php';
  exit;
}

// Database IS available - now require admin auth
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

$out = ['success' => true, 'data' => []];

// Helper to check column existence
function hasColumn($conn, $table, $col)
{
  $q = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
  return ($q && mysqli_num_rows($q) > 0);
}

// Domains counts
$totalDomains = 0;
$activeDomains = 0;
$expiring30 = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM domains")) {
  $stmt->execute();
  $r = $stmt->get_result();
  $totalDomains = ($r->fetch_assoc()['cnt'] ?? 0);
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM domains");
  $totalDomains = ($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
}

if ($stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM domains WHERE status = 'active'")) {
  $stmt->execute();
  $r = $stmt->get_result();
  $activeDomains = ($r->fetch_assoc()['cnt'] ?? 0);
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM domains WHERE status='active'");
  $activeDomains = ($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
}

if ($stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM domains WHERE (COALESCE(expires_at, expiry_date, '1970-01-01') BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY))")) {
  $stmt->execute();
  $r = $stmt->get_result();
  $expiring30 = ($r->fetch_assoc()['cnt'] ?? 0);
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM domains WHERE (COALESCE(expires_at, expiry_date, '1970-01-01') BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY))");
  $expiring30 = ($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
}

$out['data']['total_domains'] = (int) $totalDomains;
$out['data']['active_domains'] = (int) $activeDomains;
$out['data']['expiring_30'] = (int) $expiring30;

// Revenue this month and last month for delta
$revenueThis = 0;
$revenueLast = 0;
if ($stmt = $conn->prepare("SELECT IFNULL(SUM(total),0) as s FROM orders WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")) {
  $stmt->execute();
  $r = $stmt->get_result();
  $revenueThis = (float) ($r->fetch_assoc()['s'] ?? 0);
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT IFNULL(SUM(total),0) as s FROM orders WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
  $revenueThis = ($r ? (float) mysqli_fetch_assoc($r)['s'] : 0);
}
if ($stmt = $conn->prepare("SELECT IFNULL(SUM(total),0) as s FROM orders WHERE created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH),'%Y-%m-01') AND created_at < DATE_FORMAT(NOW(), '%Y-%m-01')")) {
  $stmt->execute();
  $r = $stmt->get_result();
  $revenueLast = (float) ($r->fetch_assoc()['s'] ?? 0);
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT IFNULL(SUM(total),0) as s FROM orders WHERE created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH),'%Y-%m-01') AND created_at < DATE_FORMAT(NOW(), '%Y-%m-01')");
  $revenueLast = ($r ? (float) mysqli_fetch_assoc($r)['s'] : 0);
}

$out['data']['revenue_month'] = $revenueThis;
$out['data']['revenue_last_month'] = $revenueLast;

// Quick stats
$pendingOrders = 0;
$recentRegistrations = 0;
$failedPayments = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM orders WHERE status = 'pending'")) {
  $stmt->execute();
  $r = $stmt->get_result();
  $pendingOrders = ($r->fetch_assoc()['cnt'] ?? 0);
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM orders WHERE status='pending'");
  $pendingOrders = ($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
}

// Recent registrations (last 7 days) - try created_at or fallback to id
$recentRegistrations = 0;
if (hasColumn($conn, 'domains', 'created_at')) {
  if ($stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM domains WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")) {
    $stmt->execute();
    $r = $stmt->get_result();
    $recentRegistrations = ($r->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
  }
} else {
  // fallback: count domains with id in range (approximate new rows in last 7 days) - return 0
  $recentRegistrations = 0;
}

if ($stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM transactions WHERE type = 'payment_failed'")) {
  $stmt->execute();
  $r = $stmt->get_result();
  $failedPayments = ($r->fetch_assoc()['cnt'] ?? 0);
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM transactions WHERE type='payment_failed'");
  $failedPayments = ($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
}

$out['data']['pending_orders'] = (int) $pendingOrders;
$out['data']['recent_registrations'] = (int) $recentRegistrations;
$out['data']['failed_payments'] = (int) $failedPayments;

// Domains by status
$statusCounts = [];
if ($stmt = $conn->prepare("SELECT status, COUNT(*) as cnt FROM domains GROUP BY status")) {
  $stmt->execute();
  $r = $stmt->get_result();
  while ($row = $r->fetch_assoc())
    $statusCounts[$row['status']] = (int) $row['cnt'];
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM domains GROUP BY status");
  while ($row = mysqli_fetch_assoc($r))
    $statusCounts[$row['status']] = (int) $row['cnt'];
}
$out['data']['domains_by_status'] = $statusCounts;

// Recent domains (last 10)
$recentDomains = [];
$colDate = hasColumn($conn, 'domains', 'created_at') ? 'created_at' : (hasColumn($conn, 'domains', 'registered_date') ? 'registered_date' : null);
$sqlRecentDomains = $colDate ? "SELECT d.id, d.domain_name, d.user_id, d.status, COALESCE(d.created_at, d.registered_date, '') as registered_at, u.email FROM domains d LEFT JOIN users u ON u.id = d.user_id ORDER BY COALESCE(d.created_at, d.registered_date, d.id) DESC LIMIT 10" : "SELECT d.id, d.domain_name, d.user_id, d.status, u.email FROM domains d LEFT JOIN users u ON u.id = d.user_id ORDER BY d.id DESC LIMIT 10";
if ($stmt = $conn->prepare($sqlRecentDomains)) {
  $stmt->execute();
  $r = $stmt->get_result();
  while ($row = $r->fetch_assoc())
    $recentDomains[] = $row;
  $stmt->close();
} else {
  $r = mysqli_query($conn, $sqlRecentDomains);
  while ($row = mysqli_fetch_assoc($r))
    $recentDomains[] = $row;
}
$out['data']['recent_domains'] = $recentDomains;

// Recent orders (last 10)
$recentOrders = [];
if ($stmt = $conn->prepare("SELECT o.id, COALESCE(o.order_number, o.id) as order_number, o.user_id, o.total, o.status, o.created_at, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 10")) {
  $stmt->execute();
  $r = $stmt->get_result();
  while ($row = $r->fetch_assoc())
    $recentOrders[] = $row;
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT o.id, COALESCE(o.order_number, o.id) as order_number, o.user_id, o.total, o.status, o.created_at, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 10");
  while ($row = mysqli_fetch_assoc($r))
    $recentOrders[] = $row;
}
$out['data']['recent_orders'] = $recentOrders;

// Alerts
$alerts = [];
if ($stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM domains WHERE (COALESCE(expires_at, expiry_date, '1970-01-01') BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY))")) {
  $stmt->execute();
  $r = $stmt->get_result();
  $alerts['expiring_7'] = (int) ($r->fetch_assoc()['cnt'] ?? 0);
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM domains WHERE (COALESCE(expires_at, expiry_date, '1970-01-01') BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY))");
  $alerts['expiring_7'] = ($r ? (int) mysqli_fetch_assoc($r)['cnt'] : 0);
}
if ($stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM domains WHERE (COALESCE(expires_at, expiry_date, '1970-01-01') BETWEEN DATE_ADD(NOW(), INTERVAL 8 DAY) AND DATE_ADD(NOW(), INTERVAL 14 DAY))")) {
  $stmt->execute();
  $r = $stmt->get_result();
  $alerts['expiring_14'] = (int) ($r->fetch_assoc()['cnt'] ?? 0);
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM domains WHERE (COALESCE(expires_at, expiry_date, '1970-01-01') BETWEEN DATE_ADD(NOW(), INTERVAL 8 DAY) AND DATE_ADD(NOW(), INTERVAL 14 DAY))");
  $alerts['expiring_14'] = ($r ? (int) mysqli_fetch_assoc($r)['cnt'] : 0);
}
$alerts['pending_orders'] = (int) $pendingOrders;
$out['data']['alerts'] = $alerts;

echo json_encode($out);
exit;

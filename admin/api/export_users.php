<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

global $conn;
if (!$conn) {
  http_response_code(500);
  echo 'DB unavailable';
  exit;
}

// detect available columns to avoid referencing missing fields
$availableCols = [];
$colsRes = mysqli_query($conn, "SHOW COLUMNS FROM users");
if ($colsRes) {
  while ($col = mysqli_fetch_assoc($colsRes)) {
    $availableCols[$col['Field']] = true;
  }
}

$q = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = [];
$params = [];
$types = '';
if ($q !== '') {
  $where[] = "(CONCAT(first_name,' ',last_name) LIKE ? OR email LIKE ? OR username LIKE ? )";
  $like = '%' . $q . '%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= 'sss';
}
if ($role === 'admins') {
  $where[] = "is_admin = 1";
}
if ($role === 'regular') {
  $where[] = "(is_admin = 0 OR is_admin IS NULL)";
}
if ($status !== 'all') {
  $where[] = "status = ?";
  $params[] = $status;
  $types .= 's';
}
if ($dateFrom) {
  $where[] = "created_at >= ?";
  $params[] = $dateFrom . ' 00:00:00';
  $types .= 's';
}
if ($dateTo) {
  $where[] = "created_at <= ?";
  $params[] = $dateTo . ' 23:59:59';
  $types .= 's';
}

$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Build select fields based on available columns
$selectFields = ['u.id'];
if (!empty($availableCols['email']))
  $selectFields[] = 'u.email';
if (!empty($availableCols['first_name']))
  $selectFields[] = 'u.first_name';
if (!empty($availableCols['last_name']))
  $selectFields[] = 'u.last_name';
if (!empty($availableCols['username']))
  $selectFields[] = 'u.username';
if (!empty($availableCols['phone']))
  $selectFields[] = 'u.phone';
if (!empty($availableCols['country']))
  $selectFields[] = 'u.country';
if (!empty($availableCols['created_at']))
  $selectFields[] = 'u.created_at';
if (!empty($availableCols['status']))
  $selectFields[] = 'u.status';
if (!empty($availableCols['is_admin']))
  $selectFields[] = 'u.is_admin';

$selectFields[] = '(SELECT COUNT(*) FROM domains d WHERE d.user_id = u.id) as domain_count';
$selectFields[] = '(SELECT IFNULL(SUM(total),0) FROM orders o WHERE o.user_id = u.id) as total_spent';

$sql = "SELECT " . implode(', ', $selectFields) . " FROM users u $whereSql ORDER BY u.id DESC";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users_export_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
// build CSV header to match select fields
$csvHeader = [];
foreach ($selectFields as $f) {
  // strip table aliases and AS
  $label = preg_replace('/^.*\.(.*)$/', '$1', $f);
  $label = preg_replace('/\s+as\s+/i', ' as ', $label);
  if (stripos($label, ' as ') !== false) {
    $parts = preg_split('/\s+as\s+/i', $label);
    $label = trim($parts[1]);
  }
  $csvHeader[] = $label;
}
fputcsv($out, $csvHeader);

if ($stmt = $conn->prepare($sql)) {
  if ($types)
    $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $r = $stmt->get_result();
  while ($row = $r->fetch_assoc())
    fputcsv($out, $row);
  $stmt->close();
} else {
  $r = mysqli_query($conn, $sql);
  while ($row = mysqli_fetch_assoc($r))
    fputcsv($out, $row);
}
fclose($out);
exit;

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

$sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.username, u.phone, u.country, u.created_at, u.status, u.is_admin,
          (SELECT COUNT(*) FROM domains d WHERE d.user_id = u.id) as domain_count,
          (SELECT IFNULL(SUM(total),0) FROM orders o WHERE o.user_id = u.id) as total_spent
        FROM users u $whereSql ORDER BY u.id DESC";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users_export_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['id', 'email', 'first_name', 'last_name', 'username', 'phone', 'country', 'created_at', 'status', 'is_admin', 'domain_count', 'total_spent']);

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

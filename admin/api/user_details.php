<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

global $conn;
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
  echo json_encode(['success' => false, 'message' => 'Missing id']);
  exit;
}

$user = null;
// build select based on existing columns
$availableCols = [];
$colsRes = mysqli_query($conn, "SHOW COLUMNS FROM users");
if ($colsRes) {
  while ($col = mysqli_fetch_assoc($colsRes)) {
    $availableCols[$col['Field']] = true;
  }
}

$selectFields = ['id'];
if (!empty($availableCols['email']))
  $selectFields[] = 'email';
if (!empty($availableCols['first_name']))
  $selectFields[] = 'first_name';
if (!empty($availableCols['last_name']))
  $selectFields[] = 'last_name';
if (!empty($availableCols['username']))
  $selectFields[] = 'username';
if (!empty($availableCols['phone']))
  $selectFields[] = 'phone';
if (!empty($availableCols['country']))
  $selectFields[] = 'country';
if (!empty($availableCols['created_at']))
  $selectFields[] = 'created_at';
if (!empty($availableCols['status']))
  $selectFields[] = 'status';
if (!empty($availableCols['is_admin']))
  $selectFields[] = 'is_admin';

$sqlUser = "SELECT " . implode(',', $selectFields) . " FROM users WHERE id = ? LIMIT 1";
if ($stmt = $conn->prepare($sqlUser)) {
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $r = $stmt->get_result();
  $user = $r ? $r->fetch_assoc() : null;
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT " . implode(',', $selectFields) . " FROM users WHERE id = $id LIMIT 1");
  $user = $r ? mysqli_fetch_assoc($r) : null;
}

if (!$user) {
  echo json_encode(['success' => false, 'message' => 'User not found']);
  exit;
}

$domains = [];
if ($stmt2 = $conn->prepare("SELECT id, domain_name, status, COALESCE(created_at, registered_date, '') as registered_at FROM domains WHERE user_id = ? ORDER BY id DESC LIMIT 20")) {
  $stmt2->bind_param('i', $id);
  $stmt2->execute();
  $r2 = $stmt2->get_result();
  while ($row = $r2->fetch_assoc())
    $domains[] = $row;
  $stmt2->close();
}

$orders = [];
if ($stmt3 = $conn->prepare("SELECT id, COALESCE(order_number,id) as order_number, total, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 20")) {
  $stmt3->bind_param('i', $id);
  $stmt3->execute();
  $r3 = $stmt3->get_result();
  while ($row = $r3->fetch_assoc())
    $orders[] = $row;
  $stmt3->close();
}

echo json_encode(['success' => true, 'data' => ['user' => $user, 'domains' => $domains, 'orders' => $orders]]);
exit;

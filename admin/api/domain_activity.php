<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

global $conn;
$domainId = (int) ($_GET['domain_id'] ?? 0);
if (!$domainId) {
  echo json_encode(['success' => false, 'data' => []]);
  exit;
}

$rows = [];
if ($stmt = $conn->prepare("SELECT action, description, created_at FROM activity_log WHERE domain_id = ? ORDER BY created_at DESC LIMIT 50")) {
  $stmt->bind_param('i', $domainId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
  }
  $stmt->close();
} else {
  $res = mysqli_query($conn, "SELECT action, description, created_at FROM activity_log WHERE domain_id = $domainId ORDER BY created_at DESC LIMIT 50");
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = $r;
  }
}

echo json_encode(['success' => true, 'data' => $rows]);
exit;

<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../api/OnlineNICAPI.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
  jsonResponse(false, 'Invalid request method');
$posted = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($posted)) {
  http_response_code(403);
  jsonResponse(false, 'Invalid CSRF token');
}

global $conn;
$orderId = (int) ($_POST['order_id'] ?? 0);
if (!$orderId)
  jsonResponse(false, 'Missing order id');

// fetch order
$order = null;
if ($stmt = $conn->prepare("SELECT id, status FROM orders WHERE id = ? LIMIT 1")) {
  $stmt->bind_param('i', $orderId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $res->num_rows > 0)
    $order = $res->fetch_assoc();
  $stmt->close();
}
if (!$order)
  jsonResponse(false, 'Order not found');
if ($order['status'] !== 'pending')
  jsonResponse(false, 'Order is not pending');

// set status to processing
if ($stmt = $conn->prepare("UPDATE orders SET status = 'processing' WHERE id = ?")) {
  $stmt->bind_param('i', $orderId);
  $stmt->execute();
  $stmt->close();
} else {
  mysqli_query($conn, "UPDATE orders SET status='processing' WHERE id = $orderId");
}

// fetch order items
$items = [];
if ($stmt2 = $conn->prepare("SELECT id, domain_name, operation_type, period, price FROM order_items WHERE order_id = ?")) {
  $stmt2->bind_param('i', $orderId);
  $stmt2->execute();
  $r = $stmt2->get_result();
  while ($row = $r->fetch_assoc())
    $items[] = $row;
  $stmt2->close();
}

$api = new OnlineNICAPI();
$failed = [];
foreach ($items as $it) {
  if ($it['operation_type'] === 'register') {
    $data = ['domain' => $it['domain_name'], 'period' => (int) $it['period']];
    $resApi = $api->registerDomain($data);
    if (!($resApi['success'] ?? false)) {
      $failed[] = $it['domain_name'];
    }
  }
}

if (count($failed) === 0) {
  // complete order
  if ($stmt3 = $conn->prepare("UPDATE orders SET status='completed' WHERE id = ?")) {
    $stmt3->bind_param('i', $orderId);
    $stmt3->execute();
    $stmt3->close();
  } else {
    mysqli_query($conn, "UPDATE orders SET status='completed' WHERE id = $orderId");
  }
  $adminId = $_SESSION['admin_id'] ?? 0;
  // log status history or fallback
  if ($stmt4 = $conn->prepare("INSERT INTO order_status_history (order_id, status, changed_by, created_at) VALUES (?, 'completed', ?, NOW())")) {
    $stmt4->bind_param('ii', $orderId, $adminId);
    $stmt4->execute();
    $stmt4->close();
  } else {
    logActivity($adminId, null, 'order_completed', "Order $orderId completed");
  }
  logActivity($adminId, null, 'order_approved', "Order $orderId approved by admin");
  jsonResponse(true, 'Order approved and processed');
} else {
  // partial failure: set to failed
  if ($stmt5 = $conn->prepare("UPDATE orders SET status='failed' WHERE id = ?")) {
    $stmt5->bind_param('i', $orderId);
    $stmt5->execute();
    $stmt5->close();
  } else {
    mysqli_query($conn, "UPDATE orders SET status='failed' WHERE id = $orderId");
  }
  $adminId = $_SESSION['admin_id'] ?? 0;
  logActivity($adminId, null, 'order_failed', "Order $orderId failed for domains: " . implode(',', $failed));
  jsonResponse(false, 'Some domains failed: ' . implode(',', $failed));
}

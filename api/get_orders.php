<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
  jsonResponse(false, 'Not authenticated');
}

$user = getCurrentUser();

$sql = "SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $user['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$orders = mysqli_fetch_all($res, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Attach items for each order
foreach ($orders as &$order) {
  $oid = (int) $order['id'];
  $sql = "SELECT domain_name, operation_type, period, price, status FROM order_items WHERE order_id = ?";
  $st = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($st, 'i', $oid);
  mysqli_stmt_execute($st);
  $r = mysqli_stmt_get_result($st);
  $items = mysqli_fetch_all($r, MYSQLI_ASSOC);
  mysqli_stmt_close($st);
  $order['items'] = $items;
}

jsonResponse(true, 'Orders fetched', ['orders' => $orders]);

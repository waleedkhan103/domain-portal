<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
  jsonResponse(false, 'Not authenticated');
}

$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$orderId)
  jsonResponse(false, 'Missing order id');

$user = getCurrentUser();

$sql = "SELECT id, order_number, total_amount, status, created_at FROM orders WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $orderId, $user['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order)
  jsonResponse(false, 'Order not found');

$sql = "SELECT domain_name, operation_type, period, price, status FROM order_items WHERE order_id = ?";
$st = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($st, 'i', $orderId);
mysqli_stmt_execute($st);
$r = mysqli_stmt_get_result($st);
$items = mysqli_fetch_all($r, MYSQLI_ASSOC);
mysqli_stmt_close($st);

$order['items'] = $items;
// Attach invoice path if exists
$invoicePath = __DIR__ . '/../invoices/invoice_' . $order['id'] . '.html';
if (file_exists($invoicePath)) {
  $order['invoice'] = '/invoices/invoice_' . $order['id'] . '.html';
}

jsonResponse(true, 'Order fetched', ['order' => $order]);

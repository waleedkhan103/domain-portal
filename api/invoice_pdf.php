<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/fpdf.php';

if (!isLoggedIn()) {
  header('HTTP/1.1 401 Unauthorized');
  echo 'Unauthorized';
  exit;
}

$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$orderId) {
  header('HTTP/1.1 400 Bad Request');
  echo 'Missing id';
  exit;
}

$user = getCurrentUser();

// Load order
$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $orderId, $user['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
if (!$order) {
  header('HTTP/1.1 404 Not Found');
  echo 'Order not found';
  exit;
}

// Load items
$stmt = mysqli_prepare($conn, "SELECT domain_name, operation_type, period, price FROM order_items WHERE order_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $orderId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$items = mysqli_fetch_all($res, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Helvetica', '', 12);
$pdf->Cell(0, 10, 'Invoice - Order ' . $order['order_number'], 0, 1);
$pdf->Ln(4);
$pdf->Cell(40, 6, 'Customer:');
$pdf->Cell(0, 6, ($user['first_name'] . ' ' . ($user['last_name'] ?? '')), 0, 1);
$pdf->Ln(4);

// Table header
$pdf->Cell(90, 7, 'Domain', 1, 0);
$pdf->Cell(40, 7, 'Type', 1, 0);
$pdf->Cell(20, 7, 'Period', 1, 0);
$pdf->Cell(30, 7, 'Price', 1, 1);

foreach ($items as $it) {
  $pdf->Cell(90, 6, $it['domain_name'], 1, 0);
  $pdf->Cell(40, 6, $it['operation_type'], 1, 0);
  $pdf->Cell(20, 6, $it['period'], 1, 0);
  $pdf->Cell(30, 6, '$' . number_format($it['price'], 2), 1, 1);
}
$pdf->Ln(6);
$pdf->Cell(0, 8, 'Total: $' . number_format($order['total_amount'], 2), 0, 1);

// Output PDF inline
$pdf->Output('invoice_' . $orderId . '.pdf', 'I');

exit;

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/fpdf.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Allow both admin sessions and regular user sessions
$isAdmin = !empty($_SESSION['admin_logged_in']);
$isUser  = isLoggedIn();

if (!$isAdmin && !$isUser) {
  header('HTTP/1.1 401 Unauthorized');
  echo 'Unauthorized';
  exit;
}

global $conn;

// Accept both ?order_id= (from admin panel) and ?id= (legacy)
$orderId = (int) ($_GET['order_id'] ?? $_GET['id'] ?? 0);
if (!$orderId) {
  header('HTTP/1.1 400 Bad Request');
  echo 'Missing order id';
  exit;
}

// Load order — admins can view any order; regular users only their own
if ($isAdmin) {
  $stmt = $conn->prepare(
    "SELECT o.*, COALESCE(o.total_amount, o.total, 0) AS amount,
            COALESCE(u.name, CONCAT_WS(' ', u.first_name, u.last_name), u.email) AS customer_name,
            u.email AS customer_email
     FROM orders o LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ? LIMIT 1"
  );
  $stmt->bind_param('i', $orderId);
} else {
  $userId = (int) $_SESSION['user_id'];
  $stmt   = $conn->prepare(
    "SELECT o.*, COALESCE(o.total_amount, o.total, 0) AS amount,
            COALESCE(u.name, CONCAT_WS(' ', u.first_name, u.last_name), u.email) AS customer_name,
            u.email AS customer_email
     FROM orders o LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ? AND o.user_id = ? LIMIT 1"
  );
  $stmt->bind_param('ii', $orderId, $userId);
}

$stmt->execute();
$res   = $stmt->get_result();
$order = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$order) {
  header('HTTP/1.1 404 Not Found');
  echo 'Order not found';
  exit;
}

// Load order items if the table exists
$items = [];
$chk   = mysqli_query($conn, "SHOW TABLES LIKE 'order_items'");
if ($chk && mysqli_num_rows($chk) > 0) {
  $stmt2 = $conn->prepare(
    "SELECT domain_name, operation_type, period, price FROM order_items WHERE order_id = ?"
  );
  $stmt2->bind_param('i', $orderId);
  $stmt2->execute();
  $res2  = $stmt2->get_result();
  $items = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [];
  $stmt2->close();
}

// Generate PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Invoice', 0, 1, 'C');
$pdf->SetFont('Helvetica', '', 12);
$pdf->Ln(4);
$pdf->Cell(40, 6, 'Order #:');
$pdf->Cell(0, 6, $order['order_number'] ?? $orderId, 0, 1);
$pdf->Cell(40, 6, 'Date:');
$pdf->Cell(0, 6, $order['created_at'] ?? '', 0, 1);
$pdf->Cell(40, 6, 'Status:');
$pdf->Cell(0, 6, ucfirst($order['status'] ?? ''), 0, 1);
$pdf->Ln(4);
$pdf->Cell(40, 6, 'Customer:');
$pdf->Cell(0, 6, $order['customer_name'] ?? '', 0, 1);
$pdf->Cell(40, 6, 'Email:');
$pdf->Cell(0, 6, $order['customer_email'] ?? '', 0, 1);
$pdf->Ln(6);

if (!empty($items)) {
  // Table header
  $pdf->SetFont('Helvetica', 'B', 11);
  $pdf->Cell(90, 7, 'Domain', 1, 0);
  $pdf->Cell(40, 7, 'Type', 1, 0);
  $pdf->Cell(20, 7, 'Period', 1, 0);
  $pdf->Cell(30, 7, 'Price', 1, 1);
  $pdf->SetFont('Helvetica', '', 11);

  foreach ($items as $it) {
    $pdf->Cell(90, 6, $it['domain_name'] ?? '', 1, 0);
    $pdf->Cell(40, 6, $it['operation_type'] ?? '', 1, 0);
    $pdf->Cell(20, 6, $it['period'] ?? '', 1, 0);
    $pdf->Cell(30, 6, '$' . number_format((float)($it['price'] ?? 0), 2), 1, 1);
  }
  $pdf->Ln(4);
}

$pdf->SetFont('Helvetica', 'B', 12);
$pdf->Cell(0, 8, 'Total: $' . number_format((float)($order['amount'] ?? 0), 2), 0, 1);

$pdf->Output('invoice_' . $orderId . '.pdf', 'I');
exit;

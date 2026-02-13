<?php
// Mock checkout API
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'Invalid request method');
}

if (!isLoggedIn()) {
  jsonResponse(false, 'Not authenticated');
}

$user = getCurrentUser();
$action = $_POST['action'] ?? '';
if ($action !== 'checkout') {
  jsonResponse(false, 'Invalid action');
}

// Fetch cart items
$sql = "SELECT * FROM cart WHERE user_id = {$user['id']}";
$res = mysqli_query($conn, $sql);
$cartItems = mysqli_fetch_all($res, MYSQLI_ASSOC);
if (empty($cartItems)) {
  jsonResponse(false, 'Cart is empty');
}

// Calculate subtotal
$subtotal = 0;
foreach ($cartItems as $item) {
  $subtotal += ($item['price'] * (int) $item['period']);
}

// Apply promo if provided
$promoCode = trim($_POST['promo_code'] ?? '');
$discount = 0;
$total = $subtotal;
if (!empty($promoCode)) {
  $codeEsc = mysqli_real_escape_string($conn, strtoupper($promoCode));
  $now = date('Y-m-d H:i:s');
  $pSql = "SELECT * FROM promo_codes WHERE UPPER(code) = '$codeEsc' AND active = 1
           AND (valid_from IS NULL OR valid_from <= '$now')
           AND (valid_until IS NULL OR valid_until >= '$now')
           AND (max_uses IS NULL OR uses_count < max_uses)
           AND min_order <= $subtotal LIMIT 1";
  $pRes = mysqli_query($conn, $pSql);
  if ($pRes && mysqli_num_rows($pRes) > 0) {
    $promo = mysqli_fetch_assoc($pRes);
    if ($promo['discount_type'] === 'percentage') {
      $discount = $subtotal * (float) $promo['discount_value'] / 100;
    } else {
      $discount = min((float) $promo['discount_value'], $subtotal);
    }
    $total = max(0, $subtotal - $discount);
    mysqli_query($conn, "UPDATE promo_codes SET uses_count = uses_count + 1 WHERE id = " . (int) $promo['id']);
  }
}

// Start transaction
mysqli_begin_transaction($conn);
try {
  $orderNumber = generateOrderNumber();
  $stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, order_number, total_amount, status, created_at) VALUES (?, ?, ?, ?, NOW())");
  $status = 'processing';
  mysqli_stmt_bind_param($stmt, 'isds', $user['id'], $orderNumber, $total, $status);
  mysqli_stmt_execute($stmt);
  $orderId = mysqli_insert_id($conn);
  mysqli_stmt_close($stmt);

  // Insert order items
  $stmtItem = mysqli_prepare($conn, "INSERT INTO order_items (order_id, domain_name, operation_type, period, price, status, api_response) VALUES (?, ?, ?, ?, ?, ?, ?)");
  foreach ($cartItems as $item) {
    $apiResponse = json_encode(['mock' => true]);
    $itemStatus = 'completed';
    mysqli_stmt_bind_param($stmtItem, 'issidss', $orderId, $item['domain_name'], $item['operation_type'], $item['period'], $item['price'], $itemStatus, $apiResponse);
    mysqli_stmt_execute($stmtItem);
  }
  mysqli_stmt_close($stmtItem);

  // Update order status to completed
  $sql = "UPDATE orders SET status = 'completed' WHERE id = $orderId";
  mysqli_query($conn, $sql);

  // Clear cart
  $sql = "DELETE FROM cart WHERE user_id = {$user['id']}";
  mysqli_query($conn, $sql);

  // Commit
  mysqli_commit($conn);

  // Insert transaction for billing history
  $transSql = "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )";
  mysqli_query($conn, $transSql);
  $desc = "Order #$orderNumber";
  if ($discount > 0) $desc .= " (promo: -$" . number_format($discount, 2) . ")";
  mysqli_query($conn, "INSERT INTO transactions (user_id, order_id, type, amount, description) VALUES ({$user['id']}, $orderId, 'order', $total, '" . mysqli_real_escape_string($conn, $desc) . "')");

  // Log activity
  logActivity($user['id'], null, 'order_placed', "Order #$orderNumber placed (mock)");

  // Generate simple invoice HTML and save to /invoices
  $invoiceDir = __DIR__ . '/../invoices';
  if (!is_dir($invoiceDir))
    @mkdir($invoiceDir, 0755, true);
  $invoiceFile = $invoiceDir . '/invoice_' . $orderId . '.html';

  $itemsHtml = '';
  foreach ($cartItems as $ci) {
    $itemsHtml .= '<tr><td>' . htmlspecialchars($ci['domain_name']) . '</td><td>' . htmlspecialchars($ci['operation_type']) . '</td><td>' . (int) $ci['period'] . '</td><td>$' . number_format($ci['price'], 2) . '</td></tr>';
  }

  $invoiceHtml = '<!doctype html><html><head><meta charset="utf-8"><title>Invoice #' . htmlspecialchars($orderNumber) . '</title>' .
    '<style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:8px}</style></head><body>' .
    '<h2>Invoice - Order ' . htmlspecialchars($orderNumber) . '</h2>' .
    '<p><strong>Customer:</strong> ' . htmlspecialchars($user['first_name'] . ' ' . ($user['last_name'] ?? '')) . '</p>' .
    '<table><thead><tr><th>Domain</th><th>Type</th><th>Period</th><th>Price</th></tr></thead><tbody>' .
    $itemsHtml .
    '</tbody></table>' .
    '<h3>Total: $' . number_format($total, 2) . '</h3>' .
    '</body></html>';

  @file_put_contents($invoiceFile, $invoiceHtml);

  // Try to send order confirmation email with invoice link (mock)
  $to = $user['email'] ?? null;
  if ($to) {
    $subject = "Order Confirmation - {$orderNumber}";
    $invoicePdfUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_PATH . '/api/invoice_pdf.php?id=' . $orderId;
    $message = "Hi {$user['first_name']},\n\nThank you for your order. You can download your invoice here: " . $invoicePdfUrl . "\n\nRegards,\nDomainPortal";
    $headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'domainportal') . "\r\n";
    // Suppress errors if mail isn't configured
    @mail($to, $subject, $message, $headers);
  }

  jsonResponse(true, 'Order placed', ['redirect' => '/pages/dashboard.php?order_success=1', 'invoice' => 'invoice_' . $orderId . '.html']);
} catch (Exception $ex) {
  mysqli_rollback($conn);
  error_log('Checkout error: ' . $ex->getMessage());
  jsonResponse(false, 'Failed to process order');
}

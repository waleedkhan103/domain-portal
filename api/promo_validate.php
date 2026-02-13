<?php
/**
 * Validate promo/coupon code
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($conn) || !$conn) {
  jsonResponse(false, 'Database error');
}

$code = strtoupper(trim($_POST['code'] ?? $_GET['code'] ?? ''));
$subtotal = (float) ($_POST['subtotal'] ?? $_GET['subtotal'] ?? 0);

if (empty($code)) {
  jsonResponse(false, 'Please enter a promo code');
}

// Create promo_codes table if not exists
$createSql = "CREATE TABLE IF NOT EXISTS promo_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  discount_value DECIMAL(10,2) NOT NULL,
  min_order DECIMAL(10,2) DEFAULT 0,
  max_uses INT DEFAULT NULL,
  uses_count INT DEFAULT 0,
  valid_from DATETIME DEFAULT NULL,
  valid_until DATETIME DEFAULT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $createSql);

// Insert sample promo if table is empty
$check = mysqli_query($conn, "SELECT COUNT(*) as c FROM promo_codes");
$row = mysqli_fetch_assoc($check);
if ($row['c'] == 0) {
  mysqli_query($conn, "INSERT INTO promo_codes (code, discount_type, discount_value, min_order, active) VALUES ('SAVE10', 'percentage', 10, 20, 1)");
  mysqli_query($conn, "INSERT INTO promo_codes (code, discount_type, discount_value, min_order, active) VALUES ('FIRST5', 'fixed', 5, 10, 1)");
}

$codeEsc = mysqli_real_escape_string($conn, $code);
$now = date('Y-m-d H:i:s');
$sql = "SELECT * FROM promo_codes WHERE UPPER(code) = '$codeEsc' AND active = 1
        AND (valid_from IS NULL OR valid_from <= '$now')
        AND (valid_until IS NULL OR valid_until >= '$now')
        AND (max_uses IS NULL OR uses_count < max_uses)
        LIMIT 1";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) === 0) {
  jsonResponse(false, 'Invalid or expired promo code');
}

$promo = mysqli_fetch_assoc($result);
$minOrder = (float) $promo['min_order'];
if ($subtotal < $minOrder) {
  jsonResponse(false, 'Minimum order of $' . number_format($minOrder, 2) . ' required');
}

$discountType = $promo['discount_type'];
$discountVal = (float) $promo['discount_value'];
$discount = $discountType === 'percentage' ? ($subtotal * $discountVal / 100) : min($discountVal, $subtotal);
$finalTotal = max(0, $subtotal - $discount);

jsonResponse(true, 'Promo code applied', [
  'code' => $promo['code'],
  'discount' => round($discount, 2),
  'discount_type' => $discountType,
  'discount_value' => $discountVal,
  'original_total' => $subtotal,
  'final_total' => round($finalTotal, 2)
]);

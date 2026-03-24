<?php
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method');
}

if (!isLoggedIn()) {
    jsonResponse(false, 'Not authenticated');
}

$user = getCurrentUser();
if (!$user) {
    jsonResponse(false, 'Not authenticated');
}

$action = $_POST['action'] ?? '';
if ($action !== 'checkout') {
    jsonResponse(false, 'Invalid action');
}

// ── Payment method ───────────────────────────────────────────────────────────
// 'wallet'  = pay entirely from wallet balance
// 'stripe'  = pay entirely by card via Stripe
// 'mixed'   = use wallet for part, card for the rest
$paymentMethod         = $_POST['payment_method'] ?? 'stripe';
$stripePaymentIntentId = trim($_POST['stripe_payment_intent_id'] ?? '');

// ── Fetch cart ───────────────────────────────────────────────────────────────
$userId    = (int) $user['id'];
$res       = mysqli_query($conn, "SELECT * FROM cart WHERE user_id = $userId");
$cartItems = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Migrate guest cart if DB cart is empty
if (empty($cartItems) && !empty($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
    foreach ($_SESSION['guest_cart'] as $item) {
        $domain = trim((string) ($item['domain_name'] ?? ''));
        if ($domain === '') continue;
        $dEsc   = mysqli_real_escape_string($conn, $domain);
        $opEsc  = mysqli_real_escape_string($conn, (string) ($item['operation_type'] ?? 'register'));
        $period = (int) ($item['period'] ?? 1);
        $price  = (float) ($item['price'] ?? 12.99);
        $isPrem = (int) ($item['is_premium'] ?? 0);
        $exists = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = $userId AND domain_name = '$dEsc' LIMIT 1");
        if ($exists && mysqli_num_rows($exists) > 0) continue;
        mysqli_query($conn, "INSERT INTO cart (user_id, domain_name, operation_type, period, price, is_premium)
                             VALUES ($userId, '$dEsc', '$opEsc', $period, $price, $isPrem)");
    }
    $_SESSION['guest_cart'] = [];
    $res2      = mysqli_query($conn, "SELECT * FROM cart WHERE user_id = $userId");
    $cartItems = $res2 ? mysqli_fetch_all($res2, MYSQLI_ASSOC) : [];
}

if (empty($cartItems)) {
    jsonResponse(false, 'Cart is empty');
}

// ── Calculate totals ─────────────────────────────────────────────────────────
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += ($item['price'] * (int) $item['period']);
}

$privacyEnabled = !empty($_POST['privacy_protection']) && $_POST['privacy_protection'] === '1';
$privacyFee     = 0;
if ($privacyEnabled) {
    foreach ($cartItems as $item) {
        $pricing    = getTldPricing($item['domain_name']);
        $privacyFee += (float) $pricing['privacy_price'];
    }
}

$promoCode = trim($_POST['promo_code'] ?? '');
$discount  = 0;
$total     = $subtotal + $privacyFee;
if (!empty($promoCode)) {
    $codeEsc = mysqli_real_escape_string($conn, strtoupper($promoCode));
    $now     = date('Y-m-d H:i:s');
    $pSql    = "SELECT * FROM promo_codes WHERE UPPER(code) = '$codeEsc' AND active = 1
                AND (valid_from IS NULL OR valid_from <= '$now')
                AND (valid_until IS NULL OR valid_until >= '$now')
                AND (max_uses IS NULL OR uses_count < max_uses)
                AND min_order <= $subtotal LIMIT 1";
    $pRes = mysqli_query($conn, $pSql);
    if ($pRes && mysqli_num_rows($pRes) > 0) {
        $promo    = mysqli_fetch_assoc($pRes);
        $discount = ($promo['discount_type'] === 'percentage')
            ? $subtotal * (float) $promo['discount_value'] / 100
            : min((float) $promo['discount_value'], $subtotal);
        $total    = max(0, $subtotal + $privacyFee - $discount);
        mysqli_query($conn, "UPDATE promo_codes SET uses_count = uses_count + 1 WHERE id = " . (int) $promo['id']);
    }
}

// ── Wallet / card split ───────────────────────────────────────────────────────
$walletBalance = (float) ($user['wallet_balance'] ?? 0.00);

if ($paymentMethod === 'wallet') {
    $walletAmount = $total;
    $cardAmount   = 0.00;
} elseif ($paymentMethod === 'mixed') {
    $walletAmount = min($walletBalance, $total);
    $cardAmount   = max(0.00, $total - $walletAmount);
} else {
    $walletAmount = 0.00;
    $cardAmount   = $total;
}

if ($walletAmount > 0 && $walletBalance < $walletAmount) {
    jsonResponse(false, 'Insufficient wallet balance');
}

// ── Verify Stripe PaymentIntent (server-side) ─────────────────────────────────
if ($cardAmount > 0) {
    if (!STRIPE_ENABLED) {
        jsonResponse(false, 'Card payments are not configured. Please contact support or pay with wallet.');
    }
    if (empty($stripePaymentIntentId)) {
        jsonResponse(false, 'Missing payment confirmation. Please complete card payment.');
    }

    $pi = stripeRequest('GET', 'payment_intents/' . $stripePaymentIntentId);
    if (isset($pi['error'])) {
        jsonResponse(false, 'Payment verification failed: ' . ($pi['error']['message'] ?? 'Unknown Stripe error'));
    }
    if (($pi['status'] ?? '') !== 'succeeded') {
        jsonResponse(false, 'Payment was not completed. Status: ' . ($pi['status'] ?? 'unknown'));
    }
    // Verify amount (allow 1-cent rounding tolerance)
    $expectedCents = (int) round($cardAmount * 100);
    $paidCents     = (int) ($pi['amount_received'] ?? $pi['amount'] ?? 0);
    if (abs($paidCents - $expectedCents) > 1) {
        error_log("Stripe amount mismatch: expected $expectedCents, got $paidCents, PI=$stripePaymentIntentId");
        jsonResponse(false, 'Payment amount mismatch. Please contact support.');
    }
}

// ── Ensure schema is ready ────────────────────────────────────────────────────
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, type ENUM('credit','debit','refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL, balance_before DECIMAL(10,2) NOT NULL, balance_after DECIMAL(10,2) NOT NULL,
    order_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL,
    added_by INT DEFAULT NULL, payment_method VARCHAR(50) DEFAULT NULL,
    stripe_payment_id VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id), INDEX idx_order (order_id)
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, order_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL, amount DECIMAL(10,2) NOT NULL, description VARCHAR(255),
    payment_method VARCHAR(50) DEFAULT NULL, wallet_amount DECIMAL(10,2) DEFAULT 0.00,
    stripe_payment_id VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_user (user_id)
)");

// ── DB Transaction ────────────────────────────────────────────────────────────
mysqli_begin_transaction($conn);
try {
    $orderNumber = generateOrderNumber();
    $status      = 'processing';
    $stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, order_number, total_amount, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, 'isds', $userId, $orderNumber, $total, $status);
    mysqli_stmt_execute($stmt);
    $orderId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Order items
    $stmtItem = mysqli_prepare($conn, "INSERT INTO order_items (order_id, domain_name, operation_type, period, price, auth_code, status, api_response) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($cartItems as $item) {
        $apiResponse = json_encode(['processed' => true]);
        $itemStatus  = ($item['operation_type'] === 'transfer') ? 'pending' : 'completed';
        $authCode    = $item['auth_code'] ?? null;
        mysqli_stmt_bind_param($stmtItem, 'issidsss', $orderId, $item['domain_name'], $item['operation_type'], $item['period'], $item['price'], $authCode, $itemStatus, $apiResponse);
        mysqli_stmt_execute($stmtItem);
    }
    mysqli_stmt_close($stmtItem);

    // Register domains
    $contactId  = generateContactID($userId);
    $regFirst   = mysqli_real_escape_string($conn, trim($_POST['reg_first']   ?? $user['first_name'] ?? ''));
    $regLast    = mysqli_real_escape_string($conn, trim($_POST['reg_last']    ?? $user['last_name']  ?? ''));
    $regEmail   = mysqli_real_escape_string($conn, trim($_POST['reg_email']   ?? $user['email']      ?? ''));
    $regPhone   = mysqli_real_escape_string($conn, trim($_POST['reg_phone']   ?? $user['phone']      ?? ''));
    $regAddress = mysqli_real_escape_string($conn, trim($_POST['reg_address'] ?? $user['address']    ?? ''));

    $stmtDomain = mysqli_prepare($conn,
        "INSERT INTO domains
           (user_id, domain_name, registration_date, expiry_date, expires_at,
            status, is_locked, privacy_enabled, privacy_fee,
            registrant_contact, admin_contact, tech_contact, billing_contact,
            registrant_first, registrant_last, registrant_email, registrant_phone, registrant_address,
            registrant_contact_id, admin_contact_id, tech_contact_id, billing_contact_id)
         VALUES
           (?, ?, CURDATE(), ?, ?, 'active', 0, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           status = 'active', expiry_date = VALUES(expiry_date), expires_at = VALUES(expires_at),
           user_id = VALUES(user_id), privacy_enabled = VALUES(privacy_enabled),
           privacy_fee = VALUES(privacy_fee),
           registrant_first = VALUES(registrant_first), registrant_last = VALUES(registrant_last),
           registrant_email = VALUES(registrant_email), registrant_phone = VALUES(registrant_phone),
           registrant_address = VALUES(registrant_address)"
    );
    if ($stmtDomain) {
        foreach ($cartItems as $item) {
            if (($item['operation_type'] ?? 'register') === 'register') {
                $period              = (int) ($item['period'] ?? 1);
                $expiry              = date('Y-m-d', strtotime("+{$period} year"));
                $privacyEnabledInt   = $privacyEnabled ? 1 : 0;
                $privacyFeePerDomain = $privacyEnabled ? 2.99 : 0.00;
                mysqli_stmt_bind_param(
                    $stmtDomain, 'isssidssssssssiiiii',
                    $userId, $item['domain_name'], $expiry, $expiry,
                    $privacyEnabledInt, $privacyFeePerDomain,
                    $contactId, $contactId, $contactId, $contactId,
                    $regFirst, $regLast, $regEmail, $regPhone, $regAddress,
                    $userId, $userId, $userId, $userId
                );
                if (!mysqli_stmt_execute($stmtDomain)) {
                    error_log('Domain insert failed for ' . $item['domain_name'] . ': ' . mysqli_stmt_error($stmtDomain));
                }
            }
        }
        mysqli_stmt_close($stmtDomain);
    }

    // Domain transfers
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS domain_transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain_id INT NULL, domain_name VARCHAR(255) NOT NULL, user_id INT NOT NULL,
        auth_code VARCHAR(255) NOT NULL,
        status ENUM('pending','in_progress','completed','failed','cancelled') DEFAULT 'pending',
        transfer_type ENUM('in','out') DEFAULT 'in',
        initiated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL, estimated_completion DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_domain_name (domain_name), INDEX idx_user_id (user_id)
    )");

    $hasTransfers = false;
    foreach ($cartItems as $item) {
        if (($item['operation_type'] ?? '') === 'transfer') {
            $hasTransfers      = true;
            $domainNameEsc     = mysqli_real_escape_string($conn, $item['domain_name']);
            $authCodeEsc       = mysqli_real_escape_string($conn, $item['auth_code'] ?? '');
            $estCompletion     = date('Y-m-d H:i:s', strtotime('+7 days'));
            mysqli_query($conn, "INSERT INTO domain_transfers (domain_name, user_id, auth_code, status, transfer_type, estimated_completion)
                                 VALUES ('$domainNameEsc', $userId, '$authCodeEsc', 'pending', 'in', '$estCompletion')");
            logActivity($userId, null, 'transfer_initiated', "Transfer initiated for {$item['domain_name']}");
        }
    }

    $orderStatus = $hasTransfers ? 'processing' : 'completed';
    mysqli_query($conn, "UPDATE orders SET status = '$orderStatus' WHERE id = $orderId");

    // ── Deduct wallet ─────────────────────────────────────────────────────────
    $newWalletBalance = $walletBalance;
    if ($walletAmount > 0) {
        $lockRes = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $userId FOR UPDATE");
        if (!$lockRes) throw new Exception('Could not lock user row');
        $lockRow      = mysqli_fetch_assoc($lockRes);
        $currentBal   = (float) ($lockRow['wallet_balance'] ?? 0.00);
        if ($currentBal < $walletAmount) throw new Exception('Insufficient wallet balance');
        $newWalletBalance = $currentBal - $walletAmount;
        $newBalFmt  = number_format($newWalletBalance, 2, '.', '');
        $walAmtFmt  = number_format($walletAmount, 2, '.', '');
        $curBalFmt  = number_format($currentBal, 2, '.', '');
        $pmEsc      = mysqli_real_escape_string($conn, $paymentMethod);
        $siEsc      = mysqli_real_escape_string($conn, $stripePaymentIntentId);
        $wdDesc     = mysqli_real_escape_string($conn, "Payment for Order #$orderNumber");
        mysqli_query($conn, "UPDATE users SET wallet_balance = $newBalFmt, wallet_updated_at = NOW() WHERE id = $userId");
        mysqli_query($conn, "INSERT INTO wallet_transactions
            (user_id, type, amount, balance_before, balance_after, order_id, description, payment_method, stripe_payment_id, created_at)
            VALUES ($userId, 'debit', $walAmtFmt, $curBalFmt, $newBalFmt, $orderId, '$wdDesc', '$pmEsc', '$siEsc', NOW())");
    }

    // Clear cart
    mysqli_query($conn, "DELETE FROM cart WHERE user_id = $userId");

    mysqli_commit($conn);

    // Billing transaction record (after commit — non-critical)
    $desc        = "Order #$orderNumber";
    if ($discount > 0) $desc .= ' (promo: -$' . number_format($discount, 2) . ')';
    $descEsc     = mysqli_real_escape_string($conn, $desc);
    $pmEscT      = mysqli_real_escape_string($conn, $paymentMethod);
    $walAmtFmt   = number_format($walletAmount, 2, '.', '');
    $siEscT      = mysqli_real_escape_string($conn, $stripePaymentIntentId);
    $totalFmt    = number_format($total, 2, '.', '');
    mysqli_query($conn, "INSERT INTO transactions (user_id, order_id, type, amount, description, payment_method, wallet_amount, stripe_payment_id)
        VALUES ($userId, $orderId, 'order', $totalFmt, '$descEsc', '$pmEscT', $walAmtFmt, '$siEscT')");

    logActivity($userId, null, 'order_placed', "Order #$orderNumber placed via $paymentMethod");

    // Generate invoice HTML
    $invoiceDir  = __DIR__ . '/../invoices';
    if (!is_dir($invoiceDir)) @mkdir($invoiceDir, 0755, true);
    $itemsHtml   = '';
    foreach ($cartItems as $ci) {
        $itemsHtml .= '<tr><td>' . htmlspecialchars($ci['domain_name']) . '</td><td>' . htmlspecialchars($ci['operation_type']) . '</td><td>' . (int) $ci['period'] . '</td><td>$' . number_format($ci['price'], 2) . '</td></tr>';
    }
    $paymentNote = '';
    if ($walletAmount > 0) $paymentNote .= '<p>Wallet: -$' . number_format($walletAmount, 2) . '</p>';
    if ($cardAmount > 0)   $paymentNote .= '<p>Card: $' . number_format($cardAmount, 2) . '</p>';
    $invoiceHtml = '<!doctype html><html><head><meta charset="utf-8"><title>Invoice #' . htmlspecialchars($orderNumber) . '</title>'
        . '<style>body{font-family:Arial,Helvetica,sans-serif;padding:20px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:8px}</style></head><body>'
        . '<h2>Invoice – Order ' . htmlspecialchars($orderNumber) . '</h2>'
        . '<p><strong>Customer:</strong> ' . htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) . '</p>'
        . '<table><thead><tr><th>Domain</th><th>Type</th><th>Period</th><th>Price</th></tr></thead><tbody>' . $itemsHtml . '</tbody></table>'
        . '<h3>Total: $' . number_format($total, 2) . '</h3>' . $paymentNote
        . '</body></html>';
    @file_put_contents($invoiceDir . '/invoice_' . $orderId . '.html', $invoiceHtml);

    $to = $user['email'] ?? null;
    if ($to) {
        $invoicePdfUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_PATH . '/api/invoice_pdf.php?id=' . $orderId;
        @mail($to, "Order Confirmation – $orderNumber",
            "Hi {$user['first_name']},\n\nOrder #{$orderNumber} confirmed.\nInvoice: $invoicePdfUrl\n\nRegards,\nDomainPortal",
            'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'domainportal'));
    }

    jsonResponse(true, 'Order placed', [
        'redirect'       => '/pages/dashboard.php?order_success=1',
        'invoice'        => 'invoice_' . $orderId . '.html',
        'wallet_balance' => $newWalletBalance,
    ]);

} catch (Exception $ex) {
    mysqli_rollback($conn);
    error_log('Checkout error: ' . $ex->getMessage());
    jsonResponse(false, 'Failed to process order: ' . $ex->getMessage());
}

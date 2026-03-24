<?php
/**
 * api/renew.php
 * Renew a domain with payment (wallet, stripe, or mixed).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/stripe.php';
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

$userId    = (int) $user['id'];
$domainId  = (int) ($_POST['domain_id'] ?? 0);
$period    = (int) ($_POST['period'] ?? 1);
$paymentMethod         = $_POST['payment_method'] ?? 'wallet';
$stripePaymentIntentId = trim($_POST['stripe_payment_intent_id'] ?? '');

if (!$domainId) {
    jsonResponse(false, 'Missing domain id');
}
if ($period <= 0 || $period > 10) {
    $period = 1;
}

// Verify domain belongs to user
$stmt = mysqli_prepare($conn, "SELECT id, domain_name, expiry_date FROM domains WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $domainId, $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$domain = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$domain) {
    jsonResponse(false, 'Domain not found');
}

// Calculate renewal price
$pricing      = getTldPricing($domain['domain_name']);
$renewalPrice = (float) $pricing['renewal_price'] * $period;

// Wallet / card split
$walletBalance = (float) ($user['wallet_balance'] ?? 0.00);

if ($paymentMethod === 'wallet') {
    $walletAmount = $renewalPrice;
    $cardAmount   = 0.00;
} elseif ($paymentMethod === 'mixed') {
    $walletAmount = min($walletBalance, $renewalPrice);
    $cardAmount   = max(0.00, $renewalPrice - $walletAmount);
} else {
    $walletAmount = 0.00;
    $cardAmount   = $renewalPrice;
}

// Validate wallet balance
if ($walletAmount > 0 && $walletBalance < $walletAmount) {
    jsonResponse(false, 'Insufficient wallet balance. Need $' . number_format($walletAmount, 2) . ', have $' . number_format($walletBalance, 2));
}

// Verify Stripe payment if card amount > 0
if ($cardAmount > 0) {
    if (!STRIPE_ENABLED) {
        jsonResponse(false, 'Card payments are not configured. Please use wallet or contact support.');
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
    $expectedCents = (int) round($cardAmount * 100);
    $paidCents     = (int) ($pi['amount_received'] ?? $pi['amount'] ?? 0);
    if (abs($paidCents - $expectedCents) > 1) {
        jsonResponse(false, 'Payment amount mismatch. Please contact support.');
    }
}

// Process renewal in a transaction
mysqli_begin_transaction($conn);
try {
    // Update domain expiry
    $currentExpiry = $domain['expiry_date'] ?: date('Y-m-d');
    $newExpiry     = date('Y-m-d', strtotime($currentExpiry . " +{$period} years"));

    $stmt = mysqli_prepare($conn, "UPDATE domains SET expiry_date = ?, expires_at = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $newExpiry, $newExpiry, $domainId);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update domain expiry');
    }
    mysqli_stmt_close($stmt);

    // Create order record
    $orderNumber = generateOrderNumber();
    $orderStatus = 'completed';
    $stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, order_number, total_amount, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, 'isds', $userId, $orderNumber, $renewalPrice, $orderStatus);
    mysqli_stmt_execute($stmt);
    $orderId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Order item
    $opType     = 'renewal';
    $itemStatus = 'completed';
    $apiResp    = json_encode(['renewed' => true]);
    $authCode   = null;
    $stmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, domain_name, operation_type, period, price, auth_code, status, api_response) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'issidsss', $orderId, $domain['domain_name'], $opType, $period, $pricing['renewal_price'], $authCode, $itemStatus, $apiResp);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Deduct wallet if applicable
    $newWalletBalance = $walletBalance;
    if ($walletAmount > 0) {
        $lockRes = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $userId FOR UPDATE");
        if (!$lockRes) throw new Exception('Could not lock user row');
        $lockRow    = mysqli_fetch_assoc($lockRes);
        $currentBal = (float) ($lockRow['wallet_balance'] ?? 0.00);
        if ($currentBal < $walletAmount) throw new Exception('Insufficient wallet balance');

        $newWalletBalance = $currentBal - $walletAmount;
        $newBalFmt  = number_format($newWalletBalance, 2, '.', '');
        $walAmtFmt  = number_format($walletAmount, 2, '.', '');
        $curBalFmt  = number_format($currentBal, 2, '.', '');
        $pmEsc      = mysqli_real_escape_string($conn, $paymentMethod);
        $siEsc      = mysqli_real_escape_string($conn, $stripePaymentIntentId);
        $wdDesc     = mysqli_real_escape_string($conn, "Renewal of {$domain['domain_name']} - Order #$orderNumber");

        mysqli_query($conn, "UPDATE users SET wallet_balance = $newBalFmt, wallet_updated_at = NOW() WHERE id = $userId");
        mysqli_query($conn, "INSERT INTO wallet_transactions
            (user_id, type, amount, balance_before, balance_after, order_id, description, payment_method, stripe_payment_id, created_at)
            VALUES ($userId, 'debit', $walAmtFmt, $curBalFmt, $newBalFmt, $orderId, '$wdDesc', '$pmEsc', '$siEsc', NOW())");
    }

    // Transaction record
    $totalFmt  = number_format($renewalPrice, 2, '.', '');
    $walFmt    = number_format($walletAmount, 2, '.', '');
    $descEsc   = mysqli_real_escape_string($conn, "Renewal: {$domain['domain_name']} ({$period}yr) - Order #$orderNumber");
    $pmEscT    = mysqli_real_escape_string($conn, $paymentMethod);
    $siEscT    = mysqli_real_escape_string($conn, $stripePaymentIntentId);
    mysqli_query($conn, "INSERT INTO transactions (user_id, order_id, type, amount, description, payment_method, wallet_amount, stripe_payment_id)
        VALUES ($userId, $orderId, 'renewal', $totalFmt, '$descEsc', '$pmEscT', $walFmt, '$siEscT')");

    mysqli_commit($conn);

    logActivity($userId, $domainId, 'domain_renewed', "Renewed {$domain['domain_name']} for {$period} year(s) via $paymentMethod");

    jsonResponse(true, 'Domain renewed successfully', [
        'expiry_date'    => $newExpiry,
        'order_number'   => $orderNumber,
        'total_charged'  => $renewalPrice,
        'wallet_used'    => $walletAmount,
        'card_charged'   => $cardAmount,
        'wallet_balance' => $newWalletBalance,
    ]);

} catch (Exception $ex) {
    mysqli_rollback($conn);
    error_log('Renewal error: ' . $ex->getMessage());
    jsonResponse(false, 'Failed to process renewal: ' . $ex->getMessage());
}

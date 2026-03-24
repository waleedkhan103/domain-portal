<?php
/**
 * api/refund.php
 * Process refunds for orders — refunds to Stripe and/or wallet.
 * Admin-only endpoint.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method');
}

// Admin check — allow admin session or user with is_admin flag
$isAdmin = false;
if (!empty($_SESSION['admin_logged_in'])) {
    $isAdmin = true;
} elseif (isLoggedIn()) {
    $user = getCurrentUser();
    if (!empty($user['is_admin'])) {
        $isAdmin = true;
    }
}
if (!$isAdmin) {
    jsonResponse(false, 'Admin access required');
}

$orderId = (int) ($_POST['order_id'] ?? 0);
$reason  = trim($_POST['reason'] ?? 'Refund requested');

if (!$orderId) {
    jsonResponse(false, 'Missing order ID');
}

// Fetch order
$orderRes = mysqli_query($conn, "SELECT * FROM orders WHERE id = $orderId");
if (!$orderRes || mysqli_num_rows($orderRes) === 0) {
    jsonResponse(false, 'Order not found');
}
$order = mysqli_fetch_assoc($orderRes);

if ($order['status'] === 'refunded') {
    jsonResponse(false, 'Order has already been refunded');
}

$orderUserId = (int) $order['user_id'];
$totalAmount = (float) $order['total_amount'];

// Find payment details from transactions
$tRes = mysqli_query($conn, "SELECT * FROM transactions WHERE order_id = $orderId AND type = 'order' LIMIT 1");
$transaction = ($tRes && mysqli_num_rows($tRes) > 0) ? mysqli_fetch_assoc($tRes) : null;

$stripePaymentId = $transaction['stripe_payment_id'] ?? '';
$walletAmount    = (float) ($transaction['wallet_amount'] ?? 0.00);
$cardAmount      = $totalAmount - $walletAmount;

mysqli_begin_transaction($conn);
try {
    // Refund Stripe payment if applicable
    if ($cardAmount > 0 && !empty($stripePaymentId) && STRIPE_ENABLED) {
        $refundCents = (int) round($cardAmount * 100);
        $refundResult = stripeRequest('POST', 'refunds', [
            'payment_intent' => $stripePaymentId,
            'amount'         => $refundCents,
        ]);

        if (isset($refundResult['error'])) {
            throw new Exception('Stripe refund failed: ' . ($refundResult['error']['message'] ?? 'Unknown error'));
        }

        $cardAmtFmt = number_format($cardAmount, 2, '.', '');
        $descEsc    = mysqli_real_escape_string($conn, "Stripe refund for Order #{$order['order_number']}. Reason: $reason");
        $piEsc      = mysqli_real_escape_string($conn, $stripePaymentId);
        mysqli_query($conn, "INSERT INTO transactions (user_id, order_id, type, amount, description, payment_method, stripe_payment_id)
            VALUES ($orderUserId, $orderId, 'refund', $cardAmtFmt, '$descEsc', 'stripe', '$piEsc')");
    }

    // Refund wallet amount if applicable
    if ($walletAmount > 0) {
        $lockRes = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $orderUserId FOR UPDATE");
        if (!$lockRes) throw new Exception('Could not lock user row');
        $lockRow       = mysqli_fetch_assoc($lockRes);
        $balanceBefore = (float) ($lockRow['wallet_balance'] ?? 0.00);
        $balanceAfter  = $balanceBefore + $walletAmount;

        $walFmt    = number_format($walletAmount, 2, '.', '');
        $balBFmt   = number_format($balanceBefore, 2, '.', '');
        $balAFmt   = number_format($balanceAfter, 2, '.', '');
        $descEsc   = mysqli_real_escape_string($conn, "Wallet refund for Order #{$order['order_number']}. Reason: $reason");

        mysqli_query($conn, "UPDATE users SET wallet_balance = $balAFmt, wallet_updated_at = NOW() WHERE id = $orderUserId");
        mysqli_query($conn, "INSERT INTO wallet_transactions
            (user_id, type, amount, balance_before, balance_after, order_id, description, payment_method, created_at)
            VALUES ($orderUserId, 'refund', $walFmt, $balBFmt, $balAFmt, $orderId, '$descEsc', 'wallet_refund', NOW())");

        // Also log in transactions table
        mysqli_query($conn, "INSERT INTO transactions (user_id, order_id, type, amount, description, payment_method, wallet_amount)
            VALUES ($orderUserId, $orderId, 'refund', $walFmt, '$descEsc', 'wallet', $walFmt)");
    }

    // Update order status
    $reasonEsc = mysqli_real_escape_string($conn, $reason);
    mysqli_query($conn, "UPDATE orders SET status = 'refunded' WHERE id = $orderId");

    mysqli_commit($conn);

    $adminId = (int) ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
    logActivity($adminId, null, 'order_refunded', "Refunded Order #{$order['order_number']} (\${$order['total_amount']}). Reason: $reason");

    jsonResponse(true, 'Refund processed successfully', [
        'order_number'    => $order['order_number'],
        'total_refunded'  => $totalAmount,
        'stripe_refunded' => max(0, $cardAmount),
        'wallet_refunded' => $walletAmount,
    ]);

} catch (Exception $ex) {
    mysqli_rollback($conn);
    error_log('Refund error: ' . $ex->getMessage());
    jsonResponse(false, 'Refund failed: ' . $ex->getMessage());
}

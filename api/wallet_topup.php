<?php
/**
 * api/wallet_topup.php
 * Allows users to add funds to their wallet via Stripe card payment.
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

if (!STRIPE_ENABLED) {
    jsonResponse(false, 'Card payments are not configured. Please contact support.');
}

$user   = getCurrentUser();
$userId = (int) $user['id'];
$action = $_POST['action'] ?? '';

// ── Action: Create PaymentIntent for top-up ─────────────────────────────────
if ($action === 'create_intent') {
    $amount = (float) ($_POST['amount'] ?? 0);

    if ($amount < 5.00) {
        jsonResponse(false, 'Minimum top-up amount is $5.00');
    }
    if ($amount > 10000.00) {
        jsonResponse(false, 'Maximum top-up amount is $10,000.00');
    }

    $amountCents = (int) round($amount * 100);
    $currency    = 'usd';

    $result = stripeRequest('POST', 'payment_intents', [
        'amount'                              => $amountCents,
        'currency'                            => $currency,
        'automatic_payment_methods[enabled]'  => 'true',
        'metadata[user_id]'                   => $userId,
        'metadata[type]'                      => 'wallet_topup',
    ]);

    if (isset($result['error'])) {
        jsonResponse(false, $result['error']['message'] ?? 'Stripe error');
    }

    jsonResponse(true, 'PaymentIntent created', [
        'client_secret'     => $result['client_secret'],
        'payment_intent_id' => $result['id'],
    ]);
}

// ── Action: Confirm top-up after Stripe payment succeeded ────────────────────
if ($action === 'confirm_topup') {
    $stripePaymentIntentId = trim($_POST['stripe_payment_intent_id'] ?? '');
    $amount                = (float) ($_POST['amount'] ?? 0);

    if (empty($stripePaymentIntentId)) {
        jsonResponse(false, 'Missing payment intent ID');
    }
    if ($amount < 5.00 || $amount > 10000.00) {
        jsonResponse(false, 'Invalid top-up amount');
    }

    // Verify payment with Stripe
    $pi = stripeRequest('GET', 'payment_intents/' . $stripePaymentIntentId);
    if (isset($pi['error'])) {
        jsonResponse(false, 'Payment verification failed: ' . ($pi['error']['message'] ?? 'Unknown error'));
    }
    if (($pi['status'] ?? '') !== 'succeeded') {
        jsonResponse(false, 'Payment not completed. Status: ' . ($pi['status'] ?? 'unknown'));
    }

    // Verify amount
    $expectedCents = (int) round($amount * 100);
    $paidCents     = (int) ($pi['amount_received'] ?? $pi['amount'] ?? 0);
    if (abs($paidCents - $expectedCents) > 1) {
        jsonResponse(false, 'Payment amount mismatch. Please contact support.');
    }

    // Verify this payment hasn't already been used for a top-up
    $piEsc = mysqli_real_escape_string($conn, $stripePaymentIntentId);
    $dupCheck = mysqli_query($conn, "SELECT id FROM wallet_transactions WHERE stripe_payment_id = '$piEsc' AND type = 'credit' LIMIT 1");
    if ($dupCheck && mysqli_num_rows($dupCheck) > 0) {
        jsonResponse(false, 'This payment has already been applied to your wallet');
    }

    // Credit wallet in a transaction
    mysqli_begin_transaction($conn);
    try {
        $lockRes = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $userId FOR UPDATE");
        if (!$lockRes) throw new Exception('Could not lock user row');
        $lockRow       = mysqli_fetch_assoc($lockRes);
        $balanceBefore = (float) ($lockRow['wallet_balance'] ?? 0.00);
        $balanceAfter  = $balanceBefore + $amount;

        $balAfterFmt = number_format($balanceAfter, 2, '.', '');
        $amountFmt   = number_format($amount, 2, '.', '');
        $balBeforeFmt = number_format($balanceBefore, 2, '.', '');

        mysqli_query($conn, "UPDATE users SET wallet_balance = $balAfterFmt, wallet_updated_at = NOW() WHERE id = $userId");

        $descEsc = mysqli_real_escape_string($conn, "Wallet top-up via card");
        mysqli_query($conn, "INSERT INTO wallet_transactions
            (user_id, type, amount, balance_before, balance_after, description, payment_method, stripe_payment_id, created_at)
            VALUES ($userId, 'credit', $amountFmt, $balBeforeFmt, $balAfterFmt, '$descEsc', 'stripe', '$piEsc', NOW())");

        // Also log in transactions table
        mysqli_query($conn, "INSERT INTO transactions (user_id, type, amount, description, payment_method, stripe_payment_id)
            VALUES ($userId, 'wallet_topup', $amountFmt, '$descEsc', 'stripe', '$piEsc')");

        mysqli_commit($conn);

        logActivity($userId, null, 'wallet_topup', "Added \$$amountFmt to wallet via card");

        jsonResponse(true, 'Wallet topped up successfully', [
            'new_balance' => $balanceAfter,
            'amount'      => $amount,
        ]);

    } catch (Exception $ex) {
        mysqli_rollback($conn);
        error_log('Wallet top-up error: ' . $ex->getMessage());
        jsonResponse(false, 'Failed to credit wallet: ' . $ex->getMessage());
    }
}

jsonResponse(false, 'Invalid action');

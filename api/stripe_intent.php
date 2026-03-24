<?php
/**
 * api/stripe_intent.php
 * Creates a Stripe PaymentIntent for the given amount.
 * The client uses the returned client_secret with Stripe.js to confirm the payment.
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

$amountFloat = (float) ($_POST['amount'] ?? 0);
if ($amountFloat <= 0) {
    jsonResponse(false, 'Invalid amount');
}

// Stripe amounts are in the smallest currency unit (cents for USD)
$amountCents = (int) round($amountFloat * 100);
$currency    = strtolower(trim($_POST['currency'] ?? 'usd'));
$user        = getCurrentUser();

$result = stripeRequest('POST', 'payment_intents', [
    'amount'               => $amountCents,
    'currency'             => $currency,
    'automatic_payment_methods[enabled]' => 'true',
    'metadata[user_id]'    => $user['id'],
]);

if (isset($result['error'])) {
    jsonResponse(false, $result['error']['message'] ?? 'Stripe error');
}

jsonResponse(true, 'PaymentIntent created', [
    'client_secret'       => $result['client_secret'],
    'payment_intent_id'   => $result['id'],
]);

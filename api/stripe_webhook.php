<?php
/**
 * api/stripe_webhook.php
 * Handles Stripe webhook events for payment confirmations, refunds, and disputes.
 *
 * Configure in Stripe Dashboard → Webhooks → Add endpoint:
 *   URL: https://yourdomain.com/api/stripe_webhook.php
 *   Events: payment_intent.succeeded, payment_intent.payment_failed,
 *           charge.refunded, charge.dispute.created
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../includes/functions.php';

// Read raw POST body
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Retrieve webhook secret from settings
$webhookSecret = '';
if (isset($conn) && $conn) {
    $wRes = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'stripe_webhook_secret' LIMIT 1");
    if ($wRes && $row = mysqli_fetch_assoc($wRes)) {
        $webhookSecret = trim($row['setting_value'] ?? '');
    }
}

// Verify webhook signature if secret is configured
if (!empty($webhookSecret) && !empty($sigHeader)) {
    $elements  = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) === 2) {
            $elements[$kv[0]] = $kv[1];
        }
    }
    $timestamp = $elements['t'] ?? '';
    $signature = $elements['v1'] ?? '';

    if (empty($timestamp) || empty($signature)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature header']);
        exit;
    }

    // Verify signature
    $signedPayload   = $timestamp . '.' . $payload;
    $expectedSig     = hash_hmac('sha256', $signedPayload, $webhookSecret);

    if (!hash_equals($expectedSig, $signature)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }

    // Check timestamp tolerance (5 minutes)
    if (abs(time() - (int) $timestamp) > 300) {
        http_response_code(400);
        echo json_encode(['error' => 'Timestamp too old']);
        exit;
    }
}

// Parse event
$event = json_decode($payload, true);
if (!$event || !isset($event['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$eventType = $event['type'];
$object    = $event['data']['object'] ?? [];

// Log webhook event
error_log("Stripe webhook: $eventType | ID: " . ($object['id'] ?? 'unknown'));

switch ($eventType) {

    // ── Payment succeeded ────────────────────────────────────────────────────
    case 'payment_intent.succeeded':
        $piId     = $object['id'] ?? '';
        $amount   = (int) ($object['amount_received'] ?? $object['amount'] ?? 0);
        $userId   = (int) ($object['metadata']['user_id'] ?? 0);
        $type     = $object['metadata']['type'] ?? 'order';

        if ($piId) {
            $piEsc = mysqli_real_escape_string($conn, $piId);
            // Mark any pending orders with this PI as confirmed
            $orderRes = mysqli_query($conn, "SELECT o.id, o.order_number FROM orders o
                JOIN transactions t ON t.order_id = o.id
                WHERE t.stripe_payment_id = '$piEsc' AND o.status = 'processing'");
            if ($orderRes) {
                while ($order = mysqli_fetch_assoc($orderRes)) {
                    mysqli_query($conn, "UPDATE orders SET status = 'completed' WHERE id = " . (int) $order['id']);
                    error_log("Webhook: Order #{$order['order_number']} marked completed via PI $piId");
                }
            }
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        break;

    // ── Payment failed ───────────────────────────────────────────────────────
    case 'payment_intent.payment_failed':
        $piId   = $object['id'] ?? '';
        $error  = $object['last_payment_error']['message'] ?? 'Unknown error';
        $userId = (int) ($object['metadata']['user_id'] ?? 0);

        if ($piId) {
            $piEsc = mysqli_real_escape_string($conn, $piId);
            // Mark orders with this PI as failed
            $orderRes = mysqli_query($conn, "SELECT o.id, o.order_number, o.user_id FROM orders o
                JOIN transactions t ON t.order_id = o.id
                WHERE t.stripe_payment_id = '$piEsc' AND o.status IN ('processing','pending')");
            if ($orderRes) {
                while ($order = mysqli_fetch_assoc($orderRes)) {
                    mysqli_query($conn, "UPDATE orders SET status = 'failed' WHERE id = " . (int) $order['id']);
                    // Refund wallet amount if mixed payment was used
                    refundWalletForOrder($conn, (int) $order['id'], (int) $order['user_id']);
                    error_log("Webhook: Order #{$order['order_number']} marked failed. Error: $error");
                }
            }
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        break;

    // ── Charge refunded ──────────────────────────────────────────────────────
    case 'charge.refunded':
        $chargeId   = $object['id'] ?? '';
        $piId       = $object['payment_intent'] ?? '';
        $refunded   = (int) ($object['amount_refunded'] ?? 0);
        $refundedAmt = $refunded / 100;

        if ($piId) {
            $piEsc = mysqli_real_escape_string($conn, $piId);
            $tRes  = mysqli_query($conn, "SELECT t.user_id, t.order_id FROM transactions t
                WHERE t.stripe_payment_id = '$piEsc' LIMIT 1");
            if ($tRes && $row = mysqli_fetch_assoc($tRes)) {
                $refUserId  = (int) $row['user_id'];
                $refOrderId = (int) $row['order_id'];
                $amtFmt     = number_format($refundedAmt, 2, '.', '');
                $descEsc    = mysqli_real_escape_string($conn, "Stripe refund for charge $chargeId");

                mysqli_query($conn, "INSERT INTO transactions (user_id, order_id, type, amount, description, payment_method, stripe_payment_id)
                    VALUES ($refUserId, $refOrderId, 'refund', $amtFmt, '$descEsc', 'stripe', '$piEsc')");

                if ($refOrderId) {
                    mysqli_query($conn, "UPDATE orders SET status = 'refunded' WHERE id = $refOrderId");
                }

                error_log("Webhook: Refund of \$$amtFmt processed for user #$refUserId, PI $piId");
            }
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        break;

    // ── Dispute created ──────────────────────────────────────────────────────
    case 'charge.dispute.created':
        $chargeId = $object['charge'] ?? '';
        $reason   = $object['reason'] ?? 'unknown';
        $amount   = ((int) ($object['amount'] ?? 0)) / 100;

        error_log("DISPUTE ALERT: Charge $chargeId disputed. Reason: $reason. Amount: \$$amount");

        // Log as a transaction for admin visibility
        if ($chargeId) {
            $chEsc = mysqli_real_escape_string($conn, $chargeId);
            $tRes  = mysqli_query($conn, "SELECT t.user_id, t.order_id FROM transactions t
                WHERE t.stripe_payment_id LIKE '%$chEsc%' LIMIT 1");
            if ($tRes && $row = mysqli_fetch_assoc($tRes)) {
                $amtFmt  = number_format($amount, 2, '.', '');
                $descEsc = mysqli_real_escape_string($conn, "Dispute opened: $reason (Charge: $chargeId)");
                mysqli_query($conn, "INSERT INTO transactions (user_id, order_id, type, amount, description, payment_method)
                    VALUES ({$row['user_id']}, {$row['order_id']}, 'dispute', $amtFmt, '$descEsc', 'stripe')");
            }
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        break;

    // ── Unhandled event ──────────────────────────────────────────────────────
    default:
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'note' => 'Event type not handled']);
        break;
}

/**
 * Refund wallet amount if a mixed payment order fails.
 */
function refundWalletForOrder($conn, int $orderId, int $userId): void
{
    $wtRes = mysqli_query($conn, "SELECT amount FROM wallet_transactions
        WHERE order_id = $orderId AND user_id = $userId AND type = 'debit' LIMIT 1");
    if (!$wtRes || mysqli_num_rows($wtRes) === 0) return;

    $wt        = mysqli_fetch_assoc($wtRes);
    $refundAmt = (float) $wt['amount'];
    if ($refundAmt <= 0) return;

    $lockRes = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $userId FOR UPDATE");
    if (!$lockRes) return;
    $lockRow       = mysqli_fetch_assoc($lockRes);
    $balanceBefore = (float) ($lockRow['wallet_balance'] ?? 0.00);
    $balanceAfter  = $balanceBefore + $refundAmt;

    $amtFmt    = number_format($refundAmt, 2, '.', '');
    $balBFmt   = number_format($balanceBefore, 2, '.', '');
    $balAFmt   = number_format($balanceAfter, 2, '.', '');
    $descEsc   = mysqli_real_escape_string($conn, "Refund for failed order #$orderId");

    mysqli_query($conn, "UPDATE users SET wallet_balance = $balAFmt, wallet_updated_at = NOW() WHERE id = $userId");
    mysqli_query($conn, "INSERT INTO wallet_transactions
        (user_id, type, amount, balance_before, balance_after, order_id, description, payment_method, created_at)
        VALUES ($userId, 'refund', $amtFmt, $balBFmt, $balAFmt, $orderId, '$descEsc', 'wallet_refund', NOW())");
}

<?php
/**
 * admin/api/wallet_credit.php
 * Allows an admin to manually add funds to a user's wallet.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$targetUserId = (int) ($_POST['user_id'] ?? 0);
$amount       = (float) ($_POST['amount'] ?? 0);
$description  = trim($_POST['description'] ?? '');
$adminId      = (int) ($_SESSION['admin_id'] ?? 0);

if ($targetUserId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}
if ($amount <= 0 || $amount > 99999.99) {
    echo json_encode(['success' => false, 'message' => 'Amount must be between $0.01 and $99,999.99']);
    exit;
}
if (empty($description)) {
    $description = 'Manual credit by admin';
}

// Ensure wallet_transactions table exists
$createWalletTable = "CREATE TABLE IF NOT EXISTS wallet_transactions (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NOT NULL,
    type              ENUM('credit','debit','refund') NOT NULL,
    amount            DECIMAL(10,2) NOT NULL,
    balance_before    DECIMAL(10,2) NOT NULL,
    balance_after     DECIMAL(10,2) NOT NULL,
    order_id          INT DEFAULT NULL,
    description       VARCHAR(255) NOT NULL,
    added_by          INT DEFAULT NULL,
    payment_method    VARCHAR(50) DEFAULT NULL,
    stripe_payment_id VARCHAR(255) DEFAULT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_order (order_id)
)";
mysqli_query($conn, $createWalletTable);

// Ensure wallet_balance column exists on users
$colChk = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'wallet_balance'");
if ($colChk && mysqli_num_rows($colChk) === 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00");
}

// Run inside a transaction with row-level lock to prevent race conditions
mysqli_begin_transaction($conn);
try {
    // Lock the user row
    $lockRes = mysqli_query($conn, "SELECT wallet_balance FROM users WHERE id = $targetUserId FOR UPDATE");
    if (!$lockRes || mysqli_num_rows($lockRes) === 0) {
        throw new Exception('User not found');
    }
    $userRow = mysqli_fetch_assoc($lockRes);
    $balanceBefore = (float) ($userRow['wallet_balance'] ?? 0.00);
    $balanceAfter  = $balanceBefore + $amount;

    // Update user balance
    $amtEsc = number_format($amount, 2, '.', '');
    $balAfterEsc = number_format($balanceAfter, 2, '.', '');
    if (!mysqli_query($conn, "UPDATE users SET wallet_balance = $balAfterEsc, wallet_updated_at = NOW() WHERE id = $targetUserId")) {
        throw new Exception('Failed to update wallet balance');
    }

    // Insert wallet transaction record
    $descEsc = mysqli_real_escape_string($conn, $description);
    $balBefore = number_format($balanceBefore, 2, '.', '');
    $insertSql = "INSERT INTO wallet_transactions
        (user_id, type, amount, balance_before, balance_after, description, added_by, payment_method, created_at)
        VALUES ($targetUserId, 'credit', $amtEsc, $balBefore, $balAfterEsc, '$descEsc', $adminId, 'admin_credit', NOW())";
    if (!mysqli_query($conn, $insertSql)) {
        throw new Exception('Failed to record wallet transaction');
    }

    mysqli_commit($conn);

    logActivity($adminId, null, 'wallet_credit', "Added \$$amtEsc to user #$targetUserId wallet. Balance: \$$balBefore → \$$balAfterEsc. Note: $description");

    echo json_encode([
        'success'     => true,
        'message'     => 'Wallet credited successfully',
        'new_balance' => $balanceAfter,
        'amount'      => $amount,
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log('wallet_credit error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

<?php
/**
 * admin/api/wallet_history.php
 * Returns paginated wallet transaction history for a user (admin only).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdmin();

$targetUserId = (int) ($_GET['user_id'] ?? 0);
$page         = max(1, (int) ($_GET['page'] ?? 1));
$per          = 10;
$offset       = ($page - 1) * $per;

if ($targetUserId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Return empty gracefully if table doesn't exist yet
$chk = mysqli_query($conn, "SHOW TABLES LIKE 'wallet_transactions'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    echo json_encode(['success' => true, 'transactions' => [], 'total' => 0, 'balance' => 0.00]);
    exit;
}

// Get current balance
$balRes = mysqli_query($conn, "SELECT COALESCE(wallet_balance, 0) as bal FROM users WHERE id = $targetUserId LIMIT 1");
$balance = 0.00;
if ($balRes && $row = mysqli_fetch_assoc($balRes)) {
    $balance = (float) $row['bal'];
}

// Count total records
$cntRes = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM wallet_transactions WHERE user_id = $targetUserId");
$total  = 0;
if ($cntRes && $row = mysqli_fetch_assoc($cntRes)) {
    $total = (int) $row['cnt'];
}

// Fetch records
$sql = "SELECT wt.id, wt.type, wt.amount, wt.balance_before, wt.balance_after,
               wt.description, wt.payment_method, wt.created_at,
               wt.order_id, wt.added_by,
               COALESCE(CONCAT(u.first_name,' ',u.last_name), u.email, 'System') as added_by_name
        FROM wallet_transactions wt
        LEFT JOIN users u ON u.id = wt.added_by
        WHERE wt.user_id = $targetUserId
        ORDER BY wt.created_at DESC
        LIMIT $per OFFSET $offset";

$res   = mysqli_query($conn, $sql);
$rows  = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

echo json_encode([
    'success'      => true,
    'balance'      => $balance,
    'total'        => $total,
    'page'         => $page,
    'per'          => $per,
    'transactions' => $rows,
]);

<?php
/**
 * api/wallet_balance.php
 * Returns the current wallet balance for the logged-in user.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Not authenticated');
}

$user = getCurrentUser();
if (!$user) {
    jsonResponse(false, 'User not found');
}

$balance = (float) ($user['wallet_balance'] ?? 0.00);

jsonResponse(true, 'OK', ['balance' => $balance]);

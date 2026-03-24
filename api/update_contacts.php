<?php
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method');
}

if (!isLoggedIn()) {
    jsonResponse(false, 'Not authenticated');
}

$user = getCurrentUser();
$action = $_POST['action'] ?? '';

if ($action !== 'update_contacts') {
    jsonResponse(false, 'Invalid action');
}

$domainId = (int) ($_POST['domain_id'] ?? 0);

// Verify domain ownership
$sql = "SELECT * FROM domains WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $domainId, $user['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$domain = mysqli_fetch_assoc($result);

if (!$domain) {
    jsonResponse(false, 'Domain not found or access denied');
}

// Get old registrant email to check if it changed
$oldRegistrantEmail = $domain['registrant_email'] ?? '';

// Collect and sanitize contact data
$contacts = [
    'registrant' => [
        'first' => trim($_POST['registrant_first'] ?? ''),
        'last' => trim($_POST['registrant_last'] ?? ''),
        'email' => trim($_POST['registrant_email'] ?? ''),
        'phone' => trim($_POST['registrant_phone'] ?? ''),
        'address' => trim($_POST['registrant_address'] ?? '')
    ],
    'admin' => [
        'first' => trim($_POST['admin_first'] ?? ''),
        'last' => trim($_POST['admin_last'] ?? ''),
        'email' => trim($_POST['admin_email'] ?? ''),
        'phone' => trim($_POST['admin_phone'] ?? ''),
        'address' => trim($_POST['admin_address'] ?? '')
    ],
    'tech' => [
        'first' => trim($_POST['tech_first'] ?? ''),
        'last' => trim($_POST['tech_last'] ?? ''),
        'email' => trim($_POST['tech_email'] ?? ''),
        'phone' => trim($_POST['tech_phone'] ?? ''),
        'address' => trim($_POST['tech_address'] ?? '')
    ],
    'billing' => [
        'first' => trim($_POST['billing_first'] ?? ''),
        'last' => trim($_POST['billing_last'] ?? ''),
        'email' => trim($_POST['billing_email'] ?? ''),
        'phone' => trim($_POST['billing_phone'] ?? ''),
        'address' => trim($_POST['billing_address'] ?? '')
    ]
];

// Validation
$errors = [];
foreach ($contacts as $type => $data) {
    if (empty($data['first'])) $errors[] = ucfirst($type) . " first name is required";
    if (empty($data['last'])) $errors[] = ucfirst($type) . " last name is required";
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = ucfirst($type) . " email is invalid";
    }
    if (empty($data['phone'])) $errors[] = ucfirst($type) . " phone is required";
}

if (!empty($errors)) {
    jsonResponse(false, 'Validation failed: ' . implode(', ', $errors));
}

// Check if registrant email changed (triggers 60-day transfer lock)
$registrantEmailChanged = false;
if (strcasecmp($oldRegistrantEmail, $contacts['registrant']['email']) !== 0) {
    $registrantEmailChanged = true;
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Update domain contacts
    $sql = "UPDATE domains SET
        registrant_first = ?,
        registrant_last = ?,
        registrant_email = ?,
        registrant_phone = ?,
        registrant_address = ?,
        updated_at = NOW()
        WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'sssssi',
        $contacts['registrant']['first'],
        $contacts['registrant']['last'],
        $contacts['registrant']['email'],
        $contacts['registrant']['phone'],
        $contacts['registrant']['address'],
        $domainId
    );
    mysqli_stmt_execute($stmt);

    // If registrant email changed, apply 60-day transfer lock
    if ($registrantEmailChanged) {
        $lockUntil = date('Y-m-d H:i:s', strtotime('+60 days'));

        // Check if transfer_locks table exists, if not create it
        $createLockTable = "CREATE TABLE IF NOT EXISTS transfer_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_id INT NOT NULL,
            locked_until DATETIME NOT NULL,
            reason VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
            INDEX idx_domain_lock (domain_id, locked_until)
        )";
        mysqli_query($conn, $createLockTable);

        // Insert or update transfer lock
        $lockSql = "INSERT INTO transfer_locks (domain_id, locked_until, reason)
                    VALUES (?, ?, 'Registrant email changed')
                    ON DUPLICATE KEY UPDATE
                    locked_until = VALUES(locked_until),
                    reason = VALUES(reason)";

        $lockStmt = mysqli_prepare($conn, $lockSql);
        mysqli_stmt_bind_param($lockStmt, 'is', $domainId, $lockUntil);
        mysqli_stmt_execute($lockStmt);

        error_log("[Contact Update] 60-day transfer lock applied to domain ID $domainId until $lockUntil");
    }

    // Log activity
    $changeDetails = "Updated contacts for " . $domain['domain_name'];
    if ($registrantEmailChanged) {
        $changeDetails .= " (Registrant email changed: 60-day transfer lock applied)";
    }

    logActivity($user['id'], $domainId, 'contacts_updated', $changeDetails);

    // Commit transaction
    mysqli_commit($conn);

    $message = 'Domain contacts updated successfully.';
    if ($registrantEmailChanged) {
        $message .= ' A 60-day transfer lock has been applied due to registrant email change.';
    }

    jsonResponse(true, $message, [
        'transfer_locked' => $registrantEmailChanged,
        'lock_until' => $registrantEmailChanged ? $lockUntil : null
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log('Contact update error: ' . $e->getMessage());
    jsonResponse(false, 'Failed to update contacts: ' . $e->getMessage());
}
?>

<?php
/**
 * Save Settings API
 * Updates multiple settings at once
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAdmin();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['settings'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Verify CSRF token
if (!isset($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$settings = $data['settings'];
$updated = 0;
$failed = 0;

$chk = mysqli_query($conn, "SHOW TABLES LIKE 'settings'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    echo json_encode(['success' => false, 'message' => 'Settings table not found. Please run setup_settings_table.php first.']);
    exit;
}

$stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query prepare failed: ' . $conn->error]);
    exit;
}

foreach ($settings as $key => $value) {
    // Sanitize value
    $value = is_array($value) ? json_encode($value) : $value;

    $stmt->bind_param('ss', $value, $key);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $updated++;
    } else {
        $failed++;
    }
}

$stmt->close();

echo json_encode([
    'success' => true,
    'message' => "Updated $updated setting(s)",
    'updated' => $updated,
    'failed' => $failed,
    'csrf_token' => generateCSRFToken()
]);

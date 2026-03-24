<?php
/**
 * Get Settings API
 * Returns all settings grouped by category
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

$group = $_GET['group'] ?? 'all';

// Return empty settings gracefully if the table doesn't exist yet
$chk = mysqli_query($conn, "SHOW TABLES LIKE 'settings'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    echo json_encode(['success' => true, 'settings' => []]);
    exit;
}

if ($group === 'all') {
    $sql = "SELECT setting_key, setting_value, setting_group, description FROM settings ORDER BY setting_group, setting_key";
    $result = mysqli_query($conn, $sql);
} else {
    $stmt = $conn->prepare("SELECT setting_key, setting_value, setting_group, description FROM settings WHERE setting_group = ? ORDER BY setting_key");
    if ($stmt) {
        $stmt->bind_param('s', $group);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
    }
}

$settings = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'group' => $row['setting_group'],
            'description' => $row['description']
        ];
    }
}

echo json_encode([
    'success' => true,
    'settings' => $settings
]);

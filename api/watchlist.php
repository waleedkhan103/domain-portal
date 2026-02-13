<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
  jsonResponse(false, 'Please login first');
}

// Create table if not exists
$create = "CREATE TABLE IF NOT EXISTS domain_watchlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  domain_name VARCHAR(255) NOT NULL,
  alert_days_before INT DEFAULT 30,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_domain (user_id, domain_name)
)";
mysqli_query($conn, $create);

$action = $_POST['action'] ?? '';
$userId = (int) $_SESSION['user_id'];

switch ($action) {
  case 'add':
    $domain = trim($_POST['domain'] ?? '');
    $days = (int) ($_POST['alert_days'] ?? 30);
    if (empty($domain)) {
      jsonResponse(false, 'Domain is required');
    }
    $domain = mysqli_real_escape_string($conn, strtolower($domain));
    $sql = "INSERT IGNORE INTO domain_watchlist (user_id, domain_name, alert_days_before) VALUES ($userId, '$domain', $days)";
    if (mysqli_query($conn, $sql) && mysqli_affected_rows($conn) > 0) {
      jsonResponse(true, 'Domain added to watchlist');
    } else {
      jsonResponse(false, 'Domain already in watchlist');
    }
    break;

  case 'remove':
    $id = (int) ($_POST['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM domain_watchlist WHERE id = $id AND user_id = $userId");
    jsonResponse(true, 'Removed from watchlist');
    break;

  case 'list':
    $res = mysqli_query($conn, "SELECT * FROM domain_watchlist WHERE user_id = $userId ORDER BY created_at DESC");
    $list = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    jsonResponse(true, 'Watchlist loaded', ['items' => $list]);
    break;

  default:
    jsonResponse(false, 'Invalid action');
}

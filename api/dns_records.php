<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
  jsonResponse(false, 'Please login first');
}

// Create dns_records table if not exists
$create = "CREATE TABLE IF NOT EXISTS dns_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NOT NULL,
  record_type VARCHAR(10) NOT NULL,
  host VARCHAR(255) NOT NULL,
  value TEXT NOT NULL,
  ttl INT DEFAULT 3600,
  priority INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_domain (domain_id)
)";
mysqli_query($conn, $create);

$action = $_POST['action'] ?? '';
$userId = (int) $_SESSION['user_id'];

// Verify domain ownership
function verifyDomain($conn, $domainId, $userId) {
  $sql = "SELECT d.* FROM domains d WHERE d.id = " . (int) $domainId . " AND d.user_id = $userId LIMIT 1";
  $r = mysqli_query($conn, $sql);
  return ($r && mysqli_num_rows($r) > 0) ? mysqli_fetch_assoc($r) : null;
}

switch ($action) {
  case 'list':
    $domainId = (int) ($_POST['domain_id'] ?? 0);
    if (!verifyDomain($conn, $domainId, $userId)) {
      jsonResponse(false, 'Domain not found');
    }
    $res = mysqli_query($conn, "SELECT * FROM dns_records WHERE domain_id = $domainId ORDER BY record_type, host");
    $records = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    jsonResponse(true, 'Records loaded', ['records' => $records]);
    break;

  case 'add':
    $domainId = (int) ($_POST['domain_id'] ?? 0);
    $type = strtoupper(trim($_POST['record_type'] ?? ''));
    $host = trim($_POST['host'] ?? '');
    $value = trim($_POST['value'] ?? '');
    $ttl = (int) ($_POST['ttl'] ?? 3600);
    $priority = $_POST['priority'] ?? null;

    if (!verifyDomain($conn, $domainId, $userId)) {
      jsonResponse(false, 'Domain not found');
    }
    if (!in_array($type, ['A', 'AAAA', 'CNAME', 'MX', 'TXT'])) {
      jsonResponse(false, 'Invalid record type');
    }
    if (empty($host) || empty($value)) {
      jsonResponse(false, 'Host and value are required');
    }
    if ($type === 'MX' && $priority === null) {
      $priority = 10;
    }

    $type = mysqli_real_escape_string($conn, $type);
    $host = mysqli_real_escape_string($conn, $host);
    $value = mysqli_real_escape_string($conn, $value);
    $prioritySql = ($priority !== null && $priority !== '') ? (int) $priority : 'NULL';
    $sql = "INSERT INTO dns_records (domain_id, record_type, host, value, ttl, priority) VALUES ($domainId, '$type', '$host', '$value', $ttl, $prioritySql)";
    if (mysqli_query($conn, $sql)) {
      jsonResponse(true, 'Record added', ['id' => mysqli_insert_id($conn)]);
    } else {
      jsonResponse(false, 'Failed to add record');
    }
    break;

  case 'delete':
    $id = (int) ($_POST['id'] ?? 0);
    $domainId = (int) ($_POST['domain_id'] ?? 0);
    if (!verifyDomain($conn, $domainId, $userId)) {
      jsonResponse(false, 'Domain not found');
    }
    mysqli_query($conn, "DELETE FROM dns_records WHERE id = $id AND domain_id = $domainId");
    jsonResponse(true, 'Record deleted');
    break;

  default:
    jsonResponse(false, 'Invalid action');
}

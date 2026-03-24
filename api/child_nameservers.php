<?php
/**
 * api/child_nameservers.php
 * Manages child nameservers (glue records) for domains
 */

session_start();

// Prevent HTML output
while (ob_get_level() > 0) {
  ob_end_clean();
}

ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Error handlers
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  jsonResponse(false, "Error [$errno]: $errstr");
});

register_shutdown_function(function () {
  $error = error_get_last();
  if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE])) {
    jsonResponse(false, "Fatal: " . $error['message']);
  }
});

try {
  require_once __DIR__ . '/../config/database.php';
  require_once __DIR__ . '/../includes/functions.php';
} catch (Exception $e) {
  jsonResponse(false, "Include error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'POST method required');
}

if (!isLoggedIn()) {
  jsonResponse(false, 'Authentication required');
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$domainId = (int) ($_POST['domain_id'] ?? 0);

// Verify domain belongs to user
if ($domainId > 0) {
  $verifySql = "SELECT id, domain_name FROM domains WHERE id = $domainId AND user_id = $userId LIMIT 1";
  $verifyResult = mysqli_query($conn, $verifySql);

  if (!$verifyResult || mysqli_num_rows($verifyResult) === 0) {
    jsonResponse(false, 'Domain not found or access denied');
  }

  $domain = mysqli_fetch_assoc($verifyResult);
}

// Create table if not exists
$createTable = "CREATE TABLE IF NOT EXISTS child_nameservers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NOT NULL,
  hostname VARCHAR(255) NOT NULL,
  ipv4_address VARCHAR(45) NULL,
  ipv6_address VARCHAR(45) NULL,
  status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_hostname_per_domain (domain_id, hostname),
  INDEX idx_domain_id (domain_id),
  FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
)";
mysqli_query($conn, $createTable);

switch ($action) {

  case 'list':
    $sql = "SELECT * FROM child_nameservers WHERE domain_id = $domainId ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);

    $nameservers = [];
    while ($row = mysqli_fetch_assoc($result)) {
      $nameservers[] = [
        'id' => (int) $row['id'],
        'hostname' => $row['hostname'],
        'ipv4_address' => $row['ipv4_address'],
        'ipv6_address' => $row['ipv6_address'],
        'status' => $row['status'],
        'created_at' => $row['created_at']
      ];
    }

    jsonResponse(true, 'Child nameservers retrieved', $nameservers);
    break;

  case 'add':
    $hostname = clean($_POST['hostname'] ?? '');
    $ipv4 = clean($_POST['ipv4_address'] ?? '');
    $ipv6 = clean($_POST['ipv6_address'] ?? '');

    if (empty($hostname)) {
      jsonResponse(false, 'Hostname is required');
    }

    if (empty($ipv4)) {
      jsonResponse(false, 'IPv4 address is required');
    }

    // Validate hostname format
    if (!preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $hostname)) {
      jsonResponse(false, 'Invalid hostname format');
    }

    // Validate IPv4 format
    if (!filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      jsonResponse(false, 'Invalid IPv4 address');
    }

    // Validate IPv6 if provided
    if (!empty($ipv6) && !filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      jsonResponse(false, 'Invalid IPv6 address');
    }

    // Check if hostname is within the domain
    $domainName = $domain['domain_name'];
    if (!str_ends_with(strtolower($hostname), '.' . strtolower($domainName))) {
      jsonResponse(false, "Child nameserver must be a subdomain of {$domainName}");
    }

    // Insert child nameserver
    $hostnameEsc = mysqli_real_escape_string($conn, $hostname);
    $ipv4Esc = mysqli_real_escape_string($conn, $ipv4);
    $ipv6Esc = !empty($ipv6) ? "'" . mysqli_real_escape_string($conn, $ipv6) . "'" : 'NULL';

    $sql = "INSERT INTO child_nameservers (domain_id, hostname, ipv4_address, ipv6_address, status)
            VALUES ($domainId, '$hostnameEsc', '$ipv4Esc', $ipv6Esc, 'active')";

    if (mysqli_query($conn, $sql)) {
      $nsId = mysqli_insert_id($conn);
      logActivity($userId, $domainId, 'child_ns_add', "Added child nameserver: $hostname");
      jsonResponse(true, 'Child nameserver added successfully', ['id' => $nsId]);
    } else {
      $error = mysqli_error($conn);
      if (strpos($error, 'Duplicate') !== false) {
        jsonResponse(false, 'This hostname already exists for this domain');
      } else {
        jsonResponse(false, 'Failed to add child nameserver: ' . $error);
      }
    }
    break;

  case 'delete':
    $nsId = (int) ($_POST['id'] ?? 0);

    if ($nsId <= 0) {
      jsonResponse(false, 'Invalid nameserver ID');
    }

    // Verify nameserver belongs to this domain
    $checkSql = "SELECT id, hostname FROM child_nameservers WHERE id = $nsId AND domain_id = $domainId LIMIT 1";
    $checkResult = mysqli_query($conn, $checkSql);

    if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
      jsonResponse(false, 'Child nameserver not found');
    }

    $ns = mysqli_fetch_assoc($checkResult);

    // Delete nameserver
    $deleteSql = "DELETE FROM child_nameservers WHERE id = $nsId";

    if (mysqli_query($conn, $deleteSql)) {
      logActivity($userId, $domainId, 'child_ns_delete', "Deleted child nameserver: {$ns['hostname']}");
      jsonResponse(true, 'Child nameserver deleted successfully');
    } else {
      jsonResponse(false, 'Failed to delete child nameserver: ' . mysqli_error($conn));
    }
    break;

  case 'update':
    $nsId = (int) ($_POST['id'] ?? 0);
    $ipv4 = clean($_POST['ipv4_address'] ?? '');
    $ipv6 = clean($_POST['ipv6_address'] ?? '');

    if ($nsId <= 0) {
      jsonResponse(false, 'Invalid nameserver ID');
    }

    if (empty($ipv4)) {
      jsonResponse(false, 'IPv4 address is required');
    }

    // Validate IP addresses
    if (!filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      jsonResponse(false, 'Invalid IPv4 address');
    }

    if (!empty($ipv6) && !filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      jsonResponse(false, 'Invalid IPv6 address');
    }

    // Verify nameserver belongs to this domain
    $checkSql = "SELECT id, hostname FROM child_nameservers WHERE id = $nsId AND domain_id = $domainId LIMIT 1";
    $checkResult = mysqli_query($conn, $checkSql);

    if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
      jsonResponse(false, 'Child nameserver not found');
    }

    $ns = mysqli_fetch_assoc($checkResult);

    // Update nameserver
    $ipv4Esc = mysqli_real_escape_string($conn, $ipv4);
    $ipv6Esc = !empty($ipv6) ? "'" . mysqli_real_escape_string($conn, $ipv6) . "'" : 'NULL';

    $updateSql = "UPDATE child_nameservers
                  SET ipv4_address = '$ipv4Esc', ipv6_address = $ipv6Esc
                  WHERE id = $nsId";

    if (mysqli_query($conn, $updateSql)) {
      logActivity($userId, $domainId, 'child_ns_update', "Updated child nameserver: {$ns['hostname']}");
      jsonResponse(true, 'Child nameserver updated successfully');
    } else {
      jsonResponse(false, 'Failed to update child nameserver: ' . mysqli_error($conn));
    }
    break;

  default:
    jsonResponse(false, 'Invalid action');
}

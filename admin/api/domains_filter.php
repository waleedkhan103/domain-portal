<?php
// Admin JSON endpoint for domains filtering, search and pagination
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

global $conn;

// Get parameters (allow GET or POST)
$search = $_GET['search'] ?? $_POST['search'] ?? '';
$status = $_GET['status'] ?? $_POST['status'] ?? 'all';
$page = isset($_GET['page']) ? (int) $_GET['page'] : (isset($_POST['page']) ? (int) $_POST['page'] : 1);
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : (isset($_POST['per_page']) ? (int) $_POST['per_page'] : 25);
$owner_only = isset($_GET['owner_only']) ? $_GET['owner_only'] : (isset($_POST['owner_only']) ? $_POST['owner_only'] : null);

if ($per_page <= 0)
  $per_page = 25;
if ($per_page > 100)
  $per_page = 100;
if ($page <= 0)
  $page = 1;

$offset = ($page - 1) * $per_page;

// Owner toggle: if explicitly provided save to session, else read saved
if ($owner_only !== null) {
  // normalize truthy
  $_SESSION['domains_owner_only'] = ($owner_only === '1' || $owner_only === 1 || $owner_only === true || $owner_only === 'true');
}
$ownerOnlySession = $_SESSION['domains_owner_only'] ?? false;
if ($owner_only === null) {
  $owner_only = $ownerOnlySession;
} else {
  $owner_only = ($owner_only === '1' || $owner_only === 1 || $owner_only === true || $owner_only === 'true');
}

$params = [];
$types = '';
$wheres = [];

// Search across domain_name, user email, registrant name
if ($search !== '') {
  $like = '%' . $search . '%';
  $wheres[] = "(d.domain_name LIKE ? OR u.email LIKE ? OR CONCAT_WS(' ', d.registrant_first, d.registrant_last) LIKE ? )";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= 'sss';
}

// Status filter
if (!empty($status) && strtolower($status) !== 'all') {
  $wheres[] = 'd.status = ?';
  $params[] = $status;
  $types .= 's';
}

// Owner-only filter if domains.admin_id exists
$ownerFilterActive = false;
$check = mysqli_query($conn, "SHOW COLUMNS FROM domains LIKE 'admin_id'");
if ($check && mysqli_num_rows($check) > 0 && $owner_only) {
  $wheres[] = 'd.admin_id = ?';
  $params[] = $_SESSION['admin_id'] ?? 0;
  $types .= 'i';
  $ownerFilterActive = true;
}

$whereSql = '';
if (count($wheres) > 0) {
  $whereSql = 'WHERE ' . implode(' AND ', $wheres);
}

// Count total
$total = 0;
if ($countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM domains d LEFT JOIN users u ON u.id = d.user_id $whereSql")) {
  if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
  }
  $countStmt->execute();
  $cr = $countStmt->get_result();
  $row = $cr->fetch_assoc();
  $total = (int) $row['cnt'];
  $countStmt->close();
} else {
  // fallback
  $cntSql = "SELECT COUNT(*) as cnt FROM domains d LEFT JOIN users u ON u.id = d.user_id $whereSql";
  $resCnt = mysqli_query($conn, $cntSql);
  $row = mysqli_fetch_assoc($resCnt);
  $total = (int) $row['cnt'];
}

// Fetch paginated rows
$rows = [];
$dataSql = "SELECT d.id, d.domain_name, d.user_id, d.status, d.expires_at, u.email, d.admin_id FROM domains d LEFT JOIN users u ON u.id = d.user_id $whereSql ORDER BY d.expires_at ASC LIMIT ? OFFSET ?";
// prepare types for data stmt
$dataTypes = $types . 'ii';
$dataParams = $params;
$dataParams[] = $per_page;
$dataParams[] = $offset;

if ($stmt = $conn->prepare($dataSql)) {
  if ($dataTypes !== '') {
    // bind dynamically
    $stmt->bind_param($dataTypes, ...$dataParams);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
  }
  $stmt->close();
} else {
  // fallback raw with escaped values (basic)
  $sql = $dataSql;
  $res = mysqli_query($conn, $sql);
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = $r;
  }
}

// Status counts (all statuses, respect owner filter if active)
$counts = [];
$countsSql = "SELECT d.status, COUNT(*) as cnt FROM domains d";
if ($ownerFilterActive) {
  $countsSql .= " WHERE d.admin_id = " . intval($_SESSION['admin_id'] ?? 0);
}
$countsSql .= " GROUP BY d.status";
$resCounts = mysqli_query($conn, $countsSql);
while ($cr = mysqli_fetch_assoc($resCounts)) {
  $counts[$cr['status']] = (int) $cr['cnt'];
}

// Response
echo json_encode([
  'success' => true,
  'data' => [
    'rows' => $rows,
    'page' => $page,
    'per_page' => $per_page,
    'total' => $total,
    'pages' => $per_page > 0 ? ceil($total / $per_page) : 1,
    'counts' => $counts,
    'owner_only' => $owner_only,
  ]
]);
exit;

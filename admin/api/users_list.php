<?php
if (session_status() !== PHP_SESSION_ACTIVE)
  session_start();
header('Content-Type: application/json; charset=utf-8');
// enable errors for debugging API
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

// Helper to return JSON error and log details for debugging
function apiError($message = 'API Error', $context = [])
{
  global $conn;
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  $payload = ['success' => false, 'message' => $message, 'error' => $context];
  echo json_encode($payload);

  // ensure tmp dir
  $logDir = __DIR__ . '/../tmp';
  if (!is_dir($logDir))
    @mkdir($logDir, 0755, true);
  $logFile = $logDir . '/users_list_error.log';
  $logEntry = '[' . date('c') . '] ' . $message . ' | ' . json_encode($context) . ' | mysqli_error: ' . (isset($conn) && function_exists('mysqli_error') ? mysqli_error($conn) : '') . "\n";
  @file_put_contents($logFile, $logEntry, FILE_APPEND);
  exit;
}

try {
  $q = trim($_GET['q'] ?? '');
  $role = $_GET['role'] ?? 'all';
  $status = $_GET['status'] ?? 'all';
  $dateFrom = $_GET['date_from'] ?? '';
  $dateTo = $_GET['date_to'] ?? '';
  $page = max(1, (int) ($_GET['page'] ?? 1));
  $per = max(10, min(200, (int) ($_GET['per'] ?? 25)));
  $offset = ($page - 1) * $per;
  $sort = in_array($_GET['sort'] ?? '', ['name', 'email', 'domains', 'spent', 'created_at']) ? $_GET['sort'] : 'created_at';
  $dir = (strtolower($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
  // inspect available columns on users table to avoid referencing missing fields
  $availableCols = [];
  $colsRes = mysqli_query($conn, "SHOW COLUMNS FROM users");
  if ($colsRes) {
    while ($col = mysqli_fetch_assoc($colsRes)) {
      $availableCols[$col['Field']] = true;
    }
  }

  // build WHERE clause with escaping, only using existing columns
  $whereParts = [];
  if ($q !== '') {
    $esc = mysqli_real_escape_string($conn, $q);
    $searchParts = [];
    if (!empty($availableCols['first_name']) && !empty($availableCols['last_name'])) {
      $searchParts[] = "CONCAT(first_name,' ',last_name) LIKE '%$esc%'";
    } else {
      if (!empty($availableCols['first_name']))
        $searchParts[] = "first_name LIKE '%$esc%'";
      if (!empty($availableCols['last_name']))
        $searchParts[] = "last_name LIKE '%$esc%'";
    }
    if (!empty($availableCols['email']))
      $searchParts[] = "email LIKE '%$esc%'";
    if (!empty($availableCols['username']))
      $searchParts[] = "username LIKE '%$esc%'";
    if (count($searchParts))
      $whereParts[] = '(' . implode(' OR ', $searchParts) . ')';
  }
  if ($role === 'admins' && !empty($availableCols['is_admin']))
    $whereParts[] = "is_admin = 1";
  if ($role === 'regular' && !empty($availableCols['is_admin']))
    $whereParts[] = "(is_admin = 0 OR is_admin IS NULL)";
  if ($status !== 'all' && !empty($availableCols['status']))
    $whereParts[] = "status = '" . mysqli_real_escape_string($conn, $status) . "'";
  if ($dateFrom && !empty($availableCols['created_at']))
    $whereParts[] = "created_at >= '" . mysqli_real_escape_string($conn, $dateFrom . ' 00:00:00') . "'";
  if ($dateTo && !empty($availableCols['created_at']))
    $whereParts[] = "created_at <= '" . mysqli_real_escape_string($conn, $dateTo . ' 23:59:59') . "'";

  $whereSql = count($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

  // total count
  $cntSql = "SELECT COUNT(*) as cnt FROM users " . $whereSql;
  $cntRes = mysqli_query($conn, $cntSql);
  if ($cntRes === false)
    apiError('Count query failed', ['sql' => $cntSql, 'mysqli_error' => mysqli_error($conn)]);
  $cntRow = mysqli_fetch_assoc($cntRes);
  $total = (int) ($cntRow['cnt'] ?? 0);

  // main query
  $orderBy = ($sort === 'name') ? "u.first_name $dir, u.last_name $dir" : (($sort === 'email') ? "u.email $dir" : (($sort === 'domains') ? "domain_count $dir" : (($sort === 'spent') ? "total_spent $dir" : "u.created_at $dir")));

  // Build select list based on available columns
  $selectFields = ['u.id'];
  if (!empty($availableCols['email']))
    $selectFields[] = 'u.email';
  if (!empty($availableCols['first_name']))
    $selectFields[] = 'u.first_name';
  if (!empty($availableCols['last_name']))
    $selectFields[] = 'u.last_name';
  if (!empty($availableCols['username']))
    $selectFields[] = 'u.username';
  if (!empty($availableCols['phone']))
    $selectFields[] = 'u.phone';
  if (!empty($availableCols['country']))
    $selectFields[] = 'u.country';
  if (!empty($availableCols['created_at']))
    $selectFields[] = 'u.created_at';
  if (!empty($availableCols['status']))
    $selectFields[] = 'u.status';
  if (!empty($availableCols['is_admin']))
    $selectFields[] = 'u.is_admin';

  // always include aggregates
  $selectFields[] = '(SELECT COUNT(*) FROM domains d WHERE d.user_id = u.id) as domain_count';
  $selectFields[] = '(SELECT IFNULL(SUM(total),0) FROM orders o WHERE o.user_id = u.id) as total_spent';

  $sql = "SELECT " . implode(', ', $selectFields) . " FROM users u " . $whereSql . " ORDER BY " . $orderBy . " LIMIT " . intval($per) . " OFFSET " . intval($offset);

  $rows = [];
  $r = mysqli_query($conn, $sql);
  if ($r === false)
    apiError('Main query failed', ['sql' => $sql, 'mysqli_error' => mysqli_error($conn)]);
  while ($row = mysqli_fetch_assoc($r))
    $rows[] = $row;


  echo json_encode(['success' => true, 'data' => ['total' => $total, 'page' => $page, 'per' => $per, 'rows' => $rows]]);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  // Return error details for debugging (remove in production)
  echo json_encode(['success' => false, 'message' => 'Internal Server Error', 'error' => $e->getMessage()]);
  // optionally log
  error_log('users_list error: ' . $e->getMessage());
  exit;
}

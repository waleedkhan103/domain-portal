<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'Invalid method');
}
$token = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($token)) {
  http_response_code(403);
  jsonResponse(false, 'Invalid CSRF token');
}

global $conn;
$id    = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$isAdmin = isset($_POST['is_admin']) && ($_POST['is_admin'] == '1' || $_POST['is_admin'] === 'on') ? 1 : 0;

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
  jsonResponse(false, 'Valid email required');

// inspect available columns once
$availCols = [];
$colsRes = mysqli_query($conn, "SHOW COLUMNS FROM users");
if ($colsRes) {
  while ($c = mysqli_fetch_assoc($colsRes))
    $availCols[$c['Field']] = true;
}
$hasName     = !empty($availCols['name']);
$hasUsername = !empty($availCols['username']);
$hasPassHash = !empty($availCols['password_hash']); // some installs use password_hash
$passCol     = $hasPassHash ? 'password_hash' : 'password';

if ($id) {
  // update
  $fields = [];
  $types  = '';
  $params = [];
  $fields[] = 'email = ?';
  $types   .= 's';
  $params[] = $email;
  if ($hasName) {
    $fields[] = 'name = ?';
    $types   .= 's';
    $params[] = $name;
  }
  if ($hasUsername) {
    $fields[] = 'username = ?';
    $types   .= 's';
    $params[] = $username;
  }
  $fields[] = 'is_admin = ?';
  $types   .= 'i';
  $params[] = $isAdmin;
  if ($password) {
    $hash     = password_hash($password, PASSWORD_DEFAULT);
    $fields[] = $passCol . ' = ?';
    $types   .= 's';
    $params[] = $hash;
  }

  $sql      = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
  $params[] = $id;
  $types   .= 'i';
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    jsonResponse(true, 'User updated');
  } else {
    jsonResponse(false, 'DB error: ' . $conn->error);
  }
} else {
  // create
  if (!$password)
    jsonResponse(false, 'Password required for new user');
  $hash = password_hash($password, PASSWORD_DEFAULT);

  $cols   = ['email', $passCol, 'is_admin', 'created_at'];
  $vals   = ['?', '?', '?', 'NOW()'];
  $types  = 'ssi';
  $params = [$email, $hash, $isAdmin];

  if ($hasName) {
    $cols[]   = 'name';
    $vals[]   = '?';
    $types   .= 's';
    $params[] = $name;
  }
  if ($hasUsername) {
    $cols[]   = 'username';
    $vals[]   = '?';
    $types   .= 's';
    $params[] = $username;
  }

  $sql = "INSERT INTO users (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();
    jsonResponse(true, 'User created', ['id' => $newId]);
  } else {
    jsonResponse(false, 'DB error: ' . $conn->error);
  }
}

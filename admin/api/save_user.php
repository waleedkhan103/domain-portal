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
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$isAdmin = isset($_POST['is_admin']) && ($_POST['is_admin'] == '1' || $_POST['is_admin'] === 'on') ? 1 : 0;

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
  jsonResponse(false, 'Valid email required');

if ($id) {
  // update
  $fields = [];
  $types = '';
  $params = [];
  $fields[] = 'email = ?';
  $types .= 's';
  $params[] = $email;
  // only include username if column exists
  $colsRes = mysqli_query($conn, "SHOW COLUMNS FROM users");
  $hasUsername = false;
  if ($colsRes) {
    while ($c = mysqli_fetch_assoc($colsRes)) {
      if ($c['Field'] === 'username') {
        $hasUsername = true;
        break;
      }
    }
  }
  if ($hasUsername) {
    $fields[] = 'username = ?';
    $types .= 's';
    $params[] = $username;
  }
  $fields[] = 'is_admin = ?';
  $types .= 'i';
  $params[] = $isAdmin;
  if ($password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $fields[] = 'password_hash = ?';
    $types .= 's';
    $params[] = $hash;
  }

  $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
  $params[] = $id;
  $types .= 'i';
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    jsonResponse(true, 'User updated');
  } else {
    jsonResponse(false, 'DB error');
  }
} else {
  // create
  if (!$password)
    jsonResponse(false, 'Password required for new user');
  $hash = password_hash($password, PASSWORD_DEFAULT);
  // only include username column if available
  $colsRes = mysqli_query($conn, "SHOW COLUMNS FROM users");
  $hasUsername = false;
  if ($colsRes) {
    while ($c = mysqli_fetch_assoc($colsRes)) {
      if ($c['Field'] === 'username') {
        $hasUsername = true;
        break;
      }
    }
  }
  if ($hasUsername) {
    $sql = "INSERT INTO users (email, username, password_hash, is_admin, created_at) VALUES (?, ?, ?, ?, NOW())";
    if ($stmt = $conn->prepare($sql)) {
      $stmt->bind_param('sssi', $email, $username, $hash, $isAdmin);
      $stmt->execute();
      $newId = $stmt->insert_id;
      $stmt->close();
      jsonResponse(true, 'User created', ['id' => $newId]);
    } else {
      jsonResponse(false, 'DB error');
    }
  } else {
    $sql = "INSERT INTO users (email, password_hash, is_admin, created_at) VALUES (?, ?, ?, NOW())";
    if ($stmt = $conn->prepare($sql)) {
      $stmt->bind_param('ssi', $email, $hash, $isAdmin);
      $stmt->execute();
      $newId = $stmt->insert_id;
      $stmt->close();
      jsonResponse(true, 'User created', ['id' => $newId]);
    } else {
      jsonResponse(false, 'DB error');
    }
  }
}

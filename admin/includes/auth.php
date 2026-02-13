<?php
// Admin auth helpers
require_once __DIR__ . '/../../config/admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

function adminAttemptLogin($username, $password)
{
  global $conn;
  // Prefer admins table if exists
  $check = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");
  if ($check && mysqli_num_rows($check) > 0) {
    // prepared select for admin by username
    if ($stmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1")) {
      $stmt->bind_param('s', $username);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (password_verify($password, $row['password_hash'])) {
          $_SESSION['admin_logged_in'] = true;
          $_SESSION['admin_id'] = $row['id'];
          $stmt->close();
          return true;
        }
      }
      $stmt->close();
      return false;
    } else {
      // fallback raw query
      $u = mysqli_real_escape_string($conn, $username);
      $res = mysqli_query($conn, "SELECT id, username, password_hash FROM admins WHERE username = '$u' LIMIT 1");
      if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        if (password_verify($password, $row['password_hash'])) {
          $_SESSION['admin_logged_in'] = true;
          $_SESSION['admin_id'] = $row['id'];
          return true;
        }
      }
      return false;
    }
  }

  // Fallback to `users` table with is_admin flag
  $check2 = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'is_admin'");
  if ($check2 && mysqli_num_rows($check2) > 0) {
    // prepared select for user by email and is_admin
    if ($stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ? AND is_admin = 1 LIMIT 1")) {
      $stmt->bind_param('s', $username);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (password_verify($password, $row['password']) || $row['password'] === $password) {
          $_SESSION['admin_logged_in'] = true;
          $_SESSION['admin_id'] = $row['id'];
          $stmt->close();
          return true;
        }
      }
      $stmt->close();
    } else {
      $u = mysqli_real_escape_string($conn, $username);
      $res = mysqli_query($conn, "SELECT id, email, password FROM users WHERE email = '$u' AND is_admin = 1 LIMIT 1");
      if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        if (password_verify($password, $row['password']) || $row['password'] === $password) {
          $_SESSION['admin_logged_in'] = true;
          $_SESSION['admin_id'] = $row['id'];
          return true;
        }
      }
      if (defined('ADMIN_REQUIRE_DB') && ADMIN_REQUIRE_DB) {
        return false;
      }
    }
  }

  // Last fallback to config constants
  if (defined('ADMIN_USERNAME') && defined('ADMIN_PASSWORD')) {
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
      $_SESSION['admin_logged_in'] = true;
      $_SESSION['admin_id'] = 0;
      return true;
    }
  }

  return false;
}

function requireAdmin()
{
  if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // If this is an API/AJAX request, return JSON 401 instead of redirecting
    $isAjax = false;
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
      $isAjax = true;
    if (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
      $isAjax = true;
    if (!empty($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], '/admin/api/') !== false)
      $isAjax = true;

    if ($isAjax) {
      http_response_code(401);
      echo json_encode(['success' => false, 'message' => 'Unauthorized']);
      exit;
    }

    $base = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $base . '/admin/login.php');
    exit;
  }
}

?>
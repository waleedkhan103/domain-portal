<?php
/**
 * Password Reset API - Request reset & Reset with token
 */
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  jsonResponse(false, 'An error occurred');
});

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($conn) || !$conn) {
  jsonResponse(false, 'Database connection failed');
}

$action = $_POST['action'] ?? '';

switch ($action) {
  case 'request':
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      jsonResponse(false, 'Valid email is required');
    }

    $email = mysqli_real_escape_string($conn, $email);
    $userRow = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' LIMIT 1");
    if (!$userRow || mysqli_num_rows($userRow) === 0) {
      // Don't reveal if email exists
      jsonResponse(true, 'If this email is registered, you will receive reset instructions shortly');
    }

    $user = mysqli_fetch_assoc($userRow);
    $userId = (int) $user['id'];
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Create table if not exists
    $createSql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      token VARCHAR(64) NOT NULL UNIQUE,
      expires_at DATETIME NOT NULL,
      used TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_token (token),
      INDEX idx_expires (expires_at)
    )";
    @mysqli_query($conn, $createSql);

    mysqli_query($conn, "DELETE FROM password_reset_tokens WHERE user_id = $userId");
    $tokenEsc = mysqli_real_escape_string($conn, $token);
    $ins = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES ($userId, '$tokenEsc', '$expires')";
    if (!mysqli_query($conn, $ins)) {
      jsonResponse(false, 'Unable to process request');
    }

    $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
      . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
      . (defined('BASE_PATH') ? BASE_PATH : '')
      . '/pages/reset_password.php?token=' . $token;

    $subject = 'Reset Your Password - DomainPortal';
    $message = "Hello,\n\nClick the link below to reset your password:\n$resetUrl\n\nThis link expires in 1 hour.\n\nIf you did not request this, ignore this email.";
    $headers = 'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'domainportal.com') . "\r\n";
    @mail($email, $subject, $message, $headers);

    jsonResponse(true, 'If this email is registered, you will receive reset instructions shortly');
    break;

  case 'reset':
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (empty($token)) {
      jsonResponse(false, 'Invalid or expired reset link');
    }
    if (strlen($password) < 8) {
      jsonResponse(false, 'Password must be at least 8 characters');
    }
    if ($password !== $confirm) {
      jsonResponse(false, 'Passwords do not match');
    }

    $tokenEsc = mysqli_real_escape_string($conn, $token);
    $now = date('Y-m-d H:i:s');
    $row = mysqli_query($conn, "SELECT id, user_id FROM password_reset_tokens WHERE token = '$tokenEsc' AND used = 0 AND expires_at > '$now' LIMIT 1");
    if (!$row || mysqli_num_rows($row) === 0) {
      jsonResponse(false, 'Invalid or expired reset link');
    }

    $tok = mysqli_fetch_assoc($row);
    $userId = (int) $tok['user_id'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $hashEsc = mysqli_real_escape_string($conn, $hash);

    mysqli_query($conn, "UPDATE users SET password = '$hashEsc' WHERE id = $userId");
    mysqli_query($conn, "UPDATE password_reset_tokens SET used = 1 WHERE id = " . (int) $tok['id']);

    jsonResponse(true, 'Password updated successfully', ['redirect' => '/pages/login.php']);
    break;

  default:
    jsonResponse(false, 'Invalid action');
}

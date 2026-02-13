<?php
// Start output buffering first to catch any stray output
ob_start();

session_start();

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Custom error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  jsonResponse(false, 'Error: ' . $errstr . ' in ' . basename($errfile) . ' on line ' . $errline);
});

// Catch fatal errors too
register_shutdown_function(function () {
  $error = error_get_last();
  if ($error !== null && $error['type'] === E_ERROR) {
    jsonResponse(false, 'Fatal error: ' . $error['message']);
  }
});

// Include config and functions
try {
  require_once __DIR__ . '/../config/database.php';
  require_once __DIR__ . '/../config/paths.php';
  require_once __DIR__ . '/../includes/functions.php';
} catch (Exception $e) {
  jsonResponse(false, 'Include error: ' . $e->getMessage());
}

// Check if connection exists
if (!isset($conn) || !$conn) {
  jsonResponse(false, 'Database connection failed');
}

$action = $_POST['action'] ?? '';

if (empty($action)) {
  jsonResponse(false, 'No action specified');
}

function isSafeRedirectPath($path)
{
  if (!is_string($path) || $path === '') {
    return false;
  }
  // Only allow internal app paths
  if (substr($path, 0, 7) === '/pages/') {
    return true;
  }
  if (substr($path, 0, 10) === '/index.php') {
    return true;
  }
  return substr($path, 0, 1) === '/';
}

function migrateGuestCartToUser($conn, $userId)
{
  if (empty($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
    return;
  }

  foreach ($_SESSION['guest_cart'] as $item) {
    $domain = trim((string) ($item['domain_name'] ?? ''));
    if ($domain === '') {
      continue;
    }

    $domainEsc = mysqli_real_escape_string($conn, $domain);
    $operationType = mysqli_real_escape_string($conn, (string) ($item['operation_type'] ?? 'register'));
    $period = (int) ($item['period'] ?? 1);
    $price = (float) ($item['price'] ?? 12.99);
    $isPremium = (int) ($item['is_premium'] ?? 0);
    $authCode = mysqli_real_escape_string($conn, (string) ($item['auth_code'] ?? ''));

    // Skip if already in DB cart
    $exists = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = " . (int) $userId . " AND domain_name = '$domainEsc' LIMIT 1");
    if ($exists && mysqli_num_rows($exists) > 0) {
      continue;
    }

    $sql = "INSERT INTO cart (user_id, domain_name, operation_type, period, price, is_premium, auth_code)
            VALUES (" . (int) $userId . ", '$domainEsc', '$operationType', $period, $price, $isPremium, " . ($authCode ? "'$authCode'" : "NULL") . ")";
    @mysqli_query($conn, $sql);

  }

  // Clear guest cart after migration
  $_SESSION['guest_cart'] = [];
}

switch ($action) {
  case 'register':
    $email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    $password = $_POST['password'] ?? '';
    $firstName = clean($_POST['first_name'] ?? '');
    $lastName = clean($_POST['last_name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $country = clean($_POST['country'] ?? 'US');

    // Validate
    if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
      jsonResponse(false, 'All fields are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      jsonResponse(false, 'Invalid email address');
    }

    if (strlen($password) < 8) {
      jsonResponse(false, 'Password must be at least 8 characters');
    }

    // Check if email exists
    $checkEmail = mysqli_real_escape_string($conn, $email);
    $checkSql = "SELECT id FROM users WHERE email = '$checkEmail'";
    $checkResult = mysqli_query($conn, $checkSql);

    if (!$checkResult) {
      jsonResponse(false, 'Database error: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($checkResult) > 0) {
      jsonResponse(false, 'Email already registered');
    }

    // Hash password - DO NOT ESCAPE HASHED PASSWORDS
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Prepare values (escape all except hashed password)
    $email = mysqli_real_escape_string($conn, $email);
    $firstName = mysqli_real_escape_string($conn, $firstName);
    $lastName = mysqli_real_escape_string($conn, $lastName);
    $phone = mysqli_real_escape_string($conn, $phone);
    $country = mysqli_real_escape_string($conn, $country);

    // Insert user - hashed password is already safe
    $insertSql = "INSERT INTO users (email, password, first_name, last_name, phone, country) 
                  VALUES ('$email', '$hashedPassword', '$firstName', '$lastName', '$phone', '$country')";

    if (mysqli_query($conn, $insertSql)) {
      $userId = mysqli_insert_id($conn);

      // Log activity
      logActivity($userId, null, 'user_registered', 'User account created');

      // Auto login
      $_SESSION['user_id'] = $userId;
      $_SESSION['user_email'] = $email;

      // If user added items as guest, move them to DB cart
      migrateGuestCartToUser($conn, $userId);

      jsonResponse(true, 'Registration successful', ['redirect' => '/pages/dashboard.php']);
    } else {
      jsonResponse(false, 'Database error: ' . mysqli_error($conn));
    }
    break;

  case 'login':
    $email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
      jsonResponse(false, 'Email and password are required');
    }

    // Check user exists
    $email = mysqli_real_escape_string($conn, $email);
    $loginSql = "SELECT id, password, first_name, email FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $loginSql);

    if (!$result) {
      jsonResponse(false, 'Database error: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) === 0) {
      jsonResponse(false, 'Invalid email or password');
    }

    $user = mysqli_fetch_assoc($result);

    // Debug: Check password
    $isPasswordValid = password_verify($password, $user['password']);

    if (!$isPasswordValid) {
      jsonResponse(false, 'Invalid email or password');
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'];

    // If user added items as guest, move them to DB cart
    migrateGuestCartToUser($conn, (int) $user['id']);

    if ($remember) {
      setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/');
    }

    // Log activity
    logActivity($user['id'], null, 'user_login', 'User logged in');

    $redirect = $_POST['redirect'] ?? '';
    $redirectPath = isSafeRedirectPath($redirect) ? $redirect : '/pages/dashboard.php';
    jsonResponse(true, 'Login successful', ['redirect' => $redirectPath]);
    break;

  case 'check_session':
    if (isset($_SESSION['user_id'])) {
      jsonResponse(true, 'Session active', ['user_id' => $_SESSION['user_id']]);
    } else {
      jsonResponse(false, 'Not logged in');
    }
    break;

  default:
    jsonResponse(false, 'Invalid action');
}
?>
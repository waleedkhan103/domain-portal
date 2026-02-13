<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/../config/paths.php';

$redirect = $_GET['redirect'] ?? ($_POST['redirect'] ?? (BASE_PATH . '/admin/dashboard.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = $_POST['csrf_token'] ?? '';
  if (!validateCSRFToken($postedToken)) {
    $error = 'Invalid request';
  } else {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if (adminAttemptLogin($user, $pass)) {
      $decoded = urldecode($redirect);
      if (strpos($decoded, BASE_PATH) === 0) {
        header('Location: ' . $decoded);
      } else {
        header('Location: ' . BASE_PATH . '/admin/dashboard.php');
      }
      exit;
    } else {
      $error = 'Invalid credentials';
    }
  }
}
$pageTitle = 'Admin Login';
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <title><?php echo $pageTitle; ?></title>
  <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/admin/assets/css/admin.css">
</head>

<body>
  <div class="admin-login">
    <h1>Admin Login</h1>
    <?php if (!empty($error))
      echo '<p class="error">' . htmlspecialchars($error) . '</p>'; ?>
    <form method="post">
      <?php echo getCSRFField(); ?>
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
      <label>Username<br><input name="username" required></label><br>
      <label>Password<br><input name="password" type="password" required></label><br>
      <button type="submit">Login</button>
    </form>
  </div>
</body>

</html>
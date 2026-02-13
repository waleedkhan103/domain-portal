<?php
// Enable detailed errors for admin pages to diagnose HTTP 500
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/paths.php';
// Basic admin session check - redirect to login and preserve original path
if (!isset($_SESSION['admin_logged_in'])) {
  $current = $_SERVER['REQUEST_URI'] ?? BASE_PATH . '/admin/dashboard.php';
  $redirect = urlencode($current);
  header('Location: ' . BASE_PATH . '/admin/login.php?redirect=' . $redirect);
  exit;
}
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle ?? 'Admin'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity=""
    crossorigin="anonymous">
  <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/admin/assets/css/admin.css">
  <script>
    window.BASE_PATH = '<?php echo rtrim(BASE_PATH, '/'); ?>';
  </script>
  <script src="<?php echo BASE_PATH; ?>/admin/assets/js/admin.js" defer></script>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="<?php echo BASE_PATH; ?>/admin/dashboard.php">Admin</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar"
        aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="adminNavbar">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE_PATH; ?>/admin/manage_users.php">Users</a></li>
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE_PATH; ?>/admin/domains.php">Domains</a></li>
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE_PATH; ?>/admin/orders.php">Orders</a></li>
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE_PATH; ?>/admin/settings.php">Settings</a></li>
        </ul>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE_PATH; ?>/admin/logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
  <main class="admin-main container mt-4">
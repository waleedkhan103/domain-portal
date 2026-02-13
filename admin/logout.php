<?php
session_start();
// Clear only admin session keys
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
// Optionally destroy session if no other app session needed
// session_destroy();

require_once __DIR__ . '/../config/paths.php';
header('Location: ' . BASE_PATH . '/admin/login.php');
exit;
?>
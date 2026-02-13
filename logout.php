<?php
session_start();

// Clear session
session_destroy();

// Clear remember token cookie
setcookie('remember_token', '', time() - 3600, '/');
setcookie('remember_email', '', time() - 3600, '/');

// Get base path
require_once __DIR__ . '/config/paths.php';

// Redirect to home
$redirectUrl = BASE_PATH . '/index.php';
header('Location: ' . $redirectUrl);
exit;
?>
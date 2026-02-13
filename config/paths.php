<?php
/**
 * config/paths.php
 * Automatically detects the base path for your application
 */

// Get the real base path by looking at where this config file is
// This file is at: /misc/waleed/domain-portal/config/paths.php
// So the app root is: /misc/waleed/domain-portal

$this_file = __FILE__; // Full server path to this file
$document_root = $_SERVER['DOCUMENT_ROOT'];

// Get the path relative to document root
$relative_path = str_replace(array($document_root, '\\'), array('', '/'), $this_file);

// Remove the /config/paths.php part to get the app root
$base_path = dirname(dirname($relative_path)); // Go up two directories

// Clean up the path
$base_path = str_replace('\\', '/', $base_path);

// Ensure it starts with /
if (substr($base_path, 0, 1) !== '/') {
  $base_path = '/' . $base_path;
}

// Remove trailing slash except for root
if ($base_path !== '/' && substr($base_path, -1) === '/') {
  $base_path = rtrim($base_path, '/');
}

define('BASE_PATH', $base_path);

// Helper function for creating URLs
function assetUrl($path)
{
  $path = ltrim($path, '/');
  return BASE_PATH . '/' . $path;
}

// Helper function for page links
function pageUrl($page)
{
  return BASE_PATH . '/pages/' . ltrim($page, '/');
}
?>
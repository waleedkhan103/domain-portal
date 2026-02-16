<?php
// Check different MySQL hosts
$hosts = ['localhost', '127.0.0.1', '::1'];
$user = 'allrounder';
$pass = '7ujm&5tgb%';
$db = 'domain_portal';

foreach ($hosts as $host) {
  $conn = @mysqli_connect($host, $user, $pass, $db);
  $status = $conn ? "✓ Connected" : "✗ Failed (" . mysqli_connect_error() . ")";
  echo "$host: $status\n";
  if ($conn) mysqli_close($conn);
}

// Also show PHP info
echo "\nPHP Version: " . phpversion() . "\n";
echo "MySQLi available: " . (extension_loaded('mysqli') ? "Yes" : "No") . "\n";

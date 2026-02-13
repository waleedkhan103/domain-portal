<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'allrounder');
define('DB_PASS', '7ujm&5tgb%');
define('DB_NAME', 'domain_portal');
// Disable throwing mysqli exceptions during connect
mysqli_report(MYSQLI_REPORT_OFF);
// Create database connection
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
// Check connection - handle errors gracefully
if (!$conn) {
  $error_message = mysqli_connect_error();
  // Check if this is an API endpoint call
  if (
    strpos($_SERVER['REQUEST_URI'], '/api/') !== false ||
    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
  ) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'success' => false,
      'message' => 'Database connection failed',
      'error' => 'Unable to connect to database server'
    ]);
    exit;
  } else {
    // Avoid dying here so admin pages can show a meaningful error page
    trigger_error("Connection failed: " . $error_message, E_USER_WARNING);
    $conn = null;
  }
}
// Set charset to utf8mb4
if ($conn) {
  mysqli_set_charset($conn, "utf8mb4");
} else {
  // no DB connection available
}
// Function to get database connection
function getConnection()
{
  global $conn;
  return $conn;
}
?>

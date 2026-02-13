<?php
// Helper Functions

// Sanitize input
function clean($data)
{
  global $conn;
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return mysqli_real_escape_string($conn, $data);
}

// Backwards-compatible sanitizer used elsewhere
function sanitizeInput($data)
{
  return clean($data);
}

// Check if user is logged in
function isLoggedIn()
{
  return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Redirect to login if not logged in
function requireLogin()
{
  if (!isLoggedIn()) {
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    $current = $_SERVER['REQUEST_URI'] ?? ($base . '/pages/dashboard.php');
    header('Location: ' . $base . '/pages/login.php?redirect=' . urlencode($current));
    exit;
  }
}

// Get current user data
function getCurrentUser()
{
  if (!isLoggedIn()) {
    return null;
  }

  global $conn;
  $userId = (int) $_SESSION['user_id'];
  $sql = "SELECT * FROM users WHERE id = $userId";
  $result = mysqli_query($conn, $sql);

  if ($result && mysqli_num_rows($result) > 0) {
    return mysqli_fetch_assoc($result);
  }

  return null;
}

// Generate unique contact ID
function generateContactID($userId)
{
  return 'C' . str_pad($userId, 6, '0', STR_PAD_LEFT) . rand(1000, 9999);
}

// Generate unique order number
function generateOrderNumber()
{
  return 'ORD' . date('Ymd') . rand(10000, 99999);
}

// Log activity
function logActivity($userId, $domainId, $action, $description)
{
  global $conn;

  $userId = $userId ? (int) $userId : 'NULL';
  $domainId = $domainId ? (int) $domainId : 'NULL';
  $action = clean($action);
  $description = clean($description);
  $ip = $_SERVER['REMOTE_ADDR'];
  $userAgent = $_SERVER['HTTP_USER_AGENT'];

  $sql = "INSERT INTO activity_log (user_id, domain_id, action, description, ip_address, user_agent) 
            VALUES ($userId, $domainId, '$action', '$description', '$ip', '$userAgent')";

  mysqli_query($conn, $sql);
}

// Format date
function formatDate($date)
{
  return date('M j, Y', strtotime($date));
}

// Calculate days until expiry
function daysUntilExpiry($expiryDate)
{
  $now = time();
  $expiry = strtotime($expiryDate);
  return floor(($expiry - $now) / 86400);
}

// Get cart count for user
function getCartCount($userId)
{
  global $conn;
  $userId = (int) $userId;
  $sql = "SELECT COUNT(*) as count FROM cart WHERE user_id = $userId";
  $result = mysqli_query($conn, $sql);

  if ($result) {
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
  }

  return 0;
}

// Validate domain name
function isValidDomain($domain)
{
  return preg_match('/^(?!-)[A-Za-z0-9-]+([-.]{1}[a-z0-9]+)*\.[A-Za-z]{2,}$/', $domain);
}

// Extract domain and TLD
function parseDomain($domain)
{
  $parts = explode('.', $domain);
  if (count($parts) >= 2) {
    $tld = '.' . array_pop($parts);
    $name = implode('.', $parts);
    return ['name' => $name, 'tld' => $tld, 'full' => $domain];
  }
  return null;
}

// Format price
function formatPrice($price)
{
  return '$' . number_format($price, 2);
}

// Send JSON response
function jsonResponse($success, $message, $data = [])
{
  header('Content-Type: application/json');
  echo json_encode([
    'success' => $success,
    'message' => $message,
    'data' => $data
  ]);
  exit;
}

// Get user's default contact or create one
function getUserDefaultContact($userId)
{
  global $conn;
  $userId = (int) $userId;

  $sql = "SELECT * FROM contacts WHERE user_id = $userId LIMIT 1";
  $result = mysqli_query($conn, $sql);

  if ($result && mysqli_num_rows($result) > 0) {
    return mysqli_fetch_assoc($result);
  }

  // Create default contact from user data
  $user = getCurrentUser();
  if ($user) {
    $contactId = generateContactID($userId);

    $sql = "INSERT INTO contacts (user_id, contact_id, first_name, last_name, company, email, address, city, state, country, postal_code, phone) 
                VALUES ($userId, '$contactId', 
                        '" . clean($user['first_name']) . "', 
                        '" . clean($user['last_name']) . "', 
                        '" . clean($user['company'] ?? '') . "', 
                        '" . clean($user['email']) . "', 
                        '" . clean($user['address'] ?? '123 Main St') . "', 
                        '" . clean($user['city'] ?? 'New York') . "', 
                        '" . clean($user['state'] ?? 'NY') . "', 
                        '" . clean($user['country']) . "', 
                        '" . clean($user['postal_code'] ?? '10001') . "', 
                        '" . clean($user['phone']) . "')";

    if (mysqli_query($conn, $sql)) {
      return [
        'contact_id' => $contactId,
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone']
      ];
    }
  }

  return null;
}
?>
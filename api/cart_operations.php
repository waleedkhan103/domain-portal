<?php
session_start();

// Prevent HTML output in API
while (ob_get_level() > 0) {
  ob_end_clean();
}

ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  jsonResponse(false, "Error [$errno]: $errstr in $errfile:$errline");
});

// Catch fatal errors
register_shutdown_function(function () {
  $error = error_get_last();
  if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE])) {
    jsonResponse(false, "Fatal: " . $error['message']);
  }
});

try {
  require_once __DIR__ . '/../config/database.php';
  require_once __DIR__ . '/../includes/functions.php';
} catch (Exception $e) {
  jsonResponse(false, "Include error: " . $e->getMessage());
}

// Allow guest cart (session-based). DB is required only for logged-in carts.
$isGuest = !isLoggedIn();

$action = $_POST['action'] ?? '';
$userId = $isGuest ? null : (int) ($_SESSION['user_id'] ?? 0);

if (empty($action)) {
  jsonResponse(false, 'No action specified');
}

switch ($action) {
  case 'add':
    $domain = clean($_POST['domain_name'] ?? clean($_POST['domain'] ?? ''));
    $operationType = clean($_POST['operation_type'] ?? 'register');
    $period = (int) ($_POST['period'] ?? 1);
    $price = (float) ($_POST['price'] ?? 12.99);
    $isPremium = (int) ($_POST['is_premium'] ?? 0);
    $authCode = clean($_POST['auth_code'] ?? '');

    if (empty($domain)) {
      jsonResponse(false, 'Domain name is required');
    }

    if ($isGuest) {
      $_SESSION['guest_cart'] = $_SESSION['guest_cart'] ?? [];
      foreach ($_SESSION['guest_cart'] as $it) {
        if (($it['domain_name'] ?? '') === $domain) {
          jsonResponse(false, 'Domain already in cart');
        }
      }
      $_SESSION['guest_cart'][] = [
        'id' => (int) (microtime(true) * 1000) + rand(0, 999),
        'domain_name' => $domain,
        'operation_type' => $operationType,
        'period' => $period,
        'price' => $price,
        'is_premium' => $isPremium,
        'auth_code' => $authCode,
        'added_at' => date('Y-m-d H:i:s')
      ];
      jsonResponse(true, 'Domain added to cart', ['cart_count' => count($_SESSION['guest_cart'])]);
    }

    if (!isset($conn) || !$conn) {
      jsonResponse(false, 'Database connection failed');
    }

    // Escape domain name for SQL
    $domainEsc = mysqli_real_escape_string($conn, $domain);
    $operationTypeEsc = mysqli_real_escape_string($conn, $operationType);
    $authCodeEsc = mysqli_real_escape_string($conn, $authCode);

    // Check if already in cart
    $sql = "SELECT id FROM cart WHERE user_id = $userId AND domain_name = '$domainEsc'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
      jsonResponse(false, 'Domain already in cart');
    }

    // Add to cart
    $sql = "INSERT INTO cart (user_id, domain_name, operation_type, period, price, is_premium, auth_code) 
                VALUES ($userId, '$domainEsc', '$operationTypeEsc', $period, $price, $isPremium, " .
      ($authCodeEsc ? "'$authCodeEsc'" : "NULL") . ")";

    if (mysqli_query($conn, $sql)) {
      logActivity($userId, null, 'cart_add', "Added $domain to cart");
      jsonResponse(true, 'Domain added to cart', ['cart_count' => getCartCount($userId)]);
    }
    jsonResponse(false, 'Failed to add to cart');
    break;

  case 'remove':
    $cartId = (int) ($_POST['cart_id'] ?? 0);
    if ($isGuest) {
      $_SESSION['guest_cart'] = $_SESSION['guest_cart'] ?? [];
      $_SESSION['guest_cart'] = array_values(array_filter($_SESSION['guest_cart'], function ($it) use ($cartId) {
        return (int) ($it['id'] ?? 0) !== $cartId;
      }));
      jsonResponse(true, 'Item removed from cart', ['cart_count' => count($_SESSION['guest_cart'])]);
    }

    if (!isset($conn) || !$conn) {
      jsonResponse(false, 'Database connection failed');
    }
    $sql = "DELETE FROM cart WHERE id = $cartId AND user_id = $userId";

    if (mysqli_query($conn, $sql)) {
      logActivity($userId, null, 'cart_remove', "Removed item from cart");
      jsonResponse(true, 'Item removed from cart', ['cart_count' => getCartCount($userId)]);
    }
    jsonResponse(false, 'Failed to remove item');
    break;

  case 'update_period':
    $cartId = (int) ($_POST['cart_id'] ?? 0);
    $period = (int) ($_POST['period'] ?? 1);

    if ($period < 1 || $period > 10) {
      jsonResponse(false, 'Period must be between 1 and 10 years');
    }

    if ($isGuest) {
      $_SESSION['guest_cart'] = $_SESSION['guest_cart'] ?? [];
      foreach ($_SESSION['guest_cart'] as &$it) {
        if ((int) ($it['id'] ?? 0) === $cartId) {
          $it['period'] = $period;
        }
      }
      unset($it);
      jsonResponse(true, 'Period updated');
    }

    if (!isset($conn) || !$conn) {
      jsonResponse(false, 'Database connection failed');
    }
    $sql = "UPDATE cart SET period = $period WHERE id = $cartId AND user_id = $userId";

    if (mysqli_query($conn, $sql)) {
      jsonResponse(true, 'Period updated');
    }
    jsonResponse(false, 'Failed to update period');
    break;

  case 'get_cart':
    $items = [];
    $total = 0;

    if ($isGuest) {
      $_SESSION['guest_cart'] = $_SESSION['guest_cart'] ?? [];
      foreach ($_SESSION['guest_cart'] as $row) {
        $subtotal = ((float) ($row['price'] ?? 0)) * (int) ($row['period'] ?? 1);
        $total += $subtotal;
        $items[] = [
          'id' => (int) ($row['id'] ?? 0),
          'domain' => $row['domain_name'] ?? '',
          'operation_type' => $row['operation_type'] ?? 'register',
          'period' => (int) ($row['period'] ?? 1),
          'price' => (float) ($row['price'] ?? 0),
          'subtotal' => $subtotal,
          'is_premium' => (int) ($row['is_premium'] ?? 0)
        ];
      }
      jsonResponse(true, 'Cart retrieved', ['items' => $items, 'total' => $total, 'count' => count($items)]);
    }

    if (!isset($conn) || !$conn) {
      jsonResponse(false, 'Database connection failed');
    }

    $sql = "SELECT * FROM cart WHERE user_id = $userId ORDER BY added_at DESC";
    $result = mysqli_query($conn, $sql);

    while ($result && ($row = mysqli_fetch_assoc($result))) {
      $subtotal = $row['price'] * $row['period'];
      $total += $subtotal;

      $items[] = [
        'id' => $row['id'],
        'domain' => $row['domain_name'],
        'operation_type' => $row['operation_type'],
        'period' => $row['period'],
        'price' => $row['price'],
        'subtotal' => $subtotal,
        'is_premium' => $row['is_premium']
      ];
    }

    jsonResponse(true, 'Cart retrieved', [
      'items' => $items,
      'total' => $total,
      'count' => count($items)
    ]);
    break;

  case 'clear':
    if ($isGuest) {
      $_SESSION['guest_cart'] = [];
      jsonResponse(true, 'Cart cleared');
    }

    if (!isset($conn) || !$conn) {
      jsonResponse(false, 'Database connection failed');
    }
    $sql = "DELETE FROM cart WHERE user_id = $userId";

    if (mysqli_query($conn, $sql)) {
      jsonResponse(true, 'Cart cleared');
    }
    jsonResponse(false, 'Failed to clear cart');
    break;

  default:
    jsonResponse(false, 'Invalid action');
}
?>
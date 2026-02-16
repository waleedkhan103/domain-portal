<?php
// Disable error display - show JSON instead
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../../config/database.php';

global $conn;

$q = trim($_GET['q'] ?? '');
if ($q === '') {
  echo json_encode(['success' => true, 'data' => []]);
  exit;
}

// If database is unavailable, serve mock search results for development/testing
if (!$conn) {
  include __DIR__ . '/dashboard_search_mock.php';
  exit;
}

// Database is available, now require admin auth
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

$out = ['success' => true, 'data' => []];

// Domains suggestions
$domains = [];
$like = '%' . mysqli_real_escape_string($conn, $q) . '%';
if ($stmt = $conn->prepare("SELECT id, domain_name FROM domains WHERE domain_name LIKE ? LIMIT 8")) {
  $stmt->bind_param('s', $like);
  $stmt->execute();
  $r = $stmt->get_result();
  while ($row = $r->fetch_assoc())
    $domains[] = ['type' => 'domain', 'id' => $row['id'], 'label' => $row['domain_name']];
  $stmt->close();
} else {
  $r = mysqli_query($conn, "SELECT id, domain_name FROM domains WHERE domain_name LIKE '" . $like . "' LIMIT 8");
  while ($row = mysqli_fetch_assoc($r))
    $domains[] = ['type' => 'domain', 'id' => $row['id'], 'label' => $row['domain_name']];
}

// Orders suggestions by order number or id exact match
$orders = [];
if (is_numeric($q)) {
  $oid = (int) $q;
  if ($stmt2 = $conn->prepare("SELECT id, COALESCE(order_number, id) as order_number FROM orders WHERE id = ? LIMIT 5")) {
    $stmt2->bind_param('i', $oid);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    while ($row = $r2->fetch_assoc())
      $orders[] = ['type' => 'order', 'id' => $row['id'], 'label' => $row['order_number']];
    $stmt2->close();
  }
}
if (count($orders) === 0 && strlen($q) >= 3) {
  $like2 = '%' . mysqli_real_escape_string($conn, $q) . '%';
  if ($stmt3 = $conn->prepare("SELECT id, COALESCE(order_number, id) as order_number FROM orders WHERE COALESCE(order_number,'') LIKE ? LIMIT 6")) {
    $stmt3->bind_param('s', $like2);
    $stmt3->execute();
    $r3 = $stmt3->get_result();
    while ($row = $r3->fetch_assoc())
      $orders[] = ['type' => 'order', 'id' => $row['id'], 'label' => $row['order_number']];
    $stmt3->close();
  }
}

$out['data'] = array_merge($domains, $orders);

echo json_encode($out);
exit;

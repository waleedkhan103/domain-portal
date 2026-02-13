<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/header.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
  echo '<p>Invalid id</p>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

global $conn;
// Remove user and cascade depending on FK constraints (prepared)
if ($stmt = $conn->prepare("DELETE FROM users WHERE id = ? LIMIT 1")) {
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $stmt->close();
} else {
  mysqli_query($conn, "DELETE FROM users WHERE id = $id LIMIT 1");
}
header('Location: manage_users.php');
exit;
?>
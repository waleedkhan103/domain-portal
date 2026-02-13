<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/header.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
  echo '<p>Invalid user id</p>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

global $conn;
$user = null;
if ($stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1")) {
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $res->num_rows > 0) {
    $user = $res->fetch_assoc();
  }
  $stmt->close();
} else {
  $sql = "SELECT * FROM users WHERE id = $id LIMIT 1";
  $res = mysqli_query($conn, $sql);
  $user = $res ? mysqli_fetch_assoc($res) : null;
}

if (!$user) {
  echo '<p>User not found</p>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}
?>
<h1>View User #<?php echo $user['id']; ?></h1>
<ul>
  <li><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></li>
  <li><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></li>
  <li><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?></li>
  <li><strong>Country:</strong> <?php echo htmlspecialchars($user['country']); ?></li>
  <li><strong>Created:</strong> <?php echo htmlspecialchars($user['created_at'] ?? ''); ?></li>
</ul>
<p><a href="edit_user.php?id=<?php echo $user['id']; ?>">Edit</a> | <a href="manage_users.php">Back to list</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
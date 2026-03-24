<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
global $conn;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
  $postedToken = $_POST['csrf_token'] ?? '';
  if (!validateCSRFToken($postedToken)) {
    echo '<div class="alert alert-danger">Invalid request (CSRF).</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
  }
  $id      = (int) $_POST['id'];
  $email   = $_POST['email'];
  $name    = trim($_POST['name'] ?? '');
  $phone   = $_POST['phone'] ?? '';
  $country = $_POST['country'] ?? '';

  // Prepared update
  if ($stmt = $conn->prepare("UPDATE users SET email = ?, name = ?, phone = ?, country = ? WHERE id = ?")) {
    $stmt->bind_param('ssssi', $email, $name, $phone, $country, $id);
    $stmt->execute();
    $stmt->close();
  } else {
    $emailE   = mysqli_real_escape_string($conn, $email);
    $nameE    = mysqli_real_escape_string($conn, $name);
    $phoneE   = mysqli_real_escape_string($conn, $phone);
    $countryE = mysqli_real_escape_string($conn, $country);
    $sql = "UPDATE users SET email='$emailE', name='$nameE', phone='$phoneE', country='$countryE' WHERE id=$id";
    mysqli_query($conn, $sql);
  }
  header('Location: view_user.php?id=' . $id);
  exit;
}

if ($id) {
  $sql = "SELECT * FROM users WHERE id = $id LIMIT 1";
  $res = mysqli_query($conn, $sql);
  $user = $res ? mysqli_fetch_assoc($res) : null;
} else {
  $user = null;
}

if (!$user) {
  echo '<p>User not found</p>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}
?>
<h1>Edit User #<?php echo $user['id']; ?></h1>
<form method="post">
  <?php echo getCSRFField(); ?>
  <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
  <label>Email<br><input name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></label><br>
  <label>Name<br><input name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"></label><br>
  <label>Phone<br><input name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></label><br>
  <label>Country<br><input name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>"></label><br>
  <button type="submit">Save</button>
</form>
<p><a href="view_user.php?id=<?php echo $user['id']; ?>">Cancel</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
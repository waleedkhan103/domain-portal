<?php
$pageTitle = 'Profile';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $firstName = sanitizeInput($_POST['first_name']);
  $lastName = sanitizeInput($_POST['last_name']);
  $phone = sanitizeInput($_POST['phone']);
  $company = sanitizeInput($_POST['company'] ?? '');
  $address = sanitizeInput($_POST['address'] ?? '');
  $city = sanitizeInput($_POST['city'] ?? '');
  $state = sanitizeInput($_POST['state'] ?? '');
  $country = sanitizeInput($_POST['country']);
  $postalCode = sanitizeInput($_POST['postal_code'] ?? '');

  $sql = "UPDATE users SET 
            first_name = ?,
            last_name = ?,
            phone = ?,
            company = ?,
            address = ?,
            city = ?,
            state = ?,
            country = ?,
            postal_code = ?
            WHERE id = ?";

  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "sssssssssi", $firstName, $lastName, $phone, $company, $address, $city, $state, $country, $postalCode, $user['id']);

  if (mysqli_stmt_execute($stmt)) {
    $message = 'Profile updated successfully';
    $user = getCurrentUser();
  }
}
?>

<div class="container py-4">
  <h1 class="h3 mb-4"><i class="bi bi-person-gear"></i> Profile Settings</h1>

  <?php if ($message): ?>
    <div class="alert alert-success">
      <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="POST">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
          <small class="text-muted">Email cannot be changed</small>
        </div>
        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Company (Optional)</label>
          <input type="text" name="company" class="form-control" value="<?php echo htmlspecialchars($user['company'] ?? ''); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">State</label>
            <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Postal Code</label>
            <input type="text" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Country</label>
          <select name="country" class="form-select" required>
            <option value="US" <?php echo ($user['country'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
            <option value="GB" <?php echo ($user['country'] ?? '') === 'GB' ? 'selected' : ''; ?>>United Kingdom</option>
            <option value="PK" <?php echo ($user['country'] ?? '') === 'PK' ? 'selected' : ''; ?>>Pakistan</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Update Profile</button>
      </form>
    </div>
  </div>

  <div class="card mt-4">
    <div class="card-header"><i class="bi bi-shield-lock"></i> Security</div>
    <div class="card-body">
      <p class="text-muted mb-0">Two-factor authentication (2FA) will be available in a future update. For now, use a strong password.</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
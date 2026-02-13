<?php
$pageTitle = 'Contact Management';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();

// Ensure contact_type column exists (run ALTER if needed)
$check = @mysqli_query($conn, "SHOW COLUMNS FROM contacts LIKE 'contact_type'");
if (!$check || mysqli_num_rows($check) === 0) {
  @mysqli_query($conn, "ALTER TABLE contacts ADD COLUMN contact_type VARCHAR(20) DEFAULT 'registrant'");
}

$types = ['registrant' => 'Registrant', 'admin' => 'Admin', 'tech' => 'Technical', 'billing' => 'Billing'];
$sql = "SELECT * FROM contacts WHERE user_id = {$user['id']} ORDER BY contact_type";
$res = mysqli_query($conn, $sql);
$contacts = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Get or create default contacts per type
$byType = [];
foreach ($contacts as $c) {
  $byType[$c['contact_type'] ?? 'registrant'] = $c;
}
foreach ($types as $k => $v) {
  if (empty($byType[$k])) {
    $byType[$k] = [
      'id' => null, 'contact_type' => $k, 'first_name' => $user['first_name'] ?? '',
      'last_name' => $user['last_name'] ?? '', 'email' => $user['email'] ?? '',
      'phone' => $user['phone'] ?? '', 'address' => $user['address'] ?? '123 Main St',
      'city' => $user['city'] ?? 'New York', 'state' => $user['state'] ?? 'NY',
      'country' => $user['country'] ?? 'US', 'postal_code' => $user['postal_code'] ?? '10001'
    ];
  }
}
?>

<div class="container py-4">
  <h1 class="h3 mb-4"><i class="bi bi-people"></i> Contact Management</h1>
  <p class="text-muted mb-4">Manage Registrant, Admin, Technical, and Billing contacts for your domains.</p>

  <div class="row">
    <?php foreach ($types as $typeKey => $typeLabel): ?>
      <?php $c = $byType[$typeKey]; ?>
      <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
          <div class="card-header bg-secondary text-white">
            <strong><?php echo $typeLabel; ?></strong>
          </div>
          <div class="card-body small">
            <p class="mb-1"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></p>
            <p class="mb-1"><?php echo htmlspecialchars($c['email']); ?></p>
            <p class="mb-0"><?php echo htmlspecialchars($c['phone'] ?? '-'); ?></p>
          </div>
          <div class="card-footer">
            <a href="<?php echo pageUrl('profile.php'); ?>?edit_contact=<?php echo $typeKey; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="alert alert-info">
    <i class="bi bi-info-circle"></i> Contact details are used when registering domains. Update your <a href="<?php echo pageUrl('profile.php'); ?>" class="alert-link">Profile</a> to change default contact information.
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

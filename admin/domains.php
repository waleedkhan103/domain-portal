<?php
$pageTitle = 'Admin - Domains';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/header.php';

global $conn;
if (!$conn) {
  echo '<div class="alert alert-warning">Database unavailable. Please check configuration.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$res = false;
// Prepared statement for domains list (no dynamic params)
if ($stmt = $conn->prepare("SELECT d.id, d.domain_name, d.user_id, d.status, d.expires_at, u.email FROM domains d LEFT JOIN users u ON u.id = d.user_id ORDER BY d.expires_at ASC LIMIT 200")) {
  $stmt->execute();
  $res = $stmt->get_result();
  $stmt->close();
} else {
  $sql = "SELECT d.id, d.domain_name, d.user_id, d.status, d.expires_at, u.email FROM domains d LEFT JOIN users u ON u.id = d.user_id ORDER BY d.expires_at ASC LIMIT 200";
  $res = mysqli_query($conn, $sql);
}
?>
<h1>Domains</h1>
<p>Manage domains, registrations, transfers, and DNS settings.</p>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="d-flex gap-2 align-items-center">
    <input id="domains-search" class="form-control" placeholder="Search domain, owner email, registrant"
      style="min-width:320px">
    <select id="domains-status" class="form-select" style="width:160px">
      <option value="all">All</option>
      <option value="Active">Active</option>
      <option value="Pending">Pending</option>
      <option value="Expired">Expired</option>
      <option value="Suspended">Suspended</option>
      <option value="Cancelled">Cancelled</option>
    </select>
    <select id="domains-per-page" class="form-select">
      <option value="25">25</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
    <div id="domains-spinner" style="display:none;"> <span class="spinner-border spinner-border-sm"
        role="status"></span> </div>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <?php
    // owner toggle only shown if domains table has admin_id
    $showOwnerToggle = false;
    $check = mysqli_query($conn, "SHOW COLUMNS FROM domains LIKE 'admin_id'");
    if ($check && mysqli_num_rows($check) > 0) {
      $showOwnerToggle = true;
    }
    if ($showOwnerToggle): ?>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="domains-owner-toggle">
        <label class="form-check-label" for="domains-owner-toggle">My Assigned Domains</label>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="domains-status-counts" class="mb-3"></div>

<div class="table-responsive">
  <table class="table table-hover table-bordered">
    <thead>
      <tr>
        <th>ID</th>
        <th>Domain</th>
        <th>Owner</th>
        <th>Status</th>
        <th>Expires</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="domains-tbody">
      <tr>
        <td colspan="6" class="text-center">Loading...</td>
      </tr>
    </tbody>
  </table>
</div>

<div id="domains-pager" class="mt-3"></div>

<script src="<?php echo BASE_PATH; ?>/admin/assets/js/domains.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php
$pageTitle = 'Admin - View Domain';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/header.php';

global $conn;
if (!$conn) {
  echo '<div class="alert alert-warning">Database unavailable. Please check configuration.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
  echo '<div class="alert alert-danger">Invalid domain id.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

// Prepared select for domain by id
$domain = null;
if ($stmt = $conn->prepare("SELECT d.*, u.email FROM domains d LEFT JOIN users u ON u.id = d.user_id WHERE d.id = ? LIMIT 1")) {
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $r = $stmt->get_result();
  if ($r && $r->num_rows > 0) {
    $domain = $r->fetch_assoc();
  }
  $stmt->close();
} else {
  $sql = "SELECT d.*, u.email FROM domains d LEFT JOIN users u ON u.id = d.user_id WHERE d.id = " . $id . " LIMIT 1";
  $res = mysqli_query($conn, $sql);
  $domain = $res ? mysqli_fetch_assoc($res) : null;
}
if (!$domain) {
  echo '<div class="alert alert-info">Domain not found.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$domainName = $domain['domain_name'];

$drRes = false;
// Prepared select for dns records
if ($stmt2 = $conn->prepare("SELECT id, type, host, value, ttl FROM dns_records WHERE domain_id = ? ORDER BY id ASC")) {
  $stmt2->bind_param('i', $id);
  $stmt2->execute();
  $drRes = $stmt2->get_result();
  $stmt2->close();
} else {
  $drSql = "SELECT * FROM dns_records WHERE domain_id = " . $id . " ORDER BY id ASC";
  $drRes = mysqli_query($conn, $drSql);
}
?>
<h1>Domain: <?php echo htmlspecialchars($domainName); ?></h1>
<input type="hidden" id="domain-id" value="<?php echo (int) $domain['id']; ?>">
<div class="row">
  <div class="col-md-6">
    <h5>Details</h5>
    <ul class="list-group">
      <li class="list-group-item"><strong>Owner:</strong>
        <?php echo htmlspecialchars($domain['email'] ?? ('#' . $domain['user_id'])); ?></li>
      <li class="list-group-item"><strong>Status:</strong> <?php echo htmlspecialchars($domain['status'] ?? ''); ?></li>
      <li class="list-group-item"><strong>Lock:</strong> <span id="domain-lock-badge"
          class="badge <?php echo (!empty($domain['is_locked']) ? 'bg-danger' : 'bg-success'); ?>"><?php echo (!empty($domain['is_locked']) ? 'Locked' : 'Unlocked'); ?></span>
        <button id="domain-lock-btn"
          class="btn btn-sm btn-outline-primary ms-2"><?php echo (!empty($domain['is_locked']) ? 'Unlock Domain' : 'Lock Domain'); ?></button>
      </li>
      <li class="list-group-item"><strong>Expires:</strong> <span
          id="domain-expires"><?php echo htmlspecialchars($domain['expires_at'] ?? $domain['expiry_date'] ?? ''); ?></span>
        <div class="small text-muted">Expires in:
          <?php echo daysUntilExpiry($domain['expires_at'] ?? $domain['expiry_date'] ?? date('Y-m-d')); ?> days</div>
      </li>
      <li class="list-group-item"><strong>Registrar:</strong>
        <?php echo htmlspecialchars($domain['registrar'] ?? ''); ?></li>
    </ul>
    <div class="mt-2">
      <button id="domain-auth-btn" class="btn btn-sm btn-warning">Get Auth Code</button>
      <button id="domain-renew-btn" class="btn btn-sm btn-success ms-2">Renew Domain</button>
    </div>
  </div>
  <div class="col-md-6">
    <h5>DNS Records</h5>
    <?php if ($drRes && mysqli_num_rows($drRes) > 0): ?>
      <table class="table table-sm table-bordered">
        <thead>
          <tr>
            <th>Type</th>
            <th>Host</th>
            <th>Value</th>
            <th>TTL</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($r = mysqli_fetch_assoc($drRes)): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['type']); ?></td>
              <td><?php echo htmlspecialchars($r['host']); ?></td>
              <td><?php echo htmlspecialchars($r['value']); ?></td>
              <td><?php echo htmlspecialchars($r['ttl']); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="alert alert-info">No DNS records found for this domain.</div>
    <?php endif; ?>

    <h6 class="mt-3">Nameservers (DNS)</h6>
    <div id="ns-alert"></div>
    <form id="ns-form" method="post" action="actions/update_dns.php">
      <input type="hidden" name="domain_id" value="<?php echo (int) $domain['id']; ?>">
      <?php echo getCSRFField(); ?>
      <div class="row mb-2">
        <div class="col-md-6"><input class="form-control" name="dns1" placeholder="NS1"
            value="<?php echo htmlspecialchars($domain['dns1'] ?? ''); ?>"></div>
        <div class="col-md-6"><input class="form-control" name="dns2" placeholder="NS2"
            value="<?php echo htmlspecialchars($domain['dns2'] ?? ''); ?>"></div>
      </div>
      <div class="row mb-2">
        <div class="col-md-6"><input class="form-control" name="dns3" placeholder="NS3 (optional)"
            value="<?php echo htmlspecialchars($domain['dns3'] ?? ''); ?>"></div>
        <div class="col-md-6"><input class="form-control" name="dns4" placeholder="NS4 (optional)"
            value="<?php echo htmlspecialchars($domain['dns4'] ?? ''); ?>"></div>
      </div>
      <div class="mt-2"><button type="submit" class="btn btn-sm btn-success">Update Nameservers</button></div>
    </form>
    <hr>
    <h6 class="mt-3">Edit Contacts</h6>
    <div id="domain-toast-area"></div>
    <form id="contacts-form">
      <?php echo getCSRFField(); ?>
      <div class="mb-2"><label class="form-label">Registrant Name</label><input name="reg_name" class="form-control"
          value="<?php echo htmlspecialchars($domain['registrant_first'] ?? '') . ' ' . htmlspecialchars($domain['registrant_last'] ?? ''); ?>">
      </div>
      <div class="mb-2"><label class="form-label">Registrant Email</label><input name="reg_email" class="form-control"
          value="<?php echo htmlspecialchars($domain['registrant_email'] ?? ''); ?>"></div>
      <div class="mb-2"><label class="form-label">Registrant Phone</label><input name="reg_phone" class="form-control"
          value="<?php echo htmlspecialchars($domain['registrant_phone'] ?? ''); ?>"></div>
      <div class="mb-2"><label class="form-label">Registrant Address</label><input name="reg_address"
          class="form-control" value="<?php echo htmlspecialchars($domain['registrant_address'] ?? ''); ?>"></div>
      <button class="btn btn-sm btn-primary">Save Contacts</button>
    </form>
  </div>
</div>

<hr>
<h5>Activity Log</h5>
<div id="domain-activity-log">Loading...</div>

<!-- Auth code modal -->
<div class="modal" tabindex="-1" id="auth-modal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Auth Code</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <pre id="auth-code"></pre><button class="btn btn-sm btn-outline-secondary" id="copy-auth">Copy</button>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo BASE_PATH; ?>/admin/assets/js/view_domain.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
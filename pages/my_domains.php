<?php
$pageTitle = 'My Domains';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();

// ────────────────────────────────────────────────
// Helper function: days until expiry
// ────────────────────────────────────────────────
function calculateDaysUntilExpiry($expiry_date)
{
  if (empty($expiry_date) || $expiry_date === '0000-00-00' || $expiry_date === '0000-00-00 00:00:00') {
    return 9999; // treat as never expires / very far future
  }

  try {
    $expiry = new DateTime($expiry_date);
    $now = new DateTime();
    $interval = $now->diff($expiry);
    $days = $interval->days;
    return $interval->invert ? -$days : $days;
  } catch (Exception $e) {
    return 9999; // fallback on invalid date
  }
}

// ────────────────────────────────────────────────
// Fetch domains safely with prepared statement
// ────────────────────────────────────────────────
$sql = "SELECT * FROM domains WHERE user_id = ? ORDER BY expiry_date ASC";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
  die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $user['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$domains = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>

<div class="container mt-4">

  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <h1 class="mb-0">My Domains <span class="badge bg-secondary"><?php echo count($domains); ?></span></h1>
    <a href="<?php echo pageUrl('domain_search.php'); ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Register New Domain
    </a>
  </div>

  <?php if (empty($domains)): ?>
    <div class="card text-center py-5 border-0 bg-light">
      <div class="card-body">
        <i class="bi bi-globe fs-1 text-muted mb-3 d-block"></i>
        <h3 class="text-muted mb-3">No domains yet</h3>
        <p class="text-muted mb-4">Start building your online presence today</p>
        <a href="<?php echo pageUrl('domain_search.php'); ?>" class="btn btn-primary btn-lg">
          Search & Register Domains
        </a>
      </div>
    </div>
  <?php else: ?>
    <!-- Bulk Actions Toolbar -->
    <div class="card mb-3 border-primary" id="bulkActionsBar" style="display: none;">
      <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="selectAll">
            <label class="form-check-label" for="selectAll">
              Select All
            </label>
          </div>
          <span class="text-muted">|</span>
          <span id="selectedCount" class="fw-bold">0 selected</span>
          <span class="text-muted">|</span>
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkLock()">
              <i class="bi bi-lock"></i> Lock
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkUnlock()">
              <i class="bi bi-unlock"></i> Unlock
            </button>
          </div>
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm btn-outline-success" onclick="bulkEnablePrivacy()">
              <i class="bi bi-shield-check"></i> Enable Privacy
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkDisablePrivacy()">
              <i class="bi bi-shield-x"></i> Disable Privacy
            </button>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="bulkRenew()">
            <i class="bi bi-arrow-clockwise"></i> Renew Selected
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
            <i class="bi bi-x-circle"></i> Clear Selection
          </button>
        </div>
      </div>
    </div>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
      <?php foreach ($domains as $domain):
        $daysLeft = calculateDaysUntilExpiry($domain['expiry_date']);

        // Visual status logic
        if ($daysLeft < 0) {
          $borderColor = 'border-danger';
          $badgeClass = 'bg-danger';
          $daysDisplay = 'Expired';
        } elseif ($daysLeft <= 30) {
          $borderColor = 'border-warning';
          $badgeClass = 'bg-warning text-dark';
          $daysDisplay = $daysLeft . ' days left';
        } else {
          $borderColor = 'border-success';
          $badgeClass = 'bg-success';
          $daysDisplay = $daysLeft . ' days left';
        }
        ?>
        <div class="col">
          <div class="card h-100 shadow-sm <?php echo $borderColor; ?>" data-domain-id="<?php echo $domain['id']; ?>">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="form-check me-2">
                  <input class="form-check-input domain-checkbox" type="checkbox"
                    value="<?php echo $domain['id']; ?>"
                    id="domain_<?php echo $domain['id']; ?>"
                    data-domain-name="<?php echo htmlspecialchars($domain['domain_name']); ?>">
                </div>
                <h5 class="card-title mb-0 text-truncate pe-2 flex-grow-1"
                  title="<?php echo htmlspecialchars($domain['domain_name']); ?>">
                  <?php echo htmlspecialchars($domain['domain_name']); ?>
                </h5>
                <div class="d-flex gap-1 flex-shrink-0">
                  <?php if (!empty($domain['is_locked'])): ?>
                    <span class="badge bg-secondary">Locked</span>
                  <?php endif; ?>
                  <?php if (!empty($domain['privacy_enabled'])): ?>
                    <span class="badge bg-success" title="WHOIS Privacy Protection Enabled">
                      <i class="bi bi-shield-check"></i> Private
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="mb-3 flex-grow-1">
                <div class="small text-muted mb-1">Expires</div>
                <div class="fw-bold mb-2">
                  <?php echo formatDate($domain['expiry_date']); ?>
                </div>

                <div class="small text-muted mb-1">Remaining</div>
                <div class="fw-bold <?php echo ($daysLeft <= 30 && $daysLeft >= 0) ? 'text-warning' : ''; ?>">
                  <?php echo $daysDisplay; ?>
                </div>

                <div class="mt-3">
                  <span class="badge <?php echo $badgeClass; ?> px-3 py-2">
                    <?php echo ucfirst($domain['status'] ?? 'active'); ?>
                  </span>
                </div>
              </div>

              <div class="mt-auto">
                <a href="<?php echo pageUrl('domain_details.php') . '?id=' . $domain['id']; ?>"
                  class="btn btn-outline-primary w-100">
                  <i class="bi bi-gear me-1"></i> Manage
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script>
const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

// Track selected domains
let selectedDomains = new Set();

// Handle individual checkbox changes
document.querySelectorAll('.domain-checkbox').forEach(checkbox => {
  checkbox.addEventListener('change', function() {
    if (this.checked) {
      selectedDomains.add(parseInt(this.value));
    } else {
      selectedDomains.delete(parseInt(this.value));
    }
    updateBulkActionsBar();
  });
});

// Handle select all
document.getElementById('selectAll')?.addEventListener('change', function() {
  const checkboxes = document.querySelectorAll('.domain-checkbox');
  checkboxes.forEach(cb => {
    cb.checked = this.checked;
    if (this.checked) {
      selectedDomains.add(parseInt(cb.value));
    } else {
      selectedDomains.delete(parseInt(cb.value));
    }
  });
  updateBulkActionsBar();
});

function updateBulkActionsBar() {
  const bar = document.getElementById('bulkActionsBar');
  const count = document.getElementById('selectedCount');

  if (selectedDomains.size > 0) {
    bar.style.display = 'block';
    count.textContent = selectedDomains.size + ' selected';
  } else {
    bar.style.display = 'none';
  }

  // Update "Select All" checkbox state
  const selectAllCheckbox = document.getElementById('selectAll');
  const totalCheckboxes = document.querySelectorAll('.domain-checkbox').length;
  if (selectAllCheckbox) {
    selectAllCheckbox.checked = selectedDomains.size === totalCheckboxes && totalCheckboxes > 0;
    selectAllCheckbox.indeterminate = selectedDomains.size > 0 && selectedDomains.size < totalCheckboxes;
  }
}

function clearSelection() {
  selectedDomains.clear();
  document.querySelectorAll('.domain-checkbox').forEach(cb => cb.checked = false);
  updateBulkActionsBar();
}

async function performBulkAction(action, confirmMessage) {
  if (selectedDomains.size === 0) {
    alert('Please select at least one domain');
    return;
  }

  if (confirmMessage && !confirm(confirmMessage)) {
    return;
  }

  const domainIds = Array.from(selectedDomains);

  try {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('domain_ids', JSON.stringify(domainIds));

    const response = await fetch(basePath + '/api/bulk_operations.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });

    const data = await response.json();

    if (data.success) {
      alert(data.message || 'Operation completed successfully');
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Operation failed'));
    }
  } catch (error) {
    alert('Error: ' + error.message);
  }
}

function bulkLock() {
  performBulkAction('lock', `Lock ${selectedDomains.size} domain(s)? This will prevent unauthorized transfers.`);
}

function bulkUnlock() {
  performBulkAction('unlock', `Unlock ${selectedDomains.size} domain(s)? This will allow transfers.`);
}

function bulkEnablePrivacy() {
  performBulkAction('enable_privacy', `Enable WHOIS privacy for ${selectedDomains.size} domain(s)? Privacy fee applies.`);
}

function bulkDisablePrivacy() {
  performBulkAction('disable_privacy', `Disable WHOIS privacy for ${selectedDomains.size} domain(s)?`);
}

function bulkRenew() {
  performBulkAction('renew', `Add ${selectedDomains.size} domain(s) to cart for renewal?`);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
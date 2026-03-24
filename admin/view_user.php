<?php
$pageTitle = 'View User';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    echo '<div class="alert alert-danger">Invalid user ID.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

global $conn;
$user = null;
if ($stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) $user = $res->fetch_assoc();
    $stmt->close();
} else {
    $res  = mysqli_query($conn, "SELECT * FROM users WHERE id = $id LIMIT 1");
    $user = $res ? mysqli_fetch_assoc($res) : null;
}

if (!$user) {
    echo '<div class="alert alert-danger">User not found.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$walletBalance = (float) ($user['wallet_balance'] ?? 0.00);
$csrfField     = getCSRFField();
$displayName   = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['name'] ?? $user['email']));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3"><i class="bi bi-person-circle me-2"></i><?php echo $displayName; ?></h1>
  <div class="d-flex gap-2">
    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary">
      <i class="bi bi-pencil me-1"></i> Edit
    </a>
    <a href="manage_users.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back to Users
    </a>
  </div>
</div>

<div class="row g-4">

  <!-- Account Details -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-person me-1"></i> Account Details</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th style="width:36%">ID</th><td>#<?php echo $user['id']; ?></td></tr>
          <tr><th>Email</th><td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td></tr>
          <tr><th>Name</th><td><?php echo $displayName; ?></td></tr>
          <tr><th>Phone</th><td><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td></tr>
          <tr><th>Country</th><td><?php echo htmlspecialchars($user['country'] ?? '—'); ?></td></tr>
          <tr><th>Status</th>
            <td><?php
              $st = $user['status'] ?? 'active';
              $sc = $st === 'active' ? 'success' : ($st === 'suspended' ? 'warning' : 'secondary');
              echo '<span class="badge bg-' . $sc . '">' . ucfirst($st) . '</span>';
            ?></td>
          </tr>
          <tr><th>Role</th>
            <td><?php echo !empty($user['is_admin'])
              ? '<span class="badge bg-danger">Admin</span>'
              : '<span class="badge bg-secondary">User</span>'; ?></td>
          </tr>
          <tr><th>Registered</th><td><?php echo htmlspecialchars($user['created_at'] ?? '—'); ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <!-- Wallet -->
  <div class="col-md-6">
    <div class="card h-100 border-success">
      <div class="card-header bg-success text-white fw-semibold">
        <i class="bi bi-wallet2 me-1"></i> Wallet Balance
      </div>
      <div class="card-body">
        <div class="text-center mb-4">
          <h2 class="fw-bold text-success" id="walletBalanceDisplay">
            $<?php echo number_format($walletBalance, 2); ?>
          </h2>
          <p class="text-muted small">Current prepaid credit balance</p>
        </div>

        <h6 class="fw-semibold mb-3"><i class="bi bi-plus-circle me-1"></i> Add Funds Manually</h6>
        <form id="addFundsForm">
          <?php echo $csrfField; ?>
          <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
          <div class="mb-3">
            <label class="form-label">Amount (USD)</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="amount" id="creditAmount" class="form-control"
                min="0.01" max="99999.99" step="0.01" placeholder="0.00" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Note / Reason</label>
            <textarea name="description" id="creditDesc" class="form-control" rows="2"
              placeholder="e.g. Cash received, account credit…"></textarea>
          </div>
          <button type="submit" class="btn btn-success w-100" id="addFundsBtn">
            <i class="bi bi-plus-circle me-1"></i> Add Funds
          </button>
        </form>
        <div id="addFundsMsg" class="mt-2"></div>
      </div>
    </div>
  </div>

  <!-- Wallet History -->
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1"></i> Wallet Transaction History</span>
        <span class="badge bg-secondary" id="walletTxnCount">Loading…</span>
      </div>
      <div class="card-body p-0" id="walletHistoryContainer">
        <div class="text-center py-4 text-muted">
          <div class="spinner-border spinner-border-sm me-2"></div> Loading…
        </div>
      </div>
      <div class="card-footer text-center d-none" id="walletHistoryPager">
        <button class="btn btn-sm btn-outline-secondary" id="loadMoreWallet">Load More</button>
      </div>
    </div>
  </div>

</div>

<script>
const USER_ID    = <?php echo (int) $user['id']; ?>;
let walletPage   = 1;
let walletTotal  = 0;
let walletLoaded = 0;

document.getElementById('addFundsForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('addFundsBtn');
  const msg = document.getElementById('addFundsMsg');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing…';
  msg.innerHTML = '';

  try {
    const fd   = new FormData(this);
    const resp = await fetch(window.BASE_PATH + '/admin/api/wallet_credit.php', { method:'POST', body:fd, credentials:'same-origin' });
    const data = await resp.json();
    if (data.success) {
      const nb = parseFloat(data.new_balance).toFixed(2);
      msg.innerHTML = '<div class="alert alert-success py-2 mt-2">'
        + '<i class="bi bi-check-circle me-1"></i> Credited! New balance: <strong>$' + nb + '</strong></div>';
      document.getElementById('walletBalanceDisplay').textContent = '$' + nb;
      document.getElementById('creditAmount').value = '';
      document.getElementById('creditDesc').value   = '';
      walletPage = 1; walletLoaded = 0;
      document.getElementById('walletHistoryContainer').innerHTML = '';
      loadWalletHistory();
    } else {
      msg.innerHTML = '<div class="alert alert-danger py-2 mt-2"><i class="bi bi-x-circle me-1"></i> ' + (data.message || 'Failed') + '</div>';
    }
  } catch(err) {
    msg.innerHTML = '<div class="alert alert-danger py-2 mt-2">Error: ' + err.message + '</div>';
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Add Funds';
});

async function loadWalletHistory() {
  try {
    const resp = await fetch(window.BASE_PATH + '/admin/api/wallet_history.php?user_id=' + USER_ID + '&page=' + walletPage, { credentials:'same-origin' });
    const data = await resp.json();
    if (!data.success) throw new Error(data.message || 'Load failed');

    walletTotal  = data.total  || 0;
    walletLoaded += (data.transactions || []).length;

    document.getElementById('walletTxnCount').textContent = walletTotal + ' record' + (walletTotal !== 1 ? 's' : '');

    const container = document.getElementById('walletHistoryContainer');
    if (walletTotal === 0) {
      container.innerHTML = '<div class="text-center py-4 text-muted">'
        + '<i class="bi bi-wallet2" style="font-size:2rem;"></i>'
        + '<p class="mt-2 mb-0 small">No wallet transactions yet</p></div>';
      return;
    }

    if (walletPage === 1) {
      container.innerHTML = '<div class="table-responsive">'
        + '<table class="table table-hover align-middle mb-0" id="walletTxnTable">'
        + '<thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Description</th><th>Added By</th></tr></thead>'
        + '<tbody id="walletTxnBody"></tbody></table></div>';
    }

    const tbody = document.getElementById('walletTxnBody');
    (data.transactions || []).forEach(function(t) {
      const isCredit  = t.type === 'credit' || t.type === 'refund';
      const typeBadge = t.type === 'credit'  ? '<span class="badge bg-success">Credit</span>'
                      : t.type === 'refund'  ? '<span class="badge bg-warning text-dark">Refund</span>'
                      : '<span class="badge bg-danger">Debit</span>';
      const amtCls   = isCredit ? 'text-success' : 'text-danger';
      const amtSign  = isCredit ? '+' : '-';
      const dateStr  = new Date(t.created_at).toLocaleString('en-US', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
      tbody.insertAdjacentHTML('beforeend',
        '<tr>'
        + '<td class="text-nowrap small">' + dateStr + '</td>'
        + '<td>' + typeBadge + '</td>'
        + '<td class="fw-bold ' + amtCls + '">' + amtSign + '$' + parseFloat(t.amount).toFixed(2) + '</td>'
        + '<td class="text-muted">$' + parseFloat(t.balance_after).toFixed(2) + '</td>'
        + '<td class="small">' + (t.description || '—') + '</td>'
        + '<td class="small text-muted">' + (t.added_by_name || 'System') + '</td>'
        + '</tr>'
      );
    });

    const pager = document.getElementById('walletHistoryPager');
    if (walletLoaded < walletTotal) pager.classList.remove('d-none');
    else pager.classList.add('d-none');

    walletPage++;
  } catch(err) {
    document.getElementById('walletHistoryContainer').innerHTML =
      '<div class="text-center py-3 text-danger"><i class="bi bi-exclamation-triangle me-1"></i> ' + err.message + '</div>';
  }
}

document.getElementById('loadMoreWallet').addEventListener('click', loadWalletHistory);
loadWalletHistory();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

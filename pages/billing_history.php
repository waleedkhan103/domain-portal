<?php
$pageTitle = 'Billing History';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/stripe.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int) $user['id'];

// Ensure transactions table exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, order_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL, amount DECIMAL(10,2) NOT NULL, description VARCHAR(255),
    payment_method VARCHAR(50) DEFAULT NULL, wallet_amount DECIMAL(10,2) DEFAULT 0.00,
    stripe_payment_id VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_user (user_id)
)");

$sql          = "SELECT * FROM transactions WHERE user_id = $userId ORDER BY created_at DESC LIMIT 100";
$result       = mysqli_query($conn, $sql);
$transactions = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

$walletBalance  = (float) ($user['wallet_balance'] ?? 0.00);
$stripeEnabled  = STRIPE_ENABLED;
$stripePublicKey = STRIPE_PUBLIC_KEY;

$walletTxns = [];
$chk = mysqli_query($conn, "SHOW TABLES LIKE 'wallet_transactions'");
if ($chk && mysqli_num_rows($chk) > 0) {
    $wRes       = mysqli_query($conn, "SELECT * FROM wallet_transactions WHERE user_id = $userId ORDER BY created_at DESC LIMIT 100");
    $walletTxns = $wRes ? mysqli_fetch_all($wRes, MYSQLI_ASSOC) : [];
}

$pmLabels = [
    'wallet'       => ['txt' => 'Wallet',        'cls' => 'bg-success'],
    'stripe'       => ['txt' => 'Card (Stripe)',  'cls' => 'bg-primary'],
    'mixed'        => ['txt' => 'Wallet + Card',  'cls' => 'bg-info text-dark'],
    'admin_credit' => ['txt' => 'Admin Credit',   'cls' => 'bg-warning text-dark'],
];
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><i class="bi bi-receipt"></i> Billing History</h1>
    <a href="<?php echo pageUrl('dashboard.php'); ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
  </div>

  <!-- Wallet balance banner -->
  <div class="card mb-4 border-success">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-1"><i class="bi bi-wallet2 text-success me-2"></i> Wallet Balance</h5>
        <p class="text-muted small mb-0">Your prepaid credit — usable at checkout</p>
      </div>
      <div class="text-end d-flex align-items-center gap-3">
        <div>
          <h2 class="mb-0 fw-bold text-success" id="walletBalanceDisplay">$<?php echo number_format($walletBalance, 2); ?></h2>
          <small class="text-muted"><?php echo $walletBalance > 0 ? 'Available for checkout' : 'Add funds to get started'; ?></small>
        </div>
        <?php if ($stripeEnabled): ?>
          <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#topupModal">
            <i class="bi bi-plus-circle me-1"></i> Add Funds
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($stripeEnabled): ?>
  <!-- Wallet Top-Up Modal -->
  <div class="modal fade" id="topupModal" tabindex="-1" aria-labelledby="topupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="topupModalLabel"><i class="bi bi-wallet2 me-1"></i> Add Funds to Wallet</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="topupSuccess" class="alert alert-success d-none">
            <i class="bi bi-check-circle me-1"></i> <span id="topupSuccessMsg"></span>
          </div>
          <div id="topupError" class="alert alert-danger d-none">
            <i class="bi bi-exclamation-triangle me-1"></i> <span id="topupErrorMsg"></span>
          </div>
          <form id="topupForm">
            <div class="mb-3">
              <label class="form-label fw-semibold">Amount (USD)</label>
              <div class="d-flex gap-2 mb-2">
                <button type="button" class="btn btn-outline-success topup-preset" data-amount="10">$10</button>
                <button type="button" class="btn btn-outline-success topup-preset" data-amount="25">$25</button>
                <button type="button" class="btn btn-outline-success topup-preset" data-amount="50">$50</button>
                <button type="button" class="btn btn-outline-success topup-preset" data-amount="100">$100</button>
                <button type="button" class="btn btn-outline-success topup-preset" data-amount="250">$250</button>
              </div>
              <input type="number" id="topupAmount" class="form-control" min="5" max="10000" step="0.01" value="25" placeholder="Enter amount ($5 - $10,000)">
              <div class="form-text">Minimum: $5.00 | Maximum: $10,000.00</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Card Details</label>
              <div id="topup-card-element" class="form-control" style="height:44px;padding-top:11px;"></div>
              <div id="topup-card-errors" class="text-danger small mt-1"></div>
            </div>
            <div class="mb-3">
              <label class="form-label">Cardholder Name</label>
              <input type="text" id="topupCardName" class="form-control" placeholder="Name on card">
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="topupPayBtn">
            <i class="bi bi-credit-card me-1"></i> <span id="topupPayLabel">Pay $25.00</span>
          </button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-4" id="billingTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders-pane"
        type="button" role="tab" aria-controls="orders-pane" aria-selected="true">
        <i class="bi bi-receipt me-1"></i> Order Payments
        <span class="badge bg-secondary ms-1"><?php echo count($transactions); ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="wallet-tab" data-bs-toggle="tab" data-bs-target="#wallet-pane"
        type="button" role="tab" aria-controls="wallet-pane" aria-selected="false">
        <i class="bi bi-wallet2 me-1"></i> Wallet History
        <span class="badge bg-secondary ms-1"><?php echo count($walletTxns); ?></span>
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- Order Payments -->
    <div class="tab-pane fade show active" id="orders-pane" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <?php if (empty($transactions)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-inbox" style="font-size:4rem;"></i>
              <p class="mt-2">No order payments yet</p>
              <a href="<?php echo pageUrl('domain_search.php'); ?>" class="btn btn-primary">Search Domains</a>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Paid Via</th>
                    <th>Wallet Used</th>
                    <th class="text-end">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($transactions as $t): ?>
                    <?php
                      $pm     = $t['payment_method'] ?? '';
                      $pmInfo = $pmLabels[$pm] ?? ['txt' => ucfirst($pm ?: 'Order'), 'cls' => 'bg-secondary'];
                      $wUsed  = (float) ($t['wallet_amount'] ?? 0);
                    ?>
                    <tr>
                      <td class="text-nowrap"><?php echo date('M j, Y g:i A', strtotime($t['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($t['description'] ?? '-'); ?></td>
                      <td><span class="badge <?php echo $pmInfo['cls']; ?>"><?php echo htmlspecialchars($pmInfo['txt']); ?></span></td>
                      <td>
                        <?php if ($wUsed > 0): ?>
                          <span class="text-success small fw-bold">-$<?php echo number_format($wUsed, 2); ?></span>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end fw-bold">$<?php echo number_format($t['amount'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Wallet History -->
    <div class="tab-pane fade" id="wallet-pane" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <?php if (empty($walletTxns)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-wallet2" style="font-size:4rem;"></i>
              <p class="mt-2">No wallet transactions yet</p>
              <p class="small">Credits added by admin and payments made via wallet appear here.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Balance After</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($walletTxns as $wt): ?>
                    <tr>
                      <td class="text-nowrap"><?php echo date('M j, Y g:i A', strtotime($wt['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($wt['description'] ?? '-'); ?></td>
                      <td>
                        <?php if ($wt['type'] === 'credit'): ?>
                          <span class="badge bg-success"><i class="bi bi-plus-circle me-1"></i>Credit</span>
                        <?php elseif ($wt['type'] === 'debit'): ?>
                          <span class="badge bg-danger"><i class="bi bi-dash-circle me-1"></i>Debit</span>
                        <?php else: ?>
                          <span class="badge bg-warning text-dark"><i class="bi bi-arrow-clockwise me-1"></i>Refund</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end fw-bold <?php echo $wt['type'] === 'debit' ? 'text-danger' : 'text-success'; ?>">
                        <?php echo $wt['type'] === 'debit' ? '-' : '+'; ?>$<?php echo number_format($wt['amount'], 2); ?>
                      </td>
                      <td class="text-end text-muted">$<?php echo number_format($wt['balance_after'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->
</div>

<?php if ($stripeEnabled): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';
const stripe   = Stripe(<?php echo json_encode($stripePublicKey); ?>);
const elements = stripe.elements();
const topupCard = elements.create('card', {
  style: { base: { fontSize: '16px', color: '#495057', '::placeholder': { color: '#aab7c4' } } }
});

// Mount card when modal opens
document.getElementById('topupModal').addEventListener('shown.bs.modal', function() {
  topupCard.mount('#topup-card-element');
});
document.getElementById('topupModal').addEventListener('hidden.bs.modal', function() {
  topupCard.unmount();
  document.getElementById('topupSuccess').classList.add('d-none');
  document.getElementById('topupError').classList.add('d-none');
});

topupCard.on('change', function(ev) {
  document.getElementById('topup-card-errors').textContent = ev.error ? ev.error.message : '';
});

// Preset amount buttons
document.querySelectorAll('.topup-preset').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('topupAmount').value = this.dataset.amount;
    document.querySelectorAll('.topup-preset').forEach(function(b) { b.classList.remove('active'); });
    this.classList.add('active');
    updateTopupLabel();
  });
});

document.getElementById('topupAmount').addEventListener('input', updateTopupLabel);

function updateTopupLabel() {
  const amt = parseFloat(document.getElementById('topupAmount').value) || 0;
  document.getElementById('topupPayLabel').textContent = 'Pay $' + amt.toFixed(2);
}

// Pay button
document.getElementById('topupPayBtn').addEventListener('click', async function() {
  const btn      = this;
  const amount   = parseFloat(document.getElementById('topupAmount').value) || 0;
  const errDiv   = document.getElementById('topupError');
  const succDiv  = document.getElementById('topupSuccess');

  errDiv.classList.add('d-none');
  succDiv.classList.add('d-none');

  if (amount < 5 || amount > 10000) {
    errDiv.classList.remove('d-none');
    document.getElementById('topupErrorMsg').textContent = 'Amount must be between $5.00 and $10,000.00';
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

  try {
    // Step 1: Create PaymentIntent
    const fd1 = new FormData();
    fd1.append('action', 'create_intent');
    fd1.append('amount', amount.toFixed(2));
    const r1 = await fetch(basePath + '/api/wallet_topup.php', { method: 'POST', body: fd1, credentials: 'same-origin' });
    const d1 = await r1.json();
    if (!d1.success) throw new Error(d1.message || 'Could not create payment');

    // Step 2: Confirm card payment
    const cardName = (document.getElementById('topupCardName') || {}).value || '';
    const { paymentIntent, error } = await stripe.confirmCardPayment(d1.data.client_secret, {
      payment_method: { card: topupCard, billing_details: { name: cardName } }
    });
    if (error) throw new Error(error.message);
    if (paymentIntent.status !== 'succeeded') throw new Error('Payment not completed');

    // Step 3: Confirm top-up on server
    const fd2 = new FormData();
    fd2.append('action', 'confirm_topup');
    fd2.append('stripe_payment_intent_id', paymentIntent.id);
    fd2.append('amount', amount.toFixed(2));
    const r2 = await fetch(basePath + '/api/wallet_topup.php', { method: 'POST', body: fd2, credentials: 'same-origin' });
    const d2 = await r2.json();
    if (!d2.success) throw new Error(d2.message || 'Could not credit wallet');

    // Success
    succDiv.classList.remove('d-none');
    document.getElementById('topupSuccessMsg').textContent =
      '$' + amount.toFixed(2) + ' added to your wallet! New balance: $' + parseFloat(d2.data.new_balance).toFixed(2);
    document.getElementById('walletBalanceDisplay').textContent = '$' + parseFloat(d2.data.new_balance).toFixed(2);

    // Reload after 2 seconds to update transaction history
    setTimeout(function() { location.reload(); }, 2000);

  } catch(err) {
    errDiv.classList.remove('d-none');
    document.getElementById('topupErrorMsg').textContent = err.message;
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-credit-card me-1"></i> <span id="topupPayLabel">Pay $' + amount.toFixed(2) + '</span>';
  }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

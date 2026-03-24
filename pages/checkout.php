<?php
$pageTitle = 'Checkout';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/stripe.php';
requireLogin();

$user = getCurrentUser();

$sql       = "SELECT * FROM cart WHERE user_id = {$user['id']}";
$result    = mysqli_query($conn, $sql);
$cartItems = mysqli_fetch_all($result, MYSQLI_ASSOC);

if (empty($cartItems)) {
    header('Location: ' . BASE_PATH . '/pages/cart.php');
    exit;
}

$total           = 0;
$totalPrivacyFee = 0;
foreach ($cartItems as $item) {
    $total           += ($item['price'] * (int) $item['period']);
    $pricing          = getTldPricing($item['domain_name']);
    $totalPrivacyFee += (float) $pricing['privacy_price'];
}
$avgPrivacyFee   = count($cartItems) > 0 ? ($totalPrivacyFee / count($cartItems)) : 2.99;
$walletBalance   = (float) ($user['wallet_balance'] ?? 0.00);
$stripePublicKey = STRIPE_PUBLIC_KEY;
$stripeEnabled   = STRIPE_ENABLED;
$defaultMethod   = ($walletBalance >= $total) ? 'wallet' : ($stripeEnabled ? 'stripe' : 'wallet');
?>

<div class="container mt-5">
  <div class="row">
    <!-- Left column -->
    <div class="col-md-8">

      <h3>Order Summary</h3>
      <div class="card mb-4">
        <div class="card-body">
          <table class="table mb-0">
            <thead><tr><th>Domain</th><th>Type</th><th>Period</th><th>Price</th></tr></thead>
            <tbody>
              <?php foreach ($cartItems as $item): ?>
                <tr>
                  <td><?php echo htmlspecialchars($item['domain_name']); ?></td>
                  <td><?php echo htmlspecialchars($item['operation_type']); ?></td>
                  <td><?php echo (int) $item['period']; ?> yr</td>
                  <td>$<?php echo number_format($item['price'], 2); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <h3 class="mt-4">Domain Contact Information</h3>
      <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <span><i class="bi bi-person-vcard me-1"></i> Registrant Details</span>
          <small class="text-muted">Pre-filled from your profile</small>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">First Name <span class="text-danger">*</span></label>
              <input type="text" name="reg_first" class="form-control" required
                value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Last Name <span class="text-danger">*</span></label>
              <input type="text" name="reg_last" class="form-control" required
                value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="reg_email" class="form-control" required
                value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone <span class="text-danger">*</span></label>
              <input type="text" name="reg_phone" class="form-control" required placeholder="+1.2125551234"
                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <input type="text" name="reg_address" class="form-control"
                value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
            </div>
          </div>
        </div>
      </div>

      <h3 class="mt-4">Privacy Protection</h3>
      <div class="card mb-4">
        <div class="card-header bg-light">
          <i class="bi bi-shield-check me-1"></i> WHOIS Privacy Protection
        </div>
        <div class="card-body">
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="privacyProtection" name="privacy_protection" value="1">
            <label class="form-check-label" for="privacyProtection">
              <strong>Enable WHOIS Privacy Protection
                <span class="badge bg-success">+$<?php echo number_format($totalPrivacyFee, 2); ?> total</span>
              </strong>
              <?php if (count($cartItems) > 1): ?>
                <span class="small text-muted">(avg $<?php echo number_format($avgPrivacyFee, 2); ?>/domain/yr)</span>
              <?php endif; ?>
              <div class="small text-muted mt-1">
                <i class="bi bi-info-circle me-1"></i>
                Hide your contact info from public WHOIS lookups.
              </div>
            </label>
          </div>
        </div>
      </div>

      <h3>Payment</h3>
      <div class="card">
        <div class="card-body">

          <?php if ($walletBalance > 0): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
              <i class="bi bi-wallet2 fs-5"></i>
              <div>
                <strong>Wallet Balance: $<?php echo number_format($walletBalance, 2); ?></strong>
                <div class="small">You can use your wallet to pay for this order.</div>
              </div>
            </div>
          <?php endif; ?>

          <form id="checkoutForm">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="promo_code" id="promoCodeInput" value="">
            <input type="hidden" name="privacy_protection" id="privacyProtectionInput" value="0">
            <input type="hidden" name="payment_method" id="paymentMethodInput" value="<?php echo htmlspecialchars($defaultMethod); ?>">
            <input type="hidden" name="stripe_payment_intent_id" id="stripePaymentIntentId" value="">

            <!-- Payment method radios -->
            <div class="mb-4">
              <label class="form-label fw-semibold">Payment Method</label>
              <div class="d-flex flex-column gap-2">

                <?php if ($walletBalance >= $total && $walletBalance > 0): ?>
                  <label class="d-flex align-items-center gap-2 border rounded p-3 cursor-pointer <?php echo $defaultMethod === 'wallet' ? 'border-success bg-success bg-opacity-10' : ''; ?>" id="lbl_wallet">
                    <input type="radio" name="pm_radio" value="wallet" <?php echo $defaultMethod === 'wallet' ? 'checked' : ''; ?>>
                    <div>
                      <strong><i class="bi bi-wallet2 text-success me-1"></i> Pay with Wallet</strong>
                      <span class="badge bg-success ms-1">No card needed</span>
                      <div class="small text-muted">Deduct $<span class="walletDeductAmount"><?php echo number_format($total, 2); ?></span> from your balance</div>
                    </div>
                  </label>
                <?php endif; ?>

                <?php if ($walletBalance > 0 && $walletBalance < $total && $stripeEnabled): ?>
                  <label class="d-flex align-items-center gap-2 border rounded p-3 cursor-pointer" id="lbl_mixed">
                    <input type="radio" name="pm_radio" value="mixed" <?php echo $defaultMethod === 'mixed' ? 'checked' : ''; ?>>
                    <div>
                      <strong><i class="bi bi-wallet2 text-success me-1"></i> Wallet + Card</strong>
                      <div class="small text-muted">
                        Use $<?php echo number_format($walletBalance, 2); ?> from wallet,
                        charge $<span id="mixedCardAmt"><?php echo number_format(max(0, $total - $walletBalance), 2); ?></span> to card
                      </div>
                    </div>
                  </label>
                <?php endif; ?>

                <?php if ($stripeEnabled): ?>
                  <label class="d-flex align-items-center gap-2 border rounded p-3 cursor-pointer" id="lbl_stripe">
                    <input type="radio" name="pm_radio" value="stripe" <?php echo $defaultMethod === 'stripe' ? 'checked' : ''; ?>>
                    <div>
                      <strong><i class="bi bi-credit-card text-primary me-1"></i> Pay by Card</strong>
                      <div class="small text-muted">Secure payment via Stripe</div>
                    </div>
                  </label>
                <?php endif; ?>

                <?php if (!$stripeEnabled && $walletBalance <= 0): ?>
                  <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    No payment methods available. Contact support or add wallet funds.
                  </div>
                <?php endif; ?>

              </div>
            </div>

            <!-- Stripe Elements card field -->
            <?php if ($stripeEnabled): ?>
              <div id="stripeCardSection" class="mb-3" style="display:none;">
                <label class="form-label">Card Details</label>
                <div id="stripe-card-element" class="form-control" style="height:44px;padding-top:11px;"></div>
                <div id="stripe-card-errors" class="text-danger small mt-1"></div>
                <div class="mt-3">
                  <label class="form-label">Cardholder Name</label>
                  <input type="text" id="cardName" class="form-control" placeholder="Name as it appears on card">
                </div>
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mt-4">
              <div>
                <strong>Total: $<span id="mainTotal"><?php echo number_format($total, 2); ?></span></strong>
                <div id="paymentBreakdown" class="small text-muted mt-1"></div>
              </div>
              <button type="submit" class="btn btn-success btn-lg" id="payBtn">
                <i class="bi bi-lock me-1"></i> <span id="payBtnLabel">Place Order</span>
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>

    <!-- Right column -->
    <div class="col-md-4">
      <h4>Order Details</h4>
      <div class="card mb-3">
        <div class="card-body">
          <p class="mb-2">Items: <?php echo count($cartItems); ?></p>
          <p class="mb-2">Subtotal: $<span id="subtotalVal"><?php echo number_format($total, 2); ?></span></p>
          <div class="input-group mb-2">
            <input type="text" class="form-control" id="promoInput" placeholder="Promo code">
            <button class="btn btn-outline-secondary" type="button" id="applyPromoBtn">Apply</button>
          </div>
          <div id="promoMessage" class="small"></div>
          <p class="mb-0 mt-2 fw-bold">Total: $<span id="totalVal"><?php echo number_format($total, 2); ?></span></p>
          <?php if ($walletBalance > 0): ?>
            <hr class="my-2">
            <p class="mb-0 small text-success">
              <i class="bi bi-wallet2 me-1"></i>Wallet: $<?php echo number_format($walletBalance, 2); ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
      <div class="card bg-light border-0">
        <div class="card-body small text-muted">
          <i class="bi bi-shield-lock me-1"></i> Card payments secured by Stripe.<br>
          Your card details are never stored on our servers.
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($stripeEnabled): ?>
  <script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script>
const basePath        = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';
const STRIPE_ENABLED  = <?php echo $stripeEnabled ? 'true' : 'false'; ?>;
const STRIPE_PK       = <?php echo json_encode($stripePublicKey); ?>;
const WALLET_BALANCE  = <?php echo json_encode($walletBalance); ?>;

let subtotal    = <?php echo (float) $total; ?>;
let discount    = 0;
const TOTAL_PRIVACY_FEE = <?php echo (float) $totalPrivacyFee; ?>;
let privacyFee  = 0;

// Stripe setup
let stripe = null, cardElement = null;
if (STRIPE_ENABLED && STRIPE_PK) {
  stripe = Stripe(STRIPE_PK);
  const elements = stripe.elements();
  cardElement = elements.create('card', {
    style: { base: { fontSize: '16px', color: '#495057', '::placeholder': { color: '#aab7c4' } } }
  });
  cardElement.mount('#stripe-card-element');
  cardElement.on('change', function(ev) {
    document.getElementById('stripe-card-errors').textContent = ev.error ? ev.error.message : '';
  });
}

// Highlight selected payment method label
document.querySelectorAll('input[name="pm_radio"]').forEach(function(r) {
  r.addEventListener('change', function() {
    document.querySelectorAll('[id^="lbl_"]').forEach(function(l) {
      l.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10', 'border-success', 'bg-success');
    });
    const lbl = document.getElementById('lbl_' + r.value);
    if (lbl) {
      lbl.classList.add(r.value === 'wallet' ? 'border-success' : 'border-primary');
      lbl.classList.add(r.value === 'wallet' ? 'bg-success' : 'bg-primary', 'bg-opacity-10');
    }
    updatePaymentUI();
  });
});
updatePaymentUI();

function getMethod() {
  const c = document.querySelector('input[name="pm_radio"]:checked');
  return c ? c.value : 'stripe';
}

function currentTotal() { return Math.max(0, subtotal + privacyFee - discount); }

function updatePaymentUI() {
  const method  = getMethod();
  const total   = currentTotal();
  const cardSec = document.getElementById('stripeCardSection');
  const bd      = document.getElementById('paymentBreakdown');
  const label   = document.getElementById('payBtnLabel');

  document.getElementById('paymentMethodInput').value = method;

  if (cardSec) cardSec.style.display = (method === 'stripe' || method === 'mixed') ? 'block' : 'none';

  if (method === 'wallet') {
    if (label) label.textContent = 'Pay with Wallet';
    if (bd) bd.textContent = 'Full amount deducted from wallet';
  } else if (method === 'mixed') {
    const w = Math.min(WALLET_BALANCE, total);
    const c = Math.max(0, total - w);
    if (label) label.textContent = 'Pay $' + c.toFixed(2) + ' by Card';
    if (bd) bd.innerHTML = '<span class="text-success">Wallet: -$' + w.toFixed(2) + '</span>&nbsp; Card: $' + c.toFixed(2);
    const mca = document.getElementById('mixedCardAmt');
    if (mca) mca.textContent = c.toFixed(2);
  } else {
    if (label) label.textContent = 'Pay $' + total.toFixed(2) + ' by Card';
    if (bd) bd.textContent = '';
  }

  // Update wallet deduct amount badges
  document.querySelectorAll('.walletDeductAmount').forEach(function(el) {
    el.textContent = Math.min(WALLET_BALANCE, total).toFixed(2);
  });
}

// Privacy checkbox
document.getElementById('privacyProtection').addEventListener('change', function() {
  privacyFee = this.checked ? TOTAL_PRIVACY_FEE : 0;
  document.getElementById('privacyProtectionInput').value = this.checked ? '1' : '0';
  updateTotals();
});

function updateTotals() {
  const t = currentTotal();
  document.getElementById('totalVal').textContent  = t.toFixed(2);
  document.getElementById('mainTotal').textContent = t.toFixed(2);

  // Privacy row
  const privRow = document.getElementById('privacyFeeRow');
  if (privacyFee > 0) {
    if (!privRow) {
      const row = document.createElement('p');
      row.id = 'privacyFeeRow'; row.className = 'mb-2 text-success';
      row.innerHTML = '<small>Privacy: +$<span id="privacyFeeVal">' + privacyFee.toFixed(2) + '</span></small>';
      document.getElementById('subtotalVal').parentElement.insertAdjacentElement('afterend', row);
    } else {
      document.getElementById('privacyFeeVal').textContent = privacyFee.toFixed(2);
    }
  } else if (privRow) { privRow.remove(); }

  updatePaymentUI();
}

// Promo code
document.getElementById('applyPromoBtn').addEventListener('click', async function() {
  const code = document.getElementById('promoInput').value.trim();
  const msg  = document.getElementById('promoMessage');
  if (!code) { msg.className = 'small text-danger'; msg.textContent = 'Enter a promo code'; return; }
  try {
    const fd = new FormData();
    fd.append('code', code); fd.append('subtotal', subtotal);
    const r = await fetch(basePath + '/api/promo_validate.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    if (d.success) {
      discount = d.data.discount;
      document.getElementById('promoCodeInput').value = code;
      msg.className = 'small text-success';
      msg.textContent = d.message + ' (-$' + discount.toFixed(2) + ')';
      document.getElementById('promoInput').disabled    = true;
      document.getElementById('applyPromoBtn').disabled = true;
      updateTotals();
    } else {
      msg.className = 'small text-danger'; msg.textContent = d.message;
    }
  } catch(err) { msg.className = 'small text-danger'; msg.textContent = 'Could not validate promo'; }
});

// Form submit
document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const payBtn = document.getElementById('payBtn');
  const method = getMethod();
  const total  = currentTotal();

  payBtn.disabled = true;
  payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing…';

  try {
    const form = new FormData(this);
    ['reg_first','reg_last','reg_email','reg_phone','reg_address'].forEach(function(n) {
      const el = document.querySelector('[name="' + n + '"]');
      if (el) form.set(n, el.value.trim());
    });

    // Stripe payment (card or mixed)
    if ((method === 'stripe' || method === 'mixed') && STRIPE_ENABLED && stripe) {
      const cardAmt = method === 'mixed'
        ? Math.max(0, total - Math.min(WALLET_BALANCE, total))
        : total;

      if (cardAmt > 0) {
        // Create PaymentIntent
        const ifd = new FormData();
        ifd.append('amount', cardAmt.toFixed(2));
        ifd.append('currency', 'usd');
        const iResp = await fetch(basePath + '/api/stripe_intent.php', { method:'POST', body:ifd, credentials:'same-origin' });
        const iData = await iResp.json();
        if (!iData.success) throw new Error(iData.message || 'Could not create payment intent');

        // Confirm card payment
        const cardNameVal = (document.getElementById('cardName') || {}).value || '';
        const { paymentIntent, error } = await stripe.confirmCardPayment(iData.data.client_secret, {
          payment_method: { card: cardElement, billing_details: { name: cardNameVal } }
        });
        if (error) throw new Error(error.message);
        if (paymentIntent.status !== 'succeeded') throw new Error('Payment not completed');
        form.set('stripe_payment_intent_id', paymentIntent.id);
      }
    }

    const resp = await fetch(basePath + '/api/checkout.php', { method:'POST', body:form, credentials:'same-origin' });
    const data = await resp.json();
    if (data.success) {
      window.location.href = basePath + ((data.data && data.data.redirect) ? data.data.redirect : '/pages/dashboard.php');
    } else {
      if ((data.message || '').toLowerCase().includes('not authenticated')) {
        window.location.href = basePath + '/pages/login.php'; return;
      }
      throw new Error(data.message || 'Checkout failed');
    }
  } catch(err) {
    console.error(err);
    alert('Error: ' + err.message);
    payBtn.disabled = false;
    payBtn.innerHTML = '<i class="bi bi-lock me-1"></i> <span id="payBtnLabel">Place Order</span>';
    updatePaymentUI();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

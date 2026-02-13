<?php
$pageTitle = 'Checkout';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();

// Fetch cart items
$sql = "SELECT * FROM cart WHERE user_id = {$user['id']}";
$result = mysqli_query($conn, $sql);
$cartItems = mysqli_fetch_all($result, MYSQLI_ASSOC);

if (empty($cartItems)) {
  header('Location: ' . BASE_PATH . '/pages/cart.php');
  exit;
}

$total = 0;
foreach ($cartItems as $item) {
  $total += ($item['price'] * (int) $item['period']);
}
?>

<div class="container mt-5">
  <div class="row">
    <div class="col-md-8">
      <h3>Order Summary</h3>
      <div class="card mb-4">
        <div class="card-body">
          <table class="table">
            <thead>
              <tr>
                <th>Domain</th>
                <th>Type</th>
                <th>Period</th>
                <th>Price</th>
              </tr>
            </thead>
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

      <h3>Payment</h3>
      <div class="card">
        <div class="card-body">
          <form id="checkoutForm">
            <input type="hidden" name="promo_code" id="promoCodeInput" value="">
            <div class="mb-3">
              <label class="form-label">Cardholder Name</label>
              <input type="text" name="card_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Card Number (mock)</label>
              <input type="text" name="card_number" class="form-control" placeholder="4242 4242 4242 4242" required>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Expiry</label>
                <input type="text" name="card_expiry" class="form-control" placeholder="MM/YY" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">CVC</label>
                <input type="text" name="card_cvc" class="form-control" placeholder="123" required>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
              <div>
                <strong>Total:</strong> $<span id="mainTotal"><?php echo number_format($total, 2); ?></span>
              </div>
              <button type="submit" class="btn btn-success">Pay & Place Order</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <h4>Order Details</h4>
      <div class="card mb-3">
        <div class="card-body">
          <p class="mb-2">Items: <?php echo count($cartItems); ?></p>
          <p class="mb-2">Subtotal: $<span id="subtotalVal"><?php echo number_format($total, 2); ?></span></p>
          <div class="input-group mb-2" id="promoGroup">
            <input type="text" class="form-control" id="promoInput" placeholder="Promo code">
            <button class="btn btn-outline-secondary" type="button" id="applyPromoBtn">Apply</button>
          </div>
          <div id="promoMessage" class="small"></div>
          <p class="mb-0 mt-2 fw-bold">Total: $<span id="totalVal"><?php echo number_format($total, 2); ?></span></p>
          <p class="text-muted small mt-2">Mock checkout. No real payment is processed.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';
  let subtotal = <?php echo $total; ?>;
  let discount = 0;
  let appliedPromo = '';

  document.getElementById('applyPromoBtn').addEventListener('click', async function() {
    const code = document.getElementById('promoInput').value.trim();
    const msg = document.getElementById('promoMessage');
    if (!code) { msg.className = 'small text-danger'; msg.textContent = 'Enter a promo code'; return; }
    try {
      const fd = new FormData();
      fd.append('code', code);
      fd.append('subtotal', subtotal);
      const r = await fetch(basePath + '/api/promo_validate.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (d.success) {
        appliedPromo = code;
        discount = d.data.discount;
        document.getElementById('promoCodeInput').value = code;
        msg.className = 'small text-success'; msg.textContent = d.message + ' (-$' + discount.toFixed(2) + ')';
        updateTotals();
        document.getElementById('promoInput').disabled = true;
        document.getElementById('applyPromoBtn').disabled = true;
      } else {
        msg.className = 'small text-danger'; msg.textContent = d.message;
      }
    } catch (err) {
      msg.className = 'small text-danger'; msg.textContent = 'Could not validate promo';
    }
  });

  function updateTotals() {
    const total = Math.max(0, subtotal - discount);
    document.getElementById('totalVal').textContent = total.toFixed(2);
    document.getElementById('mainTotal').textContent = total.toFixed(2);
  }

  document.getElementById('checkoutForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = new FormData(this);
    form.append('action', 'checkout');

    try {
      const resp = await fetch(basePath + '/api/checkout.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      });
      const data = await resp.json();
      if (data.success) {
        const redirect = (data.data && data.data.redirect) ? data.data.redirect : '/pages/dashboard.php';
        window.location.href = basePath + redirect;
      } else {
        if ((data.message || '').toLowerCase().includes('not authenticated')) {
          window.location.href = basePath + '/pages/login.php';
          return;
        }
        alert('Checkout failed: ' + data.message);
      }
    } catch (err) {
      console.error(err);
      alert('Network error during checkout');
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
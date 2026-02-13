<?php
$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../includes/header.php';
// Cart should work for guests; login is required only at checkout.
$user = getCurrentUser();
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-cart3"></i> Shopping Cart</h1>
    <a href="<?php echo pageUrl('domain_search.php'); ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-plus-lg"></i> Add more domains
    </a>
  </div>

  <div class="row">
    <div class="col-lg-8 mb-4 mb-lg-0">
      <div class="card">
        <div class="card-body p-0">
          <div id="cartItems"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card sticky-top" id="cartSummary" style="display:none; top: 100px;">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"><i class="bi bi-receipt"></i> Order Summary</h5>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Subtotal</span>
            <span id="subtotal">$0.00</span>
          </div>
          <hr>
          <div class="d-flex justify-content-between mb-3">
            <strong>Total</strong>
            <strong class="text-primary fs-5" id="total">$0.00</strong>
          </div>
          <a href="<?php echo pageUrl('checkout.php'); ?>" class="btn btn-success w-100 py-2">
            <i class="bi bi-credit-card"></i> Proceed to Checkout
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

  loadCart();

  async function loadCart() {
    try {
      const response = await fetch(basePath + '/api/cart_operations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_cart',
        credentials: 'same-origin'
      });

      const data = await response.json();

      if (data.success) {
        displayCart(data.data);
      }
    } catch (error) {
      console.error(error);
    }
  }

  async function updatePeriod(cartId, period) {
    const fd = new FormData();
    fd.append('action', 'update_period');
    fd.append('cart_id', cartId);
    fd.append('period', period);
    const r = await fetch(basePath + '/api/cart_operations.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    if (d.success) loadCart();
  }

  function displayCart(cartData) {
    const itemsDiv = document.getElementById('cartItems');
    const summaryDiv = document.getElementById('cartSummary');

    if (cartData.items.length === 0) {
      itemsDiv.innerHTML = `
        <div class="text-center py-5 px-4">
          <i class="bi bi-cart-x text-muted" style="font-size: 4rem;"></i>
          <h5 class="mt-3">Your cart is empty</h5>
          <p class="text-muted mb-4">Search for domains to add them to your cart</p>
          <a href="${basePath}/pages/domain_search.php" class="btn btn-primary">
            <i class="bi bi-search"></i> Search Domains
          </a>
        </div>`;
      summaryDiv.style.display = 'none';
      return;
    }

    let html = '<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Domain</th><th>Type</th><th>Period</th><th class="text-end">Price</th><th></th></tr></thead><tbody>';

    cartData.items.forEach(item => {
      html += `
        <tr>
          <td>
            <i class="bi bi-globe text-primary me-2"></i>
            <strong>${item.domain}</strong>
          </td>
          <td><span class="badge bg-secondary">${item.operation_type}</span></td>
          <td>
            <select class="form-select form-select-sm" style="width: auto;" onchange="updatePeriod(${item.id}, this.value)">
              ${[1,2,3,4,5,6,7,8,9,10].map(y => `<option value="${y}" ${y == item.period ? 'selected' : ''}>${y} yr</option>`).join('')}
            </select>
          </td>
          <td class="text-end fw-bold">$${item.subtotal.toFixed(2)}</td>
          <td>
            <button onclick="removeItem(${item.id})" class="btn btn-outline-danger btn-sm" title="Remove">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    itemsDiv.innerHTML = html;

    document.getElementById('subtotal').textContent = '$' + cartData.total.toFixed(2);
    document.getElementById('total').textContent = '$' + cartData.total.toFixed(2);
    summaryDiv.style.display = 'block';
  }

  async function removeItem(cartId) {
    if (!confirm('Remove this domain from cart?')) return;

    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('cart_id', cartId);

    const response = await fetch(basePath + '/api/cart_operations.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });

    const data = await response.json();
    if (!data.success) {
      if ((data.message || '').toLowerCase().includes('not authenticated')) {
        window.location.href = basePath + '/pages/login.php';
        return;
      }
    }
    if (data.success) loadCart();
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
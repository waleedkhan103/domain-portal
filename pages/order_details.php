<?php
$pageTitle = 'Order Details';
require_once __DIR__ . '/../includes/header.php';
requireLogin();
$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
?>

<div class="container mt-5">
  <a href="<?php echo BASE_PATH; ?>/pages/orders.php" class="btn btn-link mb-3">&larr; Back to Orders</a>
  <div id="orderArea"></div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';
  const orderId = <?php echo $orderId; ?>;

  async function loadOrder() {
    if (!orderId) return document.getElementById('orderArea').innerHTML = '<div class="alert alert-warning">Missing order id</div>';
    try {
      const resp = await fetch(basePath + '/api/get_order.php?id=' + orderId, { credentials: 'same-origin' });
      const data = await resp.json();
      if (!data.success) {
        if ((data.message || '').toLowerCase().includes('not authenticated')) {
          window.location.href = basePath + '/pages/login.php';
          return;
        }
        return document.getElementById('orderArea').innerHTML = '<div class="alert alert-warning">' + data.message + '</div>';
      }

      const o = data.data.order;
      let html = `<h4>Order ${o.order_number}</h4>`;
      html += `<p><strong>Date:</strong> ${o.created_at} &nbsp; <strong>Status:</strong> ${o.status}</p>`;
      html += '<table class="table"><thead><tr><th>Domain</th><th>Type</th><th>Period</th><th>Price</th><th>Status</th></tr></thead><tbody>';
      o.items.forEach(it => {
        html += `<tr><td>${it.domain_name}</td><td>${it.operation_type}</td><td>${it.period}</td><td>$${Number(it.price).toFixed(2)}</td><td>${it.status}</td></tr>`;
      });
      html += '</tbody></table>';
      html += `<p><strong>Total:</strong> $${Number(o.total_amount).toFixed(2)}</p>`;
      if (o.invoice) {
        html += `<p><a class="btn btn-sm btn-outline-primary" href="${basePath + o.invoice}" target="_blank">Download Invoice</a></p>`;
      }
      document.getElementById('orderArea').innerHTML = html;
    } catch (err) {
      console.error(err);
      document.getElementById('orderArea').innerHTML = '<div class="alert alert-danger">Network error</div>';
    }
  }

  loadOrder();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
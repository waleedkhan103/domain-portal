<?php
$pageTitle = 'My Orders';
require_once __DIR__ . '/../includes/header.php';
requireLogin();
?>

<div class="container mt-5">
  <h3>Order History</h3>
  <div id="ordersArea" class="mt-3"></div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

  async function loadOrders() {
    try {
      const resp = await fetch(basePath + '/api/get_orders.php', { credentials: 'same-origin' });
      const data = await resp.json();
      if (!data.success) {
        if ((data.message || '').toLowerCase().includes('not authenticated')) {
          window.location.href = basePath + '/pages/login.php';
          return;
        }
        return document.getElementById('ordersArea').innerHTML = '<div class="alert alert-warning">' + data.message + '</div>';
      }

      const orders = data.data.orders;
      if (!orders.length) return document.getElementById('ordersArea').innerHTML = '<div class="alert alert-info">No orders yet.</div>';

      let html = '<table class="table table-striped">';
      html += '<thead><tr><th>Order #</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>';
      orders.forEach(o => {
        html += `<tr>
          <td>${o.order_number}</td>
          <td>${o.created_at}</td>
          <td>${o.items.length}</td>
          <td>$${Number(o.total_amount).toFixed(2)}</td>
          <td>${o.status}</td>
          <td><a class="btn btn-sm btn-primary" href="${basePath}/pages/order_details.php?id=${o.id}">Details</a></td>
        </tr>`;
      });
      html += '</tbody></table>';
      document.getElementById('ordersArea').innerHTML = html;
    } catch (err) {
      console.error(err);
      document.getElementById('ordersArea').innerHTML = '<div class="alert alert-danger">Network error</div>';
    }
  }

  loadOrders();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
$pageTitle = 'Admin - View Order';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';

global $conn;
if (!$conn) {
  echo '<div class="alert alert-warning">Database unavailable. Please check configuration.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
  echo '<div class="alert alert-danger">Invalid order id.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$order = null;
if ($stmt = $conn->prepare("SELECT o.id, o.order_number, o.user_id, o.total, o.status, o.created_at, o.payment_method, u.first_name, u.last_name, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ? LIMIT 1")) {
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $res->num_rows > 0)
    $order = $res->fetch_assoc();
  $stmt->close();
} else {
  $res = mysqli_query($conn, "SELECT o.id, o.order_number, o.user_id, o.total, o.status, o.created_at, o.payment_method, u.first_name, u.last_name, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = $id LIMIT 1");
  $order = $res ? mysqli_fetch_assoc($res) : null;
}

if (!$order) {
  echo '<div class="alert alert-info">Order not found.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

// fetch order items/domains
$items = [];
if ($stmt2 = $conn->prepare("SELECT id, domain_name, operation_type, period, price, status FROM order_items WHERE order_id = ?")) {
  $stmt2->bind_param('i', $id);
  $stmt2->execute();
  $r = $stmt2->get_result();
  while ($row = $r->fetch_assoc())
    $items[] = $row;
  $stmt2->close();
} else {
  $res2 = mysqli_query($conn, "SELECT id, domain_name, operation_type, period, price, status FROM order_items WHERE order_id = $id");
  while ($row = mysqli_fetch_assoc($res2))
    $items[] = $row;
}

?>
<h1>Order #<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?></h1>
<div class="row">
  <div class="col-md-8">
    <h5>Order Details</h5>
    <ul class="list-group mb-3">
      <li class="list-group-item"><strong>Order ID:</strong> <?php echo $order['id']; ?></li>
      <li class="list-group-item"><strong>Date:</strong> <?php echo $order['created_at']; ?></li>
      <li class="list-group-item"><strong>Status:</strong> <span id="order-status-badge"
          class="badge <?php echo ($order['status'] === 'completed' ? 'bg-success' : ($order['status'] === 'pending' ? 'bg-warning' : 'bg-secondary')); ?>"><?php echo htmlspecialchars($order['status']); ?></span>
      </li>
      <li class="list-group-item"><strong>Total:</strong> <?php echo htmlspecialchars($order['total']); ?></li>
      <li class="list-group-item"><strong>Payment:</strong>
        <?php echo htmlspecialchars($order['payment_method'] ?? ''); ?></li>
    </ul>

    <h5>Customer</h5>
    <ul class="list-group mb-3">
      <li class="list-group-item"><strong>Name:</strong>
        <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></li>
      <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></li>
      <li class="list-group-item"><strong>User:</strong> <a
          href="view_user.php?id=<?php echo (int) $order['user_id']; ?>">#<?php echo (int) $order['user_id']; ?></a></li>
    </ul>

    <h5>Items</h5>
    <table class="table table-sm table-bordered mb-3">
      <thead>
        <tr>
          <th>Domain</th>
          <th>Type</th>
          <th>Period</th>
          <th>Price</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><a
                href="view_domain.php?id=<?php echo (int) $it['id']; ?>"><?php echo htmlspecialchars($it['domain_name']); ?></a>
            </td>
            <td><?php echo htmlspecialchars($it['operation_type']); ?></td>
            <td><?php echo (int) $it['period']; ?></td>
            <td><?php echo htmlspecialchars($it['price']); ?></td>
            <td><?php echo htmlspecialchars($it['status']); ?></td>
            <td><a class="btn btn-sm btn-secondary"
                href="view_domain.php?domain=<?php echo urlencode($it['domain_name']); ?>">View Domain</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h5>Activity</h5>
    <div id="order-activity">Loading...</div>
  </div>
  <div class="col-md-4">
    <h5>Actions</h5>
    <div id="order-actions">
      <?php if ($order['status'] === 'pending'): ?>
        <button id="approve-order-btn" class="btn btn-sm btn-success mb-2">Approve Order</button>
        <button id="reject-order-btn" class="btn btn-sm btn-danger mb-2">Reject Order</button>
      <?php endif; ?>
      <div class="mb-2">
        <button id="refund-order-btn" class="btn btn-sm btn-warning">Issue Refund</button>
      </div>
      <div class="mb-2">
        <button id="cancel-order-btn" class="btn btn-sm btn-secondary">Cancel Order</button>
      </div>
      <div class="mb-2">
        <button id="resend-invoice-btn" class="btn btn-sm btn-primary">Resend Invoice</button>
      </div>
    </div>
  </div>
</div>

<?php echo getCSRFField(); ?>
<script src="<?php echo BASE_PATH; ?>/admin/assets/js/view_order.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
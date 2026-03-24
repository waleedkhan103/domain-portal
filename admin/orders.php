<?php
$pageTitle = 'Admin - Orders';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/header.php';

global $conn;
if (!$conn) {
  echo '<div class="alert alert-warning">Database unavailable. Please check configuration.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$res = false;
// Prepared statement for orders list
if ($stmt = $conn->prepare("SELECT o.id, COALESCE(o.order_number, o.id) AS order_number, o.user_id, COALESCE(o.total_amount, o.total, 0) AS total, o.status, o.created_at, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC")) {
  $stmt->execute();
  $res = $stmt->get_result();
  $stmt->close();
} else {
  $sql = "SELECT o.id, COALESCE(o.order_number, o.id) AS order_number, o.user_id, COALESCE(o.total_amount, o.total, 0) AS total, o.status, o.created_at, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC";
  $res = mysqli_query($conn, $sql);
}
?>
<h1>Orders</h1>
<p>View and manage orders, payments, and invoices.</p>

<?php if ($res && mysqli_num_rows($res) > 0): ?>
  <div class="table-responsive">
    <table class="table table-striped table-bordered">
      <thead>
        <tr>
          <th>ID</th>
          <th>Order #</th>
          <th>User</th>
          <th>Total</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($res)): ?>
          <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['order_number']); ?></td>
            <td><?php echo htmlspecialchars($row['email'] ?? "#" . ($row['user_id'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars(number_format((float) ($row['total'] ?? 0), 2)); ?></td>
            <td><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
            <td>
              <a class="btn btn-sm btn-primary" href="view_order.php?id=<?php echo $row['id']; ?>">View</a>
              <a class="btn btn-sm btn-secondary" href="../api/invoice_pdf.php?order_id=<?php echo $row['id']; ?>"
                target="_blank">Invoice</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="alert alert-info">No orders found.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
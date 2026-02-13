<?php
$pageTitle = 'Billing History';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();

// Ensure transactions table exists
$createSql = "CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  order_id INT DEFAULT NULL,
  type VARCHAR(50) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
)";
mysqli_query($conn, $createSql);

$sql = "SELECT * FROM transactions WHERE user_id = {$user['id']} ORDER BY created_at DESC LIMIT 100";
$result = mysqli_query($conn, $sql);
$transactions = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><i class="bi bi-receipt"></i> Billing History</h1>
    <a href="<?php echo pageUrl('dashboard.php'); ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
  </div>

  <div class="card">
    <div class="card-body">
      <?php if (empty($transactions)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-inbox" style="font-size: 4rem;"></i>
          <p class="mt-2">No transactions yet</p>
          <a href="<?php echo pageUrl('domain_search.php'); ?>" class="btn btn-primary">Search Domains</a>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Type</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($transactions as $t): ?>
                <tr>
                  <td><?php echo date('M j, Y g:i A', strtotime($t['created_at'])); ?></td>
                  <td><?php echo htmlspecialchars($t['description'] ?? '-'); ?></td>
                  <td><span class="badge bg-secondary"><?php echo htmlspecialchars($t['type']); ?></span></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

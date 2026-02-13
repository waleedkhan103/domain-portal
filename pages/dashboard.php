<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();

// ────────────────────────────────────────────────
// Fetch dashboard data
// ────────────────────────────────────────────────

// Total domains
$sql = "SELECT COUNT(*) as count FROM domains WHERE user_id = " . (int) $user['id'];
$result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$domainCount = mysqli_fetch_assoc($result)['count'];

// Expiring soon (next 30 days)
$sql = "SELECT COUNT(*) as count FROM domains 
        WHERE user_id = " . (int) $user['id'] . " 
        AND expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) 
        AND expiry_date > NOW()";
$result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$expiringCount = mysqli_fetch_assoc($result)['count'];

// Recent domains
$sql = "SELECT * FROM domains 
        WHERE user_id = " . (int) $user['id'] . " 
        ORDER BY created_at DESC LIMIT 5";
$result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$recentDomains = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Recent activity
$sql = "SELECT * FROM activity_log 
        WHERE user_id = " . (int) $user['id'] . " 
        ORDER BY created_at DESC LIMIT 10";
$result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$recentActivity = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<div class="container mt-4">

  <!-- Stats row -->
  <div class="row mb-4">
    <div class="col-md-4 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="me-3">
            <i class="bi bi-globe fs-2 text-primary"></i>
          </div>
          <div>
            <h5 class="mb-0"><?= $domainCount ?></h5>
            <small class="text-muted">Total Domains</small>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="me-3">
            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
          </div>
          <div>
            <h5 class="mb-0"><?= $expiringCount ?></h5>
            <small class="text-muted">Expiring Soon</small>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <div class="me-3">
            <i class="bi bi-cart-fill fs-2 text-success"></i>
          </div>
          <div>
            <h5 class="mb-0"><?= getCartCount($user['id']) ?></h5>
            <small class="text-muted">Cart Items</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Recent Domains -->
    <div class="col-lg-8 mb-4">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Recent Domains</h5>
          <a href="<?= pageUrl('my_domains.php') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>
        <div class="card-body">
          <?php if (empty($recentDomains)): ?>
            <div class="empty-state text-center py-5">
              <i class="bi bi-inbox fs-1 text-muted mb-3 d-block"></i>
              <p class="text-muted mb-3">No domains registered yet</p>
              <a href="<?= pageUrl('domain_search.php') ?>" class="btn btn-primary">
                Search Domains
              </a>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Domain</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentDomains as $d): ?>
                    <tr>
                      <td><?= htmlspecialchars($d['domain_name']) ?></td>
                      <td><?= formatDate($d['expiry_date']) ?></td>
                      <td><?= ucfirst($d['status']) ?></td>
                      <td>
                        <a href="<?= pageUrl('domain_details.php') ?>?id=<?= $d['id'] ?>"
                          class="btn btn-sm btn-outline-primary">
                          Manage
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4 mb-4">
      <!-- Recent Activity -->
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h6 class="mb-0">Recent Activity</h6>
        </div>
        <div class="card-body p-0">
          <?php if (empty($recentActivity)): ?>
            <div class="text-center py-4 text-muted">
              No recent activity
            </div>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($recentActivity as $act): ?>
                <li class="list-group-item">
                  <div class="small text-muted mb-1">
                    <?= date('M j, Y g:i A', strtotime($act['created_at'])) ?>
                  </div>
                  <div><?= htmlspecialchars($act['description']) ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="card shadow-sm">
        <div class="card-header">
          <h6 class="mb-0">Quick Actions</h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="<?= pageUrl('domain_search.php') ?>" class="btn btn-outline-primary">Search Domains</a>
            <a href="<?= pageUrl('my_domains.php') ?>" class="btn btn-outline-secondary">My Domains</a>
            <a href="<?= pageUrl('orders.php') ?>" class="btn btn-outline-info">Orders</a>
            <a href="<?= pageUrl('cart.php') ?>" class="btn btn-outline-success">View Cart</a>
            <a href="<?= pageUrl('profile.php') ?>" class="btn btn-outline-dark">My Profile</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
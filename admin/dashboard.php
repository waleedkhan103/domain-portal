<?php
$pageTitle = 'Admin - Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/header.php';

global $conn;
if (!$conn) {
  echo '<div class="alert alert-warning">Database unavailable. Please check configuration.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1>Dashboard</h1>
  <div style="min-width:360px">
    <div class="input-group">
      <input id="admin-quick-search" class="form-control" placeholder="Search domain or order ID...">
      <button id="search-btn" class="btn btn-outline-secondary">Search</button>
    </div>
    <div id="search-suggestions" class="list-group position-absolute"
      style="z-index:9999; display:none; max-height:280px; overflow:auto;"></div>
  </div>
</div>

<!-- Metrics cards -->
<div class="row" id="metrics-row">
  <div class="col-sm-6 col-md-3 mb-3">
    <div class="card">
      <div class="card-body">
        <h6 class="card-subtitle mb-2 text-muted">Total Domains</h6>
        <h3 id="total-domains">—</h3>
        <div id="total-domains-delta" class="text-muted small"></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3 mb-3">
    <div class="card">
      <div class="card-body">
        <h6 class="card-subtitle mb-2 text-muted">Active Domains</h6>
        <h3 id="active-domains">—</h3>
        <div id="active-domains-delta" class="text-muted small"></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3 mb-3">
    <div class="card">
      <div class="card-body">
        <h6 class="card-subtitle mb-2 text-muted">Expiring Soon (30d)</h6>
        <h3 id="expiring-domains">—</h3>
        <div id="expiring-domains-delta" class="text-muted small"></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3 mb-3">
    <div class="card">
      <div class="card-body">
        <h6 class="card-subtitle mb-2 text-muted">Revenue This Month</h6>
        <h3 id="revenue-month">—</h3>
        <div id="revenue-month-delta" class="text-muted small"></div>
      </div>
    </div>
  </div>
</div>

<!-- Quick stats and chart -->
<div class="row">
  <div class="col-lg-4 mb-3">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title">Quick Stats</h6>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">Pending Orders: <a id="quick-pending" href="orders.php?status=pending">—</a></li>
          <li class="list-group-item">Recent Registrations (7d): <span id="quick-registrations">—</span></li>
          <li class="list-group-item">Failed Payments: <a id="quick-failed" href="orders.php?status=failed">—</a></li>
        </ul>
      </div>
    </div>
    <div class="mt-3 card">
      <div class="card-body">
        <h6 class="card-title">Alerts</h6>
        <div id="alerts-area"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-8 mb-3">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title">Domains by Status</h6>
        <canvas id="domains-status-chart" height="160"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Recent tables -->
<div class="row mt-3">
  <div class="col-lg-6 mb-3">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title d-flex justify-content-between align-items-center">Recent Domains <a href="domains.php"
            class="btn btn-sm btn-outline-primary">View All</a></h6>
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>Domain</th>
                <th>Owner</th>
                <th>Registered</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="recent-domains-tbody">
              <tr>
                <td colspan="4">Loading...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6 mb-3">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title d-flex justify-content-between align-items-center">Recent Orders <a href="orders.php"
            class="btn btn-sm btn-outline-primary">View All</a></h6>
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Domain</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody id="recent-orders-tbody">
              <tr>
                <td colspan="6">Loading...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_PATH; ?>/admin/assets/js/dashboard.js" defer></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Dashboard</h1>
<p>Welcome, Admin.</p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
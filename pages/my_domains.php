<?php
$pageTitle = 'My Domains';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();

// ────────────────────────────────────────────────
// Helper function: days until expiry
// ────────────────────────────────────────────────
function calculateDaysUntilExpiry($expiry_date)
{
  if (empty($expiry_date) || $expiry_date === '0000-00-00' || $expiry_date === '0000-00-00 00:00:00') {
    return 9999; // treat as never expires / very far future
  }

  try {
    $expiry = new DateTime($expiry_date);
    $now = new DateTime();
    $interval = $now->diff($expiry);
    $days = $interval->days;
    return $interval->invert ? -$days : $days;
  } catch (Exception $e) {
    return 9999; // fallback on invalid date
  }
}

// ────────────────────────────────────────────────
// Fetch domains safely with prepared statement
// ────────────────────────────────────────────────
$sql = "SELECT * FROM domains WHERE user_id = ? ORDER BY expiry_date ASC";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
  die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $user['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$domains = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>

<div class="container mt-4">

  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <h1 class="mb-0">My Domains</h1>
    <a href="<?php echo pageUrl('domain_search.php'); ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Register New Domain
    </a>
  </div>

  <?php if (empty($domains)): ?>
    <div class="card text-center py-5 border-0 bg-light">
      <div class="card-body">
        <i class="bi bi-globe fs-1 text-muted mb-3 d-block"></i>
        <h3 class="text-muted mb-3">No domains yet</h3>
        <p class="text-muted mb-4">Start building your online presence today</p>
        <a href="<?php echo pageUrl('domain_search.php'); ?>" class="btn btn-primary btn-lg">
          Search & Register Domains
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
      <?php foreach ($domains as $domain):
        $daysLeft = calculateDaysUntilExpiry($domain['expiry_date']);

        // Visual status logic
        if ($daysLeft < 0) {
          $borderColor = 'border-danger';
          $badgeClass = 'bg-danger';
          $daysDisplay = 'Expired';
        } elseif ($daysLeft <= 30) {
          $borderColor = 'border-warning';
          $badgeClass = 'bg-warning text-dark';
          $daysDisplay = $daysLeft . ' days left';
        } else {
          $borderColor = 'border-success';
          $badgeClass = 'bg-success';
          $daysDisplay = $daysLeft . ' days left';
        }
        ?>
        <div class="col">
          <div class="card h-100 shadow-sm <?php echo $borderColor; ?>">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <h5 class="card-title mb-0 text-truncate pe-2"
                  title="<?php echo htmlspecialchars($domain['domain_name']); ?>">
                  <?php echo htmlspecialchars($domain['domain_name']); ?>
                </h5>
                <?php if (!empty($domain['is_locked'])): ?>
                  <span class="badge bg-secondary ms-2 flex-shrink-0">Locked</span>
                <?php endif; ?>
              </div>

              <div class="mb-3 flex-grow-1">
                <div class="small text-muted mb-1">Expires</div>
                <div class="fw-bold mb-2">
                  <?php echo formatDate($domain['expiry_date']); ?>
                </div>

                <div class="small text-muted mb-1">Remaining</div>
                <div class="fw-bold <?php echo ($daysLeft <= 30 && $daysLeft >= 0) ? 'text-warning' : ''; ?>">
                  <?php echo $daysDisplay; ?>
                </div>

                <div class="mt-3">
                  <span class="badge <?php echo $badgeClass; ?> px-3 py-2">
                    <?php echo ucfirst($domain['status'] ?? 'active'); ?>
                  </span>
                </div>
              </div>

              <div class="mt-auto">
                <a href="<?php echo pageUrl('domain_details.php') . '?id=' . $domain['id']; ?>"
                  class="btn btn-outline-primary w-100">
                  <i class="bi bi-gear me-1"></i> Manage
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
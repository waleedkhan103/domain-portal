<?php
$pageTitle = 'Domain Watchlist';
require_once __DIR__ . '/../includes/header.php';
requireLogin();
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><i class="bi bi-bell"></i> Domain Watchlist</h1>
    <a href="<?php echo pageUrl('domain_search.php'); ?>" class="btn btn-primary">Add Domain</a>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <p class="text-muted mb-3">Get email alerts before your domains expire. Add domains you own to receive reminders.</p>
      <form id="addWatchForm" class="row g-2">
        <div class="col-md-6">
          <input type="text" class="form-control" name="domain" placeholder="yourdomain.com" required>
        </div>
        <div class="col-md-3">
          <select class="form-select" name="alert_days">
            <option value="7">7 days before</option>
            <option value="14">14 days before</option>
            <option value="30" selected>30 days before</option>
            <option value="60">60 days before</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-outline-primary w-100">Add to Watchlist</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Watched Domains</h5>
      <div id="watchList"></div>
    </div>
  </div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

  async function loadWatchlist() {
    const fd = new FormData();
    fd.append('action', 'list');
    const r = await fetch(basePath + '/api/watchlist.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    const div = document.getElementById('watchList');
    if (d.success && d.data.items.length > 0) {
      div.innerHTML = '<ul class="list-group">' + d.data.items.map(i =>
        '<li class="list-group-item d-flex justify-content-between align-items-center">' +
        '<span>' + i.domain_name + '</span>' +
        '<div><span class="badge bg-secondary me-2">Alert ' + i.alert_days_before + ' days before</span>' +
        '<button class="btn btn-sm btn-outline-danger" onclick="removeWatch(' + i.id + ')">Remove</button></div></li>'
      ).join('') + '</ul>';
    } else {
      div.innerHTML = '<p class="text-muted">No domains in your watchlist. Add one above.</p>';
    }
  }

  async function removeWatch(id) {
    const fd = new FormData();
    fd.append('action', 'remove');
    fd.append('id', id);
    await fetch(basePath + '/api/watchlist.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    loadWatchlist();
  }

  document.getElementById('addWatchForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'add');
    const r = await fetch(basePath + '/api/watchlist.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    alert(d.message);
    if (d.success) {
      this.reset();
      loadWatchlist();
    }
  });

  loadWatchlist();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

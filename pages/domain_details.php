<?php
$pageTitle = 'Domain Details';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();
$domainId = (int) ($_GET['id'] ?? 0);

$sql = "SELECT * FROM domains WHERE id = $domainId AND user_id = {$user['id']}";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) === 0) {
  header('Location: ' . BASE_PATH . '/pages/my_domains.php');
  exit;
}

$domain = mysqli_fetch_assoc($result);
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo htmlspecialchars((string) ($domain['domain_name'] ?? '')); ?></h1>
    <a href="<?php echo pageUrl('my_domains.php'); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to My Domains</a>
  </div>

  <div class="nav nav-tabs mb-3">
    <button class="nav-link active tab-btn" data-tab="overview">Overview</button>
    <button class="nav-link tab-btn" data-tab="dns">Nameservers</button>
    <button class="nav-link tab-btn" data-tab="dnsrecords">DNS Records</button>
    <button class="nav-link tab-btn" data-tab="security">Security</button>
  </div>

  <div class="tab-content active" id="overview">
    <div class="card">
      <div class="card-body">
      <h5 class="card-title">Domain Information</h5>
      <div class="row g-2">
        <div class="col-md-4"><strong>Domain:</strong> <?php echo htmlspecialchars((string) ($domain['domain_name'] ?? '')); ?></div>
        <div class="col-md-4"><strong>Registration:</strong> <?php echo formatDate($domain['registration_date'] ?? ''); ?></div>
        <div class="col-md-4"><strong>Expiry:</strong> <?php echo formatDate($domain['expiry_date'] ?? ''); ?></div>
        <div class="col-md-4"><strong>Status:</strong> <span class="badge bg-<?php echo ($domain['status'] ?? '') === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($domain['status'] ?? 'N/A'); ?></span></div>
        <div class="col-md-4"><strong>Auto Renew:</strong> <?php echo !empty($domain['auto_renew']) ? 'Enabled' : 'Disabled'; ?></div>
        <div class="col-md-4"><strong>Lock:</strong> <?php echo !empty($domain['is_locked']) ? 'Locked' : 'Unlocked'; ?></div>
      </div>
      <hr>
      <h6>Renew Domain</h6>
      <form id="renewForm" class="d-flex gap-2 align-items-center">
        <select name="period" id="renewPeriod" class="form-select" style="width: auto;">
            <?php for ($i = 1; $i <= 10; $i++): ?>
              <option value="<?php echo $i; ?>">
                <?php echo $i; ?> Year
                <?php echo $i > 1 ? 's' : ''; ?>
              </option>
            <?php endfor; ?>
          </select>
          <button type="submit" class="btn btn-primary">Renew Now</button>
        </form>
      </div>
    </div>
  </div>

  <div class="tab-content" id="dns">
    <div class="card">
      <div class="card-body">
      <h5 class="card-title">DNS Nameservers</h5>
      <form id="dnsForm">
        <div class="mb-2">
          <label class="form-label">Nameserver 1</label>
          <input type="text" name="dns1" class="form-control" value="<?php echo htmlspecialchars((string) ($domain['dns1'] ?? 'ns1.example.com')); ?>" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Nameserver 2</label>
          <input type="text" name="dns2" class="form-control" value="<?php echo htmlspecialchars((string) ($domain['dns2'] ?? 'ns2.example.com')); ?>" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Nameserver 3 (Optional)</label>
          <input type="text" name="dns3" class="form-control" value="<?php echo htmlspecialchars((string) ($domain['dns3'] ?? '')); ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Nameserver 4 (Optional)</label>
          <input type="text" name="dns4" class="form-control" value="<?php echo htmlspecialchars((string) ($domain['dns4'] ?? '')); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Update Nameservers</button>
      </form>
    </div>
  </div>

  <div class="tab-content" id="dnsrecords">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-diagram-3"></i> DNS Records (A, CNAME, MX, TXT)</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRecordModal">+ Add Record</button>
      </div>
      <div class="card-body">
        <div id="dnsRecordsList"></div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="addRecordModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add DNS Record</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="addRecordForm">
            <div class="mb-2">
              <label class="form-label">Type</label>
              <select name="record_type" class="form-select" required>
                <option value="A">A</option>
                <option value="AAAA">AAAA</option>
                <option value="CNAME">CNAME</option>
                <option value="MX">MX</option>
                <option value="TXT">TXT</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Host</label>
              <input type="text" name="host" class="form-control" placeholder="@ or subdomain" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Value</label>
              <input type="text" name="value" class="form-control" placeholder="IP or hostname" required>
            </div>
            <div class="row">
              <div class="col-6 mb-2">
                <label class="form-label">TTL</label>
                <input type="number" name="ttl" class="form-control" value="3600" min="60">
              </div>
              <div class="col-6 mb-2" id="priorityGroup" style="display:none;">
                <label class="form-label">Priority</label>
                <input type="number" name="priority" class="form-control" value="10">
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Record</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-content" id="security">
    <div class="card">
      <div class="card-body">
      <h5 class="card-title">Security Settings</h5>

      <div class="d-flex justify-content-between align-items-center p-3 border rounded mb-3">
        <div>
          <h6 class="mb-0">Domain Lock</h6>
          <small class="text-muted">Prevent unauthorized transfers</small>
        </div>
        <button onclick="toggleLock()" class="btn btn-outline-secondary">
          <?php echo !empty($domain['is_locked']) ? 'Unlock' : 'Lock'; ?> Domain
        </button>
      </div>

      <div class="d-flex justify-content-between align-items-center p-3 border rounded">
        <div>
          <h6 class="mb-0">Auth Code</h6>
          <small class="text-muted">Get authorization code for transfers</small>
        </div>
        <button onclick="getAuthCode()" class="btn btn-outline-secondary">Get Auth Code</button>
      </div>

      <div id="authCodeDisplay" class="mt-3" style="display:none;">
        <div class="alert alert-info">
          <strong>Auth Code:</strong> <span id="authCodeValue"></span>
        </div>
      </div>
    </div>
  </div>
  </div>
</div>

<script>
  const domainId = <?php echo $domainId; ?>;
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const tab = this.dataset.tab;
      document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); });
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      const el = document.getElementById(tab);
      if (el) el.classList.add('active');
      if (tab === 'dnsrecords') loadDnsRecords();
    });
  });

  document.querySelector('select[name="record_type"]').addEventListener('change', function() {
    document.getElementById('priorityGroup').style.display = this.value === 'MX' ? 'block' : 'none';
  });

  async function loadDnsRecords() {
    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('domain_id', domainId);
    const r = await fetch(basePath + '/api/dns_records.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    const div = document.getElementById('dnsRecordsList');
    if (d.success && d.data.records.length > 0) {
      div.innerHTML = '<table class="table table-sm"><thead><tr><th>Type</th><th>Host</th><th>Value</th><th>TTL</th><th>Priority</th><th></th></tr></thead><tbody>' +
        d.data.records.map(rec => '<tr><td>' + rec.record_type + '</td><td>' + rec.host + '</td><td>' + rec.value + '</td><td>' + rec.ttl + '</td><td>' + (rec.priority || '-') + '</td><td><button class="btn btn-sm btn-outline-danger" onclick="deleteRecord(' + rec.id + ')">Delete</button></td></tr>').join('') +
        '</tbody></table>';
    } else {
      div.innerHTML = '<p class="text-muted">No DNS records. Add A, CNAME, MX, or TXT records above.</p>';
    }
  }

  async function deleteRecord(id) {
    if (!confirm('Delete this record?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fd.append('domain_id', domainId);
    const r = await fetch(basePath + '/api/dns_records.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    alert(d.message);
    if (d.success) loadDnsRecords();
  }

  document.getElementById('addRecordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'add');
    fd.append('domain_id', domainId);
    const r = await fetch(basePath + '/api/dns_records.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    alert(d.message);
    if (d.success) {
      this.reset();
      const modal = document.getElementById('addRecordModal');
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const m = bootstrap.Modal.getInstance(modal);
        if (m) m.hide();
      }
      loadDnsRecords();
    }
  });

  document.getElementById('dnsForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_dns');
    formData.append('domain_id', domainId);

    const response = await fetch(basePath + '/api/update_dns.php', {
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
    alert(data.message);
  });

  document.getElementById('renewForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const period = document.getElementById('renewPeriod').value || 1;
    if (!confirm('Proceed with domain renewal?')) return;

    const form = new FormData();
    form.append('domain_id', domainId);
    form.append('period', period);

    const response = await fetch(basePath + '/api/renew.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    });

    const data = await response.json();
    if (!data.success) {
      if ((data.message || '').toLowerCase().includes('not authenticated')) {
        window.location.href = basePath + '/pages/login.php';
        return;
      }
      alert(data.message);
      return;
    }
    alert('Renewed. New expiry: ' + data.data.expiry_date);
    if (data.success) location.reload();
  });

  async function toggleLock() {
    const formData = new FormData();
    formData.append('action', 'toggle_lock');
    formData.append('domain_id', domainId);

    const response = await fetch(basePath + '/api/toggle_lock.php', {
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
    alert(data.message);
    if (data.success) location.reload();
  }

  async function getAuthCode() {
    const formData = new FormData();
    formData.append('action', 'get_auth_code');
    formData.append('domain_id', domainId);

    const response = await fetch(basePath + '/api/get_auth_code.php', {
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
      alert(data.message);
      return;
    }

    document.getElementById('authCodeValue').textContent = data.data.auth_code;
    document.getElementById('authCodeDisplay').style.display = 'block';
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
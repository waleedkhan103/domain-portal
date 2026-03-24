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
        <div class="col-md-4">
          <strong>Privacy:</strong>
          <?php if (!empty($domain['privacy_enabled'])): ?>
            <span class="text-success"><i class="bi bi-shield-check"></i> Protected</span>
          <?php else: ?>
            <span class="text-muted">Not Protected</span>
          <?php endif; ?>
        </div>
      </div>
      <hr>
      <h6>Contact Information</h6>
      <div class="mb-3">
        <p class="mb-2"><strong>Registrant:</strong> <?php echo htmlspecialchars($domain['registrant_first'] . ' ' . $domain['registrant_last']); ?> &lt;<?php echo htmlspecialchars($domain['registrant_email'] ?? ''); ?>&gt;</p>
        <a href="<?php echo pageUrl('update_contacts.php?id=' . $domainId); ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-person-lines-fill me-1"></i> Update All Contacts
        </a>
      </div>
      <hr>
      <h6>Auto-Renewal Settings</h6>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="autoRenewToggle"
          <?php echo !empty($domain['auto_renew']) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="autoRenewToggle">
          <strong>Enable Auto-Renewal</strong>
          <div class="small text-muted">Automatically renew this domain 30 days before expiry</div>
        </label>
      </div>

      <hr>
      <h6>Manual Renewal</h6>
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

    <!-- Child Nameservers (Glue Records) -->
    <div class="card mt-4">
      <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <span>
          <i class="bi bi-hdd-network me-1"></i> Child Nameservers (Glue Records)
        </span>
        <button class="btn btn-sm btn-success" onclick="showAddChildNSModal()">
          <i class="bi bi-plus-lg"></i> Add Child NS
        </button>
      </div>
      <div class="card-body">
        <div class="alert alert-info mb-3">
          <small>
            <i class="bi bi-info-circle me-1"></i>
            <strong>What are child nameservers?</strong><br>
            Child nameservers (glue records) allow you to use nameservers within your own domain.
            For example, if you own <strong><?php echo htmlspecialchars($domain['domain_name']); ?></strong>,
            you can create <strong>ns1.<?php echo htmlspecialchars($domain['domain_name']); ?></strong> as a nameserver.
          </small>
        </div>
        <div id="childNameserversList">
          <div class="text-center text-muted py-3">
            <i class="bi bi-hdd-network fs-3 d-block mb-2"></i>
            Loading child nameservers...
          </div>
        </div>
      </div>
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
              <select name="record_type" id="recordType" class="form-select" required>
                <option value="A">A - IPv4 Address</option>
                <option value="AAAA">AAAA - IPv6 Address</option>
                <option value="CNAME">CNAME - Canonical Name</option>
                <option value="MX">MX - Mail Exchange</option>
                <option value="TXT">TXT - Text Record</option>
                <option value="SRV">SRV - Service Record</option>
                <option value="CAA">CAA - Certificate Authority Authorization</option>
                <option value="NS">NS - Nameserver</option>
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
            <!-- SRV Record Fields -->
            <div class="row" id="srvFields" style="display:none;">
              <div class="col-6 mb-2">
                <label class="form-label">Port</label>
                <input type="number" name="port" class="form-control" placeholder="e.g., 80">
              </div>
              <div class="col-6 mb-2">
                <label class="form-label">Weight</label>
                <input type="number" name="weight" class="form-control" value="10">
              </div>
            </div>
            <!-- CAA Record Fields -->
            <div class="row" id="caaFields" style="display:none;">
              <div class="col-6 mb-2">
                <label class="form-label">Flags</label>
                <input type="number" name="flags" class="form-control" value="0" min="0" max="255">
              </div>
              <div class="col-6 mb-2">
                <label class="form-label">Tag</label>
                <select name="tag" class="form-select">
                  <option value="issue">issue</option>
                  <option value="issuewild">issuewild</option>
                  <option value="iodef">iodef</option>
                </select>
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

      <div class="d-flex justify-content-between align-items-center p-3 border rounded mb-3 <?php echo !empty($domain['privacy_enabled']) ? 'bg-success bg-opacity-10 border-success' : ''; ?>">
        <div>
          <h6 class="mb-0">
            <i class="bi bi-shield-check text-success me-1"></i> WHOIS Privacy Protection
            <?php if (!empty($domain['privacy_enabled'])): ?>
              <span class="badge bg-success ms-2">Active</span>
            <?php endif; ?>
          </h6>
          <small class="text-muted">
            <?php if (!empty($domain['privacy_enabled'])): ?>
              Your contact information is hidden from public WHOIS lookups
            <?php else: ?>
              Hide your personal information from public WHOIS databases
            <?php endif; ?>
          </small>
          <?php if (!empty($domain['privacy_fee']) && $domain['privacy_fee'] > 0): ?>
            <div class="small text-muted mt-1">Annual fee: $<?php echo number_format($domain['privacy_fee'], 2); ?></div>
          <?php endif; ?>
        </div>
        <button onclick="togglePrivacy()" class="btn <?php echo !empty($domain['privacy_enabled']) ? 'btn-outline-danger' : 'btn-success'; ?>">
          <?php echo !empty($domain['privacy_enabled']) ? 'Disable' : 'Enable ($2.99/yr)'; ?>
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

  document.getElementById('recordType').addEventListener('change', function() {
    const type = this.value;
    document.getElementById('priorityGroup').style.display = (type === 'MX' || type === 'SRV') ? 'block' : 'none';
    document.getElementById('srvFields').style.display = type === 'SRV' ? 'block' : 'none';
    document.getElementById('caaFields').style.display = type === 'CAA' ? 'block' : 'none';
  });

  async function loadDnsRecords() {
    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('domain_id', domainId);
    const r = await fetch(basePath + '/api/dns_records.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    const div = document.getElementById('dnsRecordsList');
    if (d.success && d.data.records.length > 0) {
      let html = '<table class="table table-sm table-hover"><thead><tr><th>Type</th><th>Host</th><th>Value</th><th>Details</th><th>TTL</th><th></th></tr></thead><tbody>';
      d.data.records.forEach(rec => {
        let details = '';
        if (rec.record_type === 'MX' && rec.priority) {
          details = `Priority: ${rec.priority}`;
        } else if (rec.record_type === 'SRV') {
          details = `Pri: ${rec.priority || '-'}, Port: ${rec.port || '-'}, Weight: ${rec.weight || '-'}`;
        } else if (rec.record_type === 'CAA') {
          details = `Flags: ${rec.flags || 0}, Tag: ${rec.tag || '-'}`;
        } else {
          details = '-';
        }
        html += `<tr>
          <td><span class="badge bg-secondary">${rec.record_type}</span></td>
          <td>${rec.host}</td>
          <td class="text-truncate" style="max-width: 200px;" title="${rec.value}">${rec.value}</td>
          <td><small class="text-muted">${details}</small></td>
          <td>${rec.ttl}s</td>
          <td><button class="btn btn-sm btn-outline-danger" onclick="deleteRecord(${rec.id})"><i class="bi bi-trash"></i></button></td>
        </tr>`;
      });
      html += '</tbody></table>';
      div.innerHTML = html;
    } else {
      div.innerHTML = '<p class="text-muted">No DNS records. Add A, CNAME, MX, TXT, SRV, CAA, or NS records above.</p>';
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

  // Auto-renewal toggle
  document.getElementById('autoRenewToggle').addEventListener('change', async function () {
    const enabled = this.checked;
    const form = new FormData();
    form.append('action', 'auto_renew');
    form.append('domain_id', domainId);
    form.append('auto_renew', enabled ? '1' : '0');

    try {
      const response = await fetch(basePath + '/api/domain_operations.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      });
      const data = await response.json();
      if (data.success) {
        alert(enabled ? 'Auto-renewal enabled' : 'Auto-renewal disabled');
      } else {
        alert('Error: ' + data.message);
        this.checked = !enabled; // Revert on failure
      }
    } catch (err) {
      alert('Network error');
      this.checked = !enabled;
    }
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

  async function togglePrivacy() {
    const formData = new FormData();
    formData.append('action', 'toggle_privacy');
    formData.append('domain_id', domainId);

    const response = await fetch(basePath + '/api/domain_operations.php', {
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
      alert('Error: ' + data.message);
      return;
    }
    alert(data.message);
    location.reload();
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

  // ====== Child Nameservers (Glue Records) Functions ======
  async function loadChildNameservers() {
    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('domain_id', domainId);

    try {
      const response = await fetch(basePath + '/api/child_nameservers.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const data = await response.json();

      const listDiv = document.getElementById('childNameserversList');

      if (data.success && data.data && data.data.length > 0) {
        listDiv.innerHTML = '<table class="table table-sm"><thead><tr><th>Hostname</th><th>IPv4</th><th>IPv6</th><th>Status</th><th></th></tr></thead><tbody>' +
          data.data.map(ns => `
            <tr>
              <td><strong>${ns.hostname}</strong></td>
              <td>${ns.ipv4_address || '-'}</td>
              <td>${ns.ipv6_address || '-'}</td>
              <td><span class="badge bg-${ns.status === 'active' ? 'success' : 'secondary'}">${ns.status}</span></td>
              <td>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteChildNS(${ns.id})">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          `).join('') +
          '</tbody></table>';
      } else {
        listDiv.innerHTML = '<p class="text-muted text-center py-3">No child nameservers configured.</p>';
      }
    } catch (error) {
      document.getElementById('childNameserversList').innerHTML = '<p class="text-danger">Error loading child nameservers</p>';
    }
  }

  function showAddChildNSModal() {
    const hostname = prompt('Enter child nameserver hostname (e.g., ns1.<?php echo htmlspecialchars($domain['domain_name']); ?>):');
    if (!hostname) return;

    const ipv4 = prompt('Enter IPv4 address (required):');
    if (!ipv4) {
      alert('IPv4 address is required');
      return;
    }

    const ipv6 = prompt('Enter IPv6 address (optional, press Cancel to skip):') || '';

    addChildNS(hostname, ipv4, ipv6);
  }

  async function addChildNS(hostname, ipv4, ipv6) {
    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('domain_id', domainId);
    fd.append('hostname', hostname);
    fd.append('ipv4_address', ipv4);
    if (ipv6) fd.append('ipv6_address', ipv6);

    try {
      const response = await fetch(basePath + '/api/child_nameservers.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const data = await response.json();

      alert(data.message);
      if (data.success) {
        loadChildNameservers();
      }
    } catch (error) {
      alert('Error adding child nameserver: ' + error.message);
    }
  }

  async function deleteChildNS(id) {
    if (!confirm('Delete this child nameserver?')) return;

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fd.append('domain_id', domainId);

    try {
      const response = await fetch(basePath + '/api/child_nameservers.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const data = await response.json();

      alert(data.message);
      if (data.success) {
        loadChildNameservers();
      }
    } catch (error) {
      alert('Error deleting child nameserver: ' + error.message);
    }
  }

  // Load child nameservers when DNS tab is clicked
  document.querySelectorAll('.tab-btn').forEach(btn => {
    if (btn.dataset.tab === 'dns') {
      btn.addEventListener('click', function() {
        setTimeout(loadChildNameservers, 100);
      });
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
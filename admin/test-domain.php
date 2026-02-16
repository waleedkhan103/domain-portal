<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Domain Management Tester</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      padding: 20px;
      background: #f5f5f5;
    }

    .section {
      background: white;
      padding: 20px;
      margin: 20px 0;
      border-radius: 10px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .success {
      color: green;
    }

    .error {
      color: red;
    }

    .warning {
      color: orange;
    }

    pre {
      background: #f8f9fa;
      padding: 10px;
      border-radius: 5px;
      font-size: 12px;
      max-height: 300px;
      overflow: auto;
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>🌐 Domain Management Tester</h1>
    <p class="lead">Test domain management functionality</p>

    <div class="section">
      <h2>1. Database Structure Check</h2>
      <button class="btn btn-primary" onclick="checkDatabaseStructure()">Check Domains Table</button>
      <div id="db-results" class="mt-3"></div>
    </div>

    <div class="section">
      <h2>2. API Endpoints Test</h2>
      <button class="btn btn-primary" onclick="testAPIs()">Test All APIs</button>
      <div id="api-results" class="mt-3"></div>
    </div>

    <div class="section">
      <h2>3. Sample Domain Data</h2>
      <button class="btn btn-success" onclick="createSampleDomain()">Create Test Domain</button>
      <button class="btn btn-info ms-2" onclick="listDomains()">List Domains</button>
      <div id="domain-results" class="mt-3"></div>
    </div>

    <div class="section">
      <h2>4. Domain Actions Test</h2>
      <div class="mb-2">
        <input type="number" id="test-domain-id" class="form-control" placeholder="Domain ID"
          style="width: 200px; display: inline-block;">
        <button class="btn btn-warning" onclick="testDomainLock()">Test Lock/Unlock</button>
        <button class="btn btn-success ms-2" onclick="testDomainRenew()">Test Renew</button>
        <button class="btn btn-info ms-2" onclick="testAuthCode()">Get Auth Code</button>
      </div>
      <div id="action-results" class="mt-3"></div>
    </div>

    <div class="section">
      <h2>5. Quick Links</h2>
      <a href="domains.php" class="btn btn-primary">Go to Domains List</a>
      <a href="dashboard.php" class="btn btn-secondary ms-2">Dashboard</a>
      <a href="test_admin.php" class="btn btn-info ms-2">Full Admin Test</a>
    </div>
  </div>

  <script>
    const basePath = window.location.pathname.replace('/admin/test_domains.php', '');

    function displayResult(containerId, html) {
      document.getElementById(containerId).innerHTML = html;
    }

    async function checkDatabaseStructure() {
      displayResult('db-results', '<p class="text-muted">Checking...</p>');

      try {
        // Test the domains API to see what columns are returned
        const response = await fetch(basePath + '/admin/api/domains_filter.php?page=1&per_page=1');
        const data = await response.json();

        let html = '<div class="alert alert-info"><strong>API Response:</strong></div>';

        if (data.success && data.data.rows.length > 0) {
          const domain = data.data.rows[0];
          const columns = Object.keys(domain);

          html += '<p class="success">✓ Domains table accessible</p>';
          html += '<p><strong>Available columns:</strong></p>';
          html += '<ul>';
          columns.forEach(col => {
            html += `<li>${col}: ${domain[col]}</li>`;
          });
          html += '</ul>';

          // Check required columns
          const required = ['id', 'domain_name', 'user_id', 'status', 'expires_at'];
          const missing = required.filter(col => !columns.includes(col));

          if (missing.length > 0) {
            html += '<p class="warning">⚠️ Missing columns: ' + missing.join(', ') + '</p>';
            html += '<p>Run the SQL fix script to add these columns.</p>';
          } else {
            html += '<p class="success">✓ All required columns present</p>';
          }
        } else if (data.data.rows.length === 0) {
          html += '<p class="warning">⚠️ No domains found in database</p>';
          html += '<p>Create a test domain using button above.</p>';
        } else {
          html += '<p class="error">✗ Failed to load domains: ' + (data.message || 'Unknown error') + '</p>';
        }

        displayResult('db-results', html);
      } catch (error) {
        displayResult('db-results', '<p class="error">✗ Error: ' + error.message + '</p>');
      }
    }

    async function testAPIs() {
      displayResult('api-results', '<p class="text-muted">Testing APIs...</p>');

      const tests = [
        { name: 'Domains List', url: '/admin/api/domains_filter.php?page=1&per_page=25' },
        { name: 'Domain Activity', url: '/admin/api/domain_activity.php?domain_id=1', optional: true }
      ];

      let html = '';

      for (const test of tests) {
        try {
          const response = await fetch(basePath + test.url);
          const data = await response.json();

          if (data.success) {
            html += `<p class="success">✓ ${test.name} - Working</p>`;
          } else {
            if (test.optional) {
              html += `<p class="warning">⚠️ ${test.name} - ${data.message || 'Failed'} (Optional)</p>`;
            } else {
              html += `<p class="error">✗ ${test.name} - ${data.message || 'Failed'}</p>`;
            }
          }
        } catch (error) {
          html += `<p class="error">✗ ${test.name} - Error: ${error.message}</p>`;
        }
      }

      displayResult('api-results', html);
    }

    async function createSampleDomain() {
      displayResult('domain-results', '<p class="text-muted">This would create a test domain in the database...</p>' +
        '<p>SQL to create test domain:</p>' +
        '<pre>INSERT INTO domains (user_id, domain_name, status, registration_date, expiry_date, locked, registrar)\n' +
        'VALUES (1, \'test-' + Date.now() + '.com\', \'active\', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 1, \'OnlineNIC\');</pre>' +
        '<p>Run this SQL in phpMyAdmin to create a test domain.</p>');
    }

    async function listDomains() {
      displayResult('domain-results', '<p class="text-muted">Loading domains...</p>');

      try {
        const response = await fetch(basePath + '/admin/api/domains_filter.php?page=1&per_page=10');
        const data = await response.json();

        if (data.success) {
          let html = `<p class="success">✓ Found ${data.data.total} domains</p>`;

          if (data.data.rows.length > 0) {
            html += '<table class="table table-sm table-bordered"><thead><tr>' +
              '<th>ID</th><th>Domain</th><th>Owner</th><th>Status</th><th>Expires</th>' +
              '</tr></thead><tbody>';

            data.data.rows.forEach(d => {
              html += `<tr>` +
                `<td>${d.id}</td>` +
                `<td>${d.domain_name}</td>` +
                `<td>${d.email || '#' + d.user_id}</td>` +
                `<td>${d.status || 'N/A'}</td>` +
                `<td>${d.expires_at || d.expiry_date || 'N/A'}</td>` +
                `</tr>`;
            });

            html += '</tbody></table>';
          } else {
            html += '<p>No domains to display. Create a test domain first.</p>';
          }

          displayResult('domain-results', html);
        } else {
          displayResult('domain-results', '<p class="error">✗ Failed: ' + (data.message || 'Unknown error') + '</p>');
        }
      } catch (error) {
        displayResult('domain-results', '<p class="error">✗ Error: ' + error.message + '</p>');
      }
    }

    async function testDomainLock() {
      const domainId = document.getElementById('test-domain-id').value;
      if (!domainId) {
        displayResult('action-results', '<p class="warning">Please enter a domain ID</p>');
        return;
      }

      displayResult('action-results', '<p class="text-muted">Testing lock/unlock...</p>');

      try {
        const formData = new FormData();
        formData.append('domain_id', domainId);

        const response = await fetch(basePath + '/admin/api/toggle_domain_lock.php', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();

        if (data.success) {
          displayResult('action-results', '<p class="success">✓ Lock toggled successfully</p>');
        } else {
          displayResult('action-results', '<p class="error">✗ Failed: ' + (data.message || 'Unknown error') + '</p>');
        }
      } catch (error) {
        displayResult('action-results', '<p class="error">✗ Error: ' + error.message + '</p>');
      }
    }

    async function testDomainRenew() {
      const domainId = document.getElementById('test-domain-id').value;
      if (!domainId) {
        displayResult('action-results', '<p class="warning">Please enter a domain ID</p>');
        return;
      }

      displayResult('action-results', '<p class="text-muted">Testing renew...</p>');

      try {
        const formData = new FormData();
        formData.append('domain_id', domainId);
        formData.append('period', '1');

        const response = await fetch(basePath + '/admin/api/renew_domain.php', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();

        if (data.success) {
          displayResult('action-results', '<p class="success">✓ Domain renewed successfully</p><pre>' + JSON.stringify(data, null, 2) + '</pre>');
        } else {
          displayResult('action-results', '<p class="error">✗ Failed: ' + (data.message || 'Unknown error') + '</p>');
        }
      } catch (error) {
        displayResult('action-results', '<p class="error">✗ Error: ' + error.message + '</p>');
      }
    }

    async function testAuthCode() {
      const domainId = document.getElementById('test-domain-id').value;
      if (!domainId) {
        displayResult('action-results', '<p class="warning">Please enter a domain ID</p>');
        return;
      }

      displayResult('action-results', '<p class="text-muted">Getting auth code...</p>');

      try {
        const response = await fetch(basePath + '/admin/api/get_auth_code.php?domain_id=' + domainId);
        const data = await response.json();

        if (data.success) {
          displayResult('action-results', '<p class="success">✓ Auth code retrieved</p><pre>' + JSON.stringify(data, null, 2) + '</pre>');
        } else {
          displayResult('action-results', '<p class="error">✗ Failed: ' + (data.message || 'Unknown error') + '</p>');
        }
      } catch (error) {
        displayResult('action-results', '<p class="error">✗ Error: ' + error.message + '</p>');
      }
    }

    // Auto-run structure check on load
    window.addEventListener('DOMContentLoaded', () => {
      checkDatabaseStructure();
    });
  </script>
</body>

</html>
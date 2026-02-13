<?php
$pageTitle = 'WHOIS Lookup';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"><i class="bi bi-search"></i> WHOIS Lookup</h5>
          <small class="opacity-75">Check domain registration information</small>
        </div>
        <div class="card-body">
          <form id="whoisForm" class="mb-4">
            <div class="input-group input-group-lg">
              <input type="text" class="form-control" id="whoisDomain" placeholder="example.com" required>
              <button type="submit" class="btn btn-primary">Lookup</button>
            </div>
          </form>

          <div id="whoisResult" style="display:none;">
            <h6 class="text-muted mb-2">WHOIS Record</h6>
            <pre id="whoisText" class="bg-light p-3 rounded small" style="max-height: 400px; overflow: auto;"></pre>
            <div id="whoisMeta" class="mt-3 row text-muted small"></div>
          </div>

          <div id="whoisError" class="alert alert-danger" style="display:none;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

  document.getElementById('whoisForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const domain = document.getElementById('whoisDomain').value.trim();
    const resultDiv = document.getElementById('whoisResult');
    const errorDiv = document.getElementById('whoisError');

    resultDiv.style.display = 'none';
    errorDiv.style.display = 'none';

    try {
      const r = await fetch(basePath + '/api/whois_lookup.php?domain=' + encodeURIComponent(domain));
      const d = await r.json();

      if (d.success) {
        document.getElementById('whoisText').textContent = d.data.whois_text;
        const meta = document.getElementById('whoisMeta');
        meta.innerHTML = '<div class="col-md-4">Registrar: ' + (d.data.registrar || '-') + '</div>' +
          '<div class="col-md-4">Created: ' + (d.data.created || '-') + '</div>' +
          '<div class="col-md-4">Expires: ' + (d.data.expires || '-') + '</div>';
        resultDiv.style.display = 'block';
      } else {
        errorDiv.textContent = d.message || 'Lookup failed';
        errorDiv.style.display = 'block';
      }
    } catch (err) {
      errorDiv.textContent = 'Network error. Please try again.';
      errorDiv.style.display = 'block';
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
$pageTitle = 'Transfer Domain';
require_once __DIR__ . '/../includes/header.php';
requireLogin();
?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-primary text-white">
          <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Transfer Domain to Your Account</h4>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            <h6><i class="bi bi-info-circle me-1"></i> Before transferring your domain:</h6>
            <ul class="mb-0 small">
              <li>Ensure your domain is <strong>unlocked</strong> at your current registrar</li>
              <li>Obtain the <strong>authorization (EPP/auth) code</strong> from your current registrar</li>
              <li>Make sure the domain is not within <strong>60 days of registration</strong></li>
              <li>Verify the registrant email address is accessible</li>
              <li>Transfer pricing varies by TLD - check pricing before proceeding</li>
            </ul>
          </div>

          <form id="transferForm">
            <div class="mb-4">
              <label for="domainName" class="form-label">Domain Name <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control form-control-lg"
                id="domainName"
                name="domain"
                placeholder="example.com"
                required
                autocomplete="off">
              <div class="form-text">Enter the full domain name you wish to transfer</div>
            </div>

            <div class="mb-4">
              <label for="authCode" class="form-label">Authorization Code (EPP Code) <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control"
                id="authCode"
                name="auth_code"
                placeholder="Enter auth/EPP code"
                required
                autocomplete="off">
              <div class="form-text">
                This code is provided by your current registrar. Also known as EPP code or transfer key.
              </div>
            </div>

            <div id="transferPricing" class="alert alert-secondary d-none mb-4">
              <h6 class="mb-2">Transfer Pricing</h6>
              <div class="d-flex justify-content-between align-items-center">
                <span>Transfer fee (includes 1 year renewal)</span>
                <strong class="text-primary fs-5">$<span id="transferPrice">0.00</span></strong>
              </div>
              <small class="text-muted d-block mt-2">
                <i class="bi bi-info-circle"></i> Domain transfers typically include a 1-year renewal at no extra cost
              </small>
            </div>

            <div id="errorMessage" class="alert alert-danger d-none"></div>
            <div id="successMessage" class="alert alert-success d-none"></div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                <i class="bi bi-check-circle me-1"></i> Check Domain & Add to Cart
              </button>
              <a href="<?php echo pageUrl('my_domains.php'); ?>" class="btn btn-outline-secondary btn-lg">
                Cancel
              </a>
            </div>
          </form>
        </div>
      </div>

      <div class="card mt-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="bi bi-question-circle me-1"></i> Transfer Process</h5>
        </div>
        <div class="card-body">
          <ol class="mb-0">
            <li class="mb-2"><strong>Submit Transfer Request:</strong> Enter your domain and auth code</li>
            <li class="mb-2"><strong>Verification:</strong> We verify the domain is eligible and auth code is valid</li>
            <li class="mb-2"><strong>Email Confirmation:</strong> You'll receive an email to approve the transfer</li>
            <li class="mb-2"><strong>Current Registrar Notification:</strong> Your current registrar is notified</li>
            <li class="mb-2"><strong>Transfer Completion:</strong> Transfer completes in 5-7 days (can be faster if approved)</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

// Check domain transfer eligibility when domain is entered
document.getElementById('domainName').addEventListener('blur', async function() {
  const domain = this.value.trim().toLowerCase();
  if (!domain || domain.indexOf('.') === -1) return;

  // Get TLD-specific transfer pricing
  try {
    const fd = new FormData();
    fd.append('action', 'check_transfer');
    fd.append('domain', domain);

    const response = await fetch(basePath + '/api/transfer_operations.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });

    const data = await response.json();

    if (data.success) {
      // Show transfer pricing
      document.getElementById('transferPrice').textContent = parseFloat(data.data.transfer_price || 0).toFixed(2);
      document.getElementById('transferPricing').classList.remove('d-none');
    }
  } catch (error) {
    console.error('Error checking transfer:', error);
  }
});

document.getElementById('transferForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const submitBtn = document.getElementById('submitBtn');
  const errorDiv = document.getElementById('errorMessage');
  const successDiv = document.getElementById('successMessage');

  errorDiv.classList.add('d-none');
  successDiv.classList.add('d-none');

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking...';

  const domain = document.getElementById('domainName').value.trim().toLowerCase();
  const authCode = document.getElementById('authCode').value.trim();

  try {
    const fd = new FormData();
    fd.append('action', 'add_transfer_to_cart');
    fd.append('domain', domain);
    fd.append('auth_code', authCode);

    const response = await fetch(basePath + '/api/transfer_operations.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });

    const data = await response.json();

    if (data.success) {
      successDiv.textContent = data.message || 'Transfer added to cart successfully!';
      successDiv.classList.remove('d-none');

      setTimeout(() => {
        window.location.href = basePath + '/pages/cart.php';
      }, 1500);
    } else {
      errorDiv.textContent = data.message || 'Failed to add transfer to cart';
      errorDiv.classList.remove('d-none');
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Check Domain & Add to Cart';
    }
  } catch (error) {
    errorDiv.textContent = 'An error occurred: ' + error.message;
    errorDiv.classList.remove('d-none');
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Check Domain & Add to Cart';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
if (isset($_SESSION['user_id'])) {
  header('Location: ' . BASE_PATH . '/pages/dashboard.php');
  exit;
}
$pageTitle = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container py-5">
  <div class="auth-card mx-auto" style="max-width: 450px;">
    <div class="text-center mb-4">
      <i class="bi bi-key-fill text-primary" style="font-size: 3rem;"></i>
      <h1 class="h3 mt-2">Forgot Password?</h1>
      <p class="text-muted">Enter your email and we'll send you a reset link</p>
    </div>

    <div id="message"></div>

    <form id="forgotForm">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control form-control-lg" required autofocus placeholder="you@example.com">
      </div>
      <button type="submit" class="btn btn-primary btn-lg w-100">Send Reset Link</button>
    </form>

    <div class="text-center mt-4">
      <a href="<?php echo pageUrl('login.php'); ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Login</a>
    </div>
  </div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

  document.getElementById('forgotForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const msg = document.getElementById('message');
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    const fd = new FormData(this);
    fd.append('action', 'request');

    try {
      const r = await fetch(basePath + '/api/password_reset.php', { method: 'POST', body: fd });
      const d = await r.json();
      msg.className = 'alert alert-' + (d.success ? 'success' : 'danger');
      msg.textContent = d.message;
      msg.style.display = 'block';
      if (d.success) {
        this.reset();
      }
    } catch (err) {
      msg.className = 'alert alert-danger';
      msg.textContent = 'Network error. Please try again.';
      msg.style.display = 'block';
    }
    btn.disabled = false;
    btn.textContent = 'Send Reset Link';
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

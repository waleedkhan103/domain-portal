<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
if (isset($_SESSION['user_id'])) {
  header('Location: ' . BASE_PATH . '/pages/dashboard.php');
  exit;
}
$token = $_GET['token'] ?? '';
$pageTitle = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container py-5">
  <div class="auth-card mx-auto" style="max-width: 450px;">
    <div class="text-center mb-4">
      <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
      <h1 class="h3 mt-2">Set New Password</h1>
      <p class="text-muted">Enter your new password below</p>
    </div>

    <div id="message"></div>

    <?php if (empty($token)): ?>
      <div class="alert alert-warning">Invalid or missing reset link. <a href="<?php echo pageUrl('forgot_password.php'); ?>">Request a new one</a>.</div>
    <?php else: ?>
      <form id="resetForm">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control form-control-lg" required minlength="8" placeholder="Min 8 characters">
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="password_confirm" class="form-control form-control-lg" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100">Reset Password</button>
      </form>
    <?php endif; ?>

    <div class="text-center mt-4">
      <a href="<?php echo pageUrl('login.php'); ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Login</a>
    </div>
  </div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

  const form = document.getElementById('resetForm');
  if (form) {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const p = this.querySelector('[name="password"]').value;
      const c = this.querySelector('[name="password_confirm"]').value;
      if (p !== c) {
        document.getElementById('message').className = 'alert alert-danger';
        document.getElementById('message').textContent = 'Passwords do not match';
        document.getElementById('message').style.display = 'block';
        return;
      }

      const fd = new FormData(this);
      fd.append('action', 'reset');

      try {
        const r = await fetch(basePath + '/api/password_reset.php', { method: 'POST', body: fd });
        const d = await r.json();
        const msg = document.getElementById('message');
        msg.className = 'alert alert-' + (d.success ? 'success' : 'danger');
        msg.textContent = d.message;
        msg.style.display = 'block';
        if (d.success && d.data && d.data.redirect) {
          setTimeout(() => { window.location.href = basePath + d.data.redirect; }, 1500);
        }
      } catch (err) {
        document.getElementById('message').className = 'alert alert-danger';
        document.getElementById('message').textContent = 'Network error. Please try again.';
        document.getElementById('message').style.display = 'block';
      }
    });
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

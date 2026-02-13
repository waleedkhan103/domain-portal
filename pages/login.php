<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
if (isset($_SESSION['user_id'])) {
  header('Location: ' . BASE_PATH . '/pages/dashboard.php');
  exit;
}
$redirect = $_GET['redirect'] ?? '';
$redirect = is_string($redirect) ? $redirect : '';
$redirect = (strlen($redirect) > 0 && $redirect[0] === '/') ? $redirect : '';
$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
  <div class="auth-card">
    <h1>Welcome Back</h1>
    <p>Login to your account</p>

    <div id="message"></div>

    <form id="loginForm">
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" required autofocus>
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
          <input type="checkbox" name="remember" class="form-check-input" id="remember">
          <label class="form-check-label" for="remember">Remember me</label>
        </div>
        <a href="<?php echo pageUrl('forgot_password.php'); ?>" class="text-decoration-none small">Forgot password?</a>
      </div>

      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>

    <div class="auth-footer">
      <p>Don't have an account? <a href="<?php echo pageUrl('register.php'); ?>">Register</a></p>
    </div>
  </div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

  document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('action', 'login');

    try {
      const response = await fetch(basePath + '/api/auth.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        showMessage(data.message, 'success');
        setTimeout(() => {
          window.location.href = basePath + data.data.redirect;
        }, 1000);
      } else {
        showMessage(data.message, 'error');
      }
    } catch (error) {
      showMessage('An error occurred. Please try again.', 'error');
    }
  });

  function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    messageDiv.className = 'alert alert-' + type;
    messageDiv.textContent = message;
    messageDiv.style.display = 'block';
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
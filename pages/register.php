<?php
session_start();
if (isset($_SESSION['user_id'])) {
  header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '/misc/waleed/domain-portal') . '/pages/dashboard.php');
  exit;
}
$pageTitle = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
  <div class="auth-card">
    <h1>Create Account</h1>
    <p>Join DomainPortal today</p>

    <div id="message"></div>

    <form id="registerForm">
      <div class="form-row">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="first_name" required>
        </div>

        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" required>
        </div>
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" required>
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input type="tel" name="phone" required>
      </div>

      <div class="form-group">
        <label>Country</label>
        <select name="country" required>
          <option value="US">United States</option>
          <option value="GB">United Kingdom</option>
          <option value="CA">Canada</option>
          <option value="AU">Australia</option>
          <option value="PK">Pakistan</option>
          <option value="IN">India</option>
        </select>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required minlength="8">
        <small>At least 8 characters</small>
      </div>

      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>
      </div>

      <button type="submit" class="btn-primary btn-block">Create Account</button>
    </form>

    <div class="auth-footer">
      <p>Already have an account? <a href="<?php echo pageUrl('login.php'); ?>">Login</a></p>
    </div>
  </div>
</div>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

  document.getElementById('registerForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    if (formData.get('password') !== formData.get('confirm_password')) {
      showMessage('Passwords do not match', 'error');
      return;
    }

    formData.append('action', 'register');

    try {
      console.log('Submitting registration to:', basePath + '/api/auth.php');

      const response = await fetch(basePath + '/api/auth.php', {
        method: 'POST',
        body: formData
      });

      console.log('Response status:', response.status);
      console.log('Response ok:', response.ok);

      const text = await response.text();
      console.log('Raw response:', text);

      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        console.error('JSON parse error:', e);
        showMessage('Server returned invalid response. Check console.', 'error');
        return;
      }

      if (data.success) {
        showMessage(data.message, 'success');
        setTimeout(() => {
          window.location.href = basePath + data.data.redirect;
        }, 1000);
      } else {
        showMessage(data.message || 'Registration failed', 'error');
      }
    } catch (error) {
      console.error('Fetch error:', error);
      console.error('Error message:', error.message);
      showMessage('Network error: ' + error.message, 'error');
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
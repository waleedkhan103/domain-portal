<?php
// CSRF helper for admin panel
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Token lifetime in seconds
define('CSRF_TOKEN_LIFETIME', 3600);

function generateCSRFToken()
{
  if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
    $_SESSION['csrf_tokens'] = [];
  }

  // prune expired tokens
  $now = time();
  foreach ($_SESSION['csrf_tokens'] as $t => $ts) {
    if (($now - $ts) > CSRF_TOKEN_LIFETIME) {
      unset($_SESSION['csrf_tokens'][$t]);
    }
  }

  $token = bin2hex(random_bytes(32));
  $_SESSION['csrf_tokens'][$token] = $now;
  return $token;
}

function validateCSRFToken($token)
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  if (empty($token) || !isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
    return false;
  }

  // validate and expire token (single-use)
  if (isset($_SESSION['csrf_tokens'][$token])) {
    unset($_SESSION['csrf_tokens'][$token]);
    // regenerate a fresh token for future forms
    generateCSRFToken();
    return true;
  }

  return false;
}

function getCSRFField()
{
  $token = generateCSRFToken();
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

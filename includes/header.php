<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Enable error display for local debugging (temporary)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser();
$cartCount = $currentUser
  ? getCartCount($currentUser['id'])
  : (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart']) ? count($_SESSION['guest_cart']) : 0);
?>
<!DOCTYPE html>
<html lang="en" data-base-path="<?php echo BASE_PATH; ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>
    <?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>DomainPortal
  </title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
</head>

<body>
  <!-- Bootstrap Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
      <a class="navbar-brand fw-bold" href="<?php echo BASE_PATH; ?>/index.php">
        <i class="bi bi-globe"></i> DomainPortal
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="<?php echo BASE_PATH; ?>/index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo pageUrl('domain_search.php'); ?>">Search Domains</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo pageUrl('whois.php'); ?>">WHOIS Lookup</a>
          </li>

          <?php if ($currentUser): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo pageUrl('dashboard.php'); ?>">Dashboard</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo pageUrl('my_domains.php'); ?>">My Domains</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo pageUrl('transfer_domain.php'); ?>">
                <i class="bi bi-arrow-left-right"></i> Transfer Domain
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo pageUrl('watchlist.php'); ?>">Watchlist</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo pageUrl('orders.php'); ?>">Orders</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo pageUrl('cart.php'); ?>">
                <i class="bi bi-cart"></i> Cart
                <?php if ($cartCount > 0): ?>
                  <span class="badge bg-danger"><?php echo $cartCount; ?></span>
                <?php endif; ?>
              </a>
            </li>

            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($currentUser['first_name']); ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li><a class="dropdown-item" href="<?php echo pageUrl('profile.php'); ?>"><i class="bi bi-person"></i>
                    Profile</a></li>
                <li><a class="dropdown-item" href="<?php echo pageUrl('orders.php'); ?>"><i class="bi bi-receipt"></i>
                    Orders</a></li>
                <li><a class="dropdown-item" href="<?php echo pageUrl('billing_history.php'); ?>"><i class="bi bi-credit-card"></i>
                    Billing History</a></li>
                <li><a class="dropdown-item" href="<?php echo pageUrl('contacts.php'); ?>"><i class="bi bi-people"></i>
                    Contacts</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/logout.php"><i
                      class="bi bi-box-arrow-right"></i> Logout</a></li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo pageUrl('cart.php'); ?>">
                <i class="bi bi-cart"></i> Cart
                <?php if ($cartCount > 0): ?>
                  <span class="badge bg-danger"><?php echo $cartCount; ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo pageUrl('login.php'); ?>">Login</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo pageUrl('register.php'); ?>">
                <button class="btn btn-primary btn-sm">Register</button>
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <main class="main-content">
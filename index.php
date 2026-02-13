<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <div class="hero-content">
      <h1>Find Your Perfect Domain</h1>
      <p>Search for and register your domain name in seconds</p>

      <form class="search-form" id="heroSearchForm">
        <div class="search-input-wrapper">
          <input type="text" id="heroSearchInput" placeholder="Search for your domain..." autocomplete="off">
          <button type="submit" class="btn-primary">Search</button>
        </div>
        <div class="search-suggestions" id="searchSuggestions"></div>
      </form>

      <div class="popular-tlds">
        <span>Popular:</span>
        <span class="tld-badge">.com</span>
        <span class="tld-badge">.net</span>
        <span class="tld-badge">.org</span>
        <span class="tld-badge">.io</span>
        <span class="tld-badge">.co</span>
      </div>
    </div>
  </div>
</section>

<section class="features">
  <div class="container">
    <h2>Why Choose Us</h2>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
          </svg>
        </div>
        <h3>Instant Registration</h3>
        <p>Register your domain in seconds with our automated system</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>
        <h3>Secure & Protected</h3>
        <p>Keep your domains safe with domain lock and privacy protection</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          </svg>
        </div>
        <h3>24/7 Support</h3>
        <p>Our team is always here to help you with your domains</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
          </svg>
        </div>
        <h3>Easy Management</h3>
        <p>Manage all your domains from one simple dashboard</p>
      </div>
    </div>
  </div>
</section>

<section class="cta">
  <div class="container">
    <div class="cta-content">
      <h2>Ready to Get Started?</h2>
      <p>Join thousands of satisfied customers</p>
      <a href="/pages/register.php" class="btn-primary btn-lg">Create Account</a>
    </div>
  </div>
</section>

<?php
$additionalScripts = ['/assets/js/domain_search.js'];
require_once __DIR__ . '/includes/footer.php';
?>
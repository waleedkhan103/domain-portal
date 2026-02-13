<?php
$pageTitle = 'Search Domains';
require_once __DIR__ . '/../includes/header.php';

$searchQuery = isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '';
?>

<section class="search-section">
  <div class="container">
    <h2>Search Domains</h2>

    <form class="search-form" id="searchForm">
      <div class="search-input-wrapper">
        <input type="text" id="searchInput" placeholder="Enter domain name..." value="<?php echo $searchQuery; ?>"
          autocomplete="off">
        <button type="submit" class="btn-primary">Check Availability</button>
      </div>
    </form>

    <div id="results" class="search-results">
      <?php if ($searchQuery): ?>
        <p class="text-center">Searching for <strong><?php echo $searchQuery; ?></strong>...</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
  const basePath = document.documentElement.getAttribute('data-base-path') || '/domain-portal';

  // Load initial search if query exists
  document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('searchInput');
    if (input.value.trim()) {
      searchDomain(input.value.trim());
    }
  });

  // Handle form submission
  document.getElementById('searchForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const domain = document.getElementById('searchInput').value.trim();
    if (domain) {
      searchDomain(domain);
    }
  });

  function searchDomain(domain) {
    const resultsBox = document.getElementById('results');
    resultsBox.innerHTML = '<p class="text-center">Checking availability of <strong>' + domain + '</strong>...</p>';

    const apiUrl = basePath + '/api/mock_domains.php';
    console.log('Fetching from:', apiUrl);

    fetch(apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({
        action: 'check',
        domain: domain
      })
      , credentials: 'same-origin'
    })
      .then(response => {
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        return response.json();
      })
      .then(data => {
        console.log('API Response:', data);

        if (!data.success) {
          resultsBox.innerHTML = '<div class="alert alert-error">Error: ' + (data.message || 'Unknown error') + '</div>';
          return;
        }

        const domainData = data.data;
        const statusClass = domainData.available ? 'available' : 'unavailable';
        const statusText = domainData.available ? 'Available' : 'Taken';
        const renewPrice = domainData.renewal_price || (domainData.price * 1.1);

        let html = '<div class="card mb-3">';
        html += '<div class="card-body">';
        html += '<div class="d-flex flex-wrap align-items-center gap-2 mb-2">';
        html += '<h5 class="mb-0">' + domainData.domain + '</h5>';
        html += '<span class="badge bg-' + (domainData.available ? 'success' : 'secondary') + '">' + statusText + '</span>';
        if (domainData.premium) html += '<span class="badge bg-warning text-dark">Premium</span>';
        html += '</div>';
        html += '<div class="d-flex flex-wrap gap-3 align-items-center">';
        html += '<span class="text-primary fw-bold">$' + parseFloat(domainData.price).toFixed(2) + '/yr</span>';
        html += '<span class="text-muted small">Renewal: $' + parseFloat(renewPrice).toFixed(2) + '/yr</span>';
        if (domainData.available) {
          html += '<button class="btn btn-primary btn-sm" onclick="addToCart(\'' + domainData.domain.replace(/'/g, "\\'") + '\', ' + domainData.price + ')">Add to Cart</button>';
        }
        html += '</div>';
        html += '</div></div>';

        if (domainData.suggestions && domainData.suggestions.length > 0) {
          html += '<div class="card border-info"><div class="card-header bg-info bg-opacity-10"><i class="bi bi-lightbulb"></i> Suggested alternatives</div><div class="card-body">';
          html += '<div class="row g-2">';
          domainData.suggestions.slice(0, 8).forEach(function(s) {
            html += '<div class="col-md-6 col-lg-4"><div class="d-flex justify-content-between align-items-center p-2 border rounded">';
            html += '<span>' + s.domain + '</span>';
            html += '<div>';
            html += '<span class="badge bg-' + (s.available ? 'success' : 'secondary') + ' me-1">' + (s.available ? 'Avail' : 'Taken') + '</span>';
            html += '<span class="text-muted small">$' + parseFloat(s.price).toFixed(2) + '</span>';
            if (s.available) html += ' <button class="btn btn-outline-primary btn-sm ms-1" onclick="addToCart(\'' + s.domain.replace(/'/g, "\\'") + '\', ' + s.price + ')">Add</button>';
            html += '</div></div></div>';
          });
          html += '</div></div></div>';
        }
        resultsBox.innerHTML = html;
      })
      .catch(error => {
        console.error('Fetch Error:', error);
        console.error('Error message:', error.message);
        resultsBox.innerHTML = '<div class="alert alert-error">Error checking domain. Details: ' + error.message + '</div>';
      });
  }

  function addToCart(domain, price) {
    const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('domain_name', domain);
    formData.append('price', price);
    formData.append('period', 1); // 1 year default

    fetch(basePath + '/api/cart_operations.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
      })
      .then(text => {
        console.log('Raw response:', text);
        if (!text) {
          throw new Error('Empty response from server');
        }
        return JSON.parse(text);
      })
      .then(data => {
        if (data.success) {
          alert('✓ ' + domain + ' added to cart!');
          window.location.href = basePath + '/pages/cart.php';
        } else {
          if ((data.message || '').toLowerCase().includes('not authenticated')) {
            window.location.href = basePath + '/pages/login.php';
            return;
          }
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Cart error:', error);
        alert('Error adding to cart: ' + error.message);
      });
  }
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
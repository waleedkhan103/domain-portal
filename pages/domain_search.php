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
    domain = domain.toLowerCase().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/$/, '');
    resultsBox.innerHTML = '<p class="text-center">Checking availability of <strong>' + domain + '</strong>...</p>';

    const apiUrl = basePath + '/api/domain_search.php';
    const hasTld = domain.indexOf('.') !== -1;

    if (!hasTld) {
      // No TLD typed — check common TLDs via check_multiple
      const tlds = ['.com', '.net', '.org', '.io', '.co'];
      const domains = tlds.map(t => domain + t);

      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'check_multiple', domains: JSON.stringify(domains) }),
        credentials: 'same-origin'
      })
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            resultsBox.innerHTML = '<div class="alert alert-error">Error: ' + (data.message || 'Unknown error') + '</div>';
            return;
          }
          resultsBox.innerHTML = renderMultipleResults(data.data);
        })
        .catch(error => {
          resultsBox.innerHTML = '<div class="alert alert-error">Error checking domain: ' + error.message + '</div>';
        });

    } else {
      // Full domain typed — check exact domain
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'check', domain: domain }),
        credentials: 'same-origin'
      })
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            resultsBox.innerHTML = '<div class="alert alert-error">Error: ' + (data.message || 'Unknown error') + '</div>';
            return;
          }
          const d = data.data;
          const price = d.registration_price || (d.prices && d.prices[0] ? d.prices[0].price : '12.99');
          const renewPrice = d.renewal_price || price;
          const privacyPrice = d.privacy_price || '2.99';
          let html = '<div class="card mb-3"><div class="card-body">';
          html += '<div class="d-flex flex-wrap align-items-center gap-2 mb-2">';
          html += '<h5 class="mb-0">' + d.domain + '</h5>';
          html += '<span class="badge bg-' + (d.available ? 'success' : 'secondary') + '">' + (d.available ? 'Available' : 'Taken') + '</span>';
          if (d.premium) html += '<span class="badge bg-warning text-dark">Premium</span>';
          html += '</div>';
          html += '<div class="d-flex flex-wrap gap-3 align-items-center">';
          html += '<span class="text-primary fw-bold">$' + parseFloat(price).toFixed(2) + '/yr</span>';
          html += '<span class="text-muted small">Renewal: $' + parseFloat(renewPrice).toFixed(2) + '/yr</span>';
          html += '<span class="text-muted small">Privacy: +$' + parseFloat(privacyPrice).toFixed(2) + '/yr</span>';
          if (d.available) {
            html += '<button class="btn btn-primary btn-sm" onclick="addToCart(\'' + d.domain.replace(/'/g, "\\'") + '\', ' + price + ')">Add to Cart</button>';
          }
          html += '</div></div></div>';
          resultsBox.innerHTML = html;
        })
        .catch(error => {
          resultsBox.innerHTML = '<div class="alert alert-error">Error checking domain: ' + error.message + '</div>';
        });
    }
  }

  function renderMultipleResults(list) {
    if (!list || list.length === 0) return '<div class="alert alert-error">No results found.</div>';
    let html = '<div class="row g-3">';
    list.forEach(function(d) {
      const price = d.price || '12.99';
      const renewPrice = d.renewal_price || price;
      html += '<div class="col-md-6 col-lg-4"><div class="card h-100"><div class="card-body d-flex flex-column">';
      html += '<div class="d-flex justify-content-between align-items-center mb-2">';
      html += '<strong>' + d.domain + '</strong>';
      html += '<span class="badge bg-' + (d.available ? 'success' : 'secondary') + '">' + (d.available ? 'Available' : 'Taken') + '</span>';
      html += '</div>';
      html += '<div class="small text-muted mb-2">Renewal: $' + parseFloat(renewPrice).toFixed(2) + '/yr</div>';
      html += '<div class="mt-auto d-flex justify-content-between align-items-center">';
      html += '<span class="text-primary fw-bold">$' + parseFloat(price).toFixed(2) + '/yr</span>';
      if (d.available) {
        html += '<button class="btn btn-primary btn-sm" onclick="addToCart(\'' + d.domain.replace(/'/g, "\\'") + '\', ' + price + ')">Add to Cart</button>';
      }
      html += '</div></div></div></div>';
    });
    html += '</div>';
    return html;
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
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
      })
      .then(text => {
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
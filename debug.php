<!DOCTYPE html>
<html>

<head>
  <title>Debug - Domain Portal</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 20px;
      background: #f5f5f5;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      background: white;
      padding: 20px;
      border-radius: 10px;
    }

    h1 {
      color: #333;
    }

    .test-section {
      margin: 20px 0;
      padding: 20px;
      background: #f9f9f9;
      border-radius: 5px;
    }

    .success {
      color: #10b981;
      font-weight: bold;
    }

    .error {
      color: #ef4444;
      font-weight: bold;
    }

    .warning {
      color: #f59e0b;
      font-weight: bold;
    }

    pre {
      background: #1f2937;
      color: #fff;
      padding: 15px;
      border-radius: 5px;
      overflow-x: auto;
    }

    button {
      background: #6366f1;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    button:hover {
      background: #4f46e5;
    }

    .test-result {
      margin: 10px 0;
      padding: 10px;
      border-left: 4px solid #ccc;
    }

    .test-result.success {
      border-color: #10b981;
      background: #d1fae5;
    }

    .test-result.error {
      border-color: #ef4444;
      background: #fee2e2;
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>🔍 Domain Portal Debug Page</h1>

    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<div class='test-section'>";
    echo "<h2>1. PHP Configuration</h2>";
    echo "PHP Version: <strong>" . phpversion() . "</strong><br>";
    echo "cURL Enabled: <strong>" . (function_exists('curl_version') ? '✓ Yes' : '✗ No') . "</strong><br>";
    if (function_exists('curl_version')) {
      $curl = curl_version();
      echo "cURL Version: <strong>" . $curl['version'] . "</strong><br>";
    }
    echo "</div>";

    // Test 2: File Structure
    echo "<div class='test-section'>";
    echo "<h2>2. File Structure</h2>";
    $files = [
      'config/database.php',
      'config/onlinenic.php',
      'api/OnlineNICAPI.php',
      'api/domain_search.php',
      'includes/functions.php'
    ];

    foreach ($files as $file) {
      $path = __DIR__ . '/' . $file;
      if (file_exists($path)) {
        echo "✓ <span class='success'>$file - Found</span><br>";
      } else {
        echo "✗ <span class='error'>$file - Missing</span><br>";
      }
    }
    echo "</div>";

    // Test 3: Database Connection
    echo "<div class='test-section'>";
    echo "<h2>3. Database Connection</h2>";
    try {
      require_once __DIR__ . '/config/database.php';
      if ($conn && mysqli_ping($conn)) {
        echo "<div class='test-result success'>✓ Database Connected Successfully</div>";
        echo "Database: <strong>" . DB_NAME . "</strong><br>";

        // Check tables
        $tables = ['users', 'domains', 'cart', 'orders'];
        echo "<br>Tables:<br>";
        foreach ($tables as $table) {
          $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
          if (mysqli_num_rows($result) > 0) {
            echo "✓ <span class='success'>$table</span><br>";
          } else {
            echo "✗ <span class='error'>$table (missing)</span><br>";
          }
        }
      } else {
        echo "<div class='test-result error'>✗ Database Connection Failed</div>";
      }
    } catch (Exception $e) {
      echo "<div class='test-result error'>✗ Error: " . $e->getMessage() . "</div>";
    }
    echo "</div>";

    // Test 4: OnlineNIC Configuration
    echo "<div class='test-section'>";
    echo "<h2>4. OnlineNIC API Configuration</h2>";
    try {
      require_once __DIR__ . '/config/onlinenic.php';
      echo "API User: <strong>" . ONLINENIC_USER . "</strong><br>";
      echo "API Key: <strong>" . substr(ONLINENIC_APIKEY, 0, 10) . "...</strong><br>";
      echo "Environment: <strong>" . ONLINENIC_ENV . "</strong><br>";
      echo "API URL: <strong>" . getOnlineNICBaseURL() . "</strong><br>";
      echo "<div class='test-result success'>✓ Configuration Loaded</div>";
    } catch (Exception $e) {
      echo "<div class='test-result error'>✗ Error: " . $e->getMessage() . "</div>";
    }
    echo "</div>";

    // Test 5: Direct API Test
    echo "<div class='test-section'>";
    echo "<h2>5. OnlineNIC API Connection Test</h2>";

    try {
      require_once __DIR__ . '/api/OnlineNICAPI.php';
      $api = new OnlineNICAPI();

      echo "<p>Testing domain: <strong>example.com</strong></p>";

      $result = $api->checkDomain('example.com', '1');

      echo "<strong>Response:</strong>";
      echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";

      if ($result['success']) {
        echo "<div class='test-result success'>";
        echo "✓ API is Working!<br>";
        echo "Domain: " . ($result['data']['domain'] ?? 'N/A') . "<br>";
        echo "Available: " . (($result['data']['avail'] ?? 0) == 1 ? 'Yes' : 'No') . "<br>";
        echo "Code: " . $result['code'];
        echo "</div>";
      } else {
        echo "<div class='test-result error'>";
        echo "✗ API Error<br>";
        echo "Message: " . $result['message'] . "<br>";
        echo "Code: " . $result['code'];
        echo "</div>";
      }

    } catch (Exception $e) {
      echo "<div class='test-result error'>✗ Exception: " . $e->getMessage() . "</div>";
    }
    echo "</div>";

    // Test 6: Test Multiple Domains
    echo "<div class='test-section'>";
    echo "<h2>6. Multiple Domain Test</h2>";

    $testDomains = ['test.com', 'example.net', 'sample.org'];

    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Domain</th>";
    echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Status</th>";
    echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Response</th>";
    echo "</tr>";

    foreach ($testDomains as $domain) {
      try {
        $result = $api->checkDomain($domain, '1');

        echo "<tr>";
        echo "<td style='padding: 10px; border: 1px solid #ddd;'><strong>$domain</strong></td>";

        if ($result['success']) {
          $avail = ($result['data']['avail'] ?? 0) == 1;
          $status = $avail ? 'Available' : 'Taken';
          $color = $avail ? '#10b981' : '#ef4444';
          echo "<td style='padding: 10px; border: 1px solid #ddd; color: $color;'>$status</td>";
          echo "<td style='padding: 10px; border: 1px solid #ddd;'><small>Code: " . $result['code'] . "</small></td>";
        } else {
          echo "<td style='padding: 10px; border: 1px solid #ddd; color: #ef4444;'>Error</td>";
          echo "<td style='padding: 10px; border: 1px solid #ddd;'><small>" . $result['message'] . "</small></td>";
        }
        echo "</tr>";

      } catch (Exception $e) {
        echo "<tr>";
        echo "<td style='padding: 10px; border: 1px solid #ddd;'>$domain</td>";
        echo "<td style='padding: 10px; border: 1px solid #ddd;' colspan='2'>Exception: " . $e->getMessage() . "</td>";
        echo "</tr>";
      }
    }

    echo "</table>";
    echo "</div>";

    // Test 7: Test Search API Endpoint
    echo "<div class='test-section'>";
    echo "<h2>7. Test domain_search.php API</h2>";
    echo "<button onclick='testSearchAPI()'>Test Search API</button>";
    echo "<div id='searchApiResult'></div>";
    echo "</div>";
    ?>

    <div class='test-section'>
      <h2>8. Test Search Page</h2>
      <form id="testSearchForm">
        <input type="text" id="testDomain" placeholder="Enter domain (e.g., test)"
          style="padding: 10px; width: 300px; border: 1px solid #ddd; border-radius: 5px;">
        <button type="submit">Search</button>
      </form>
      <div id="testSearchResult"></div>
    </div>

    <script>
      async function testSearchAPI() {
        const resultDiv = document.getElementById('searchApiResult');
        resultDiv.innerHTML = '<p>Testing...</p>';

        try {
          const response = await fetch('/domain-portal/api/domain_search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check_multiple&domains=' + encodeURIComponent(JSON.stringify(['test123.com', 'example456.net']))
          });

          const text = await response.text();
          console.log('API Response:', text);

          const data = JSON.parse(text);

          resultDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';

        } catch (error) {
          resultDiv.innerHTML = '<div class="test-result error">Error: ' + error.message + '</div>';
          console.error('Error:', error);
        }
      }

      document.getElementById('testSearchForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const domain = document.getElementById('testDomain').value.trim();
        const resultDiv = document.getElementById('testSearchResult');

        if (!domain) {
          alert('Please enter a domain');
          return;
        }

        resultDiv.innerHTML = '<p>Searching...</p>';

        const tlds = ['.com', '.net', '.org'];
        const domains = tlds.map(tld => domain + tld);

        try {
          const response = await fetch('/domain-portal/api/domain_search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check_multiple&domains=' + encodeURIComponent(JSON.stringify(domains))
          });

          const text = await response.text();
          console.log('Response:', text);

          const data = JSON.parse(text);

          if (data.success) {
            let html = '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
            html += '<tr><th style="border: 1px solid #ddd; padding: 10px;">Domain</th><th style="border: 1px solid #ddd; padding: 10px;">Status</th><th style="border: 1px solid #ddd; padding: 10px;">Price</th></tr>';

            data.data.forEach(result => {
              const status = result.available ? 'Available ✓' : 'Taken ✗';
              const color = result.available ? '#10b981' : '#ef4444';
              html += '<tr>';
              html += '<td style="border: 1px solid #ddd; padding: 10px;">' + result.domain + '</td>';
              html += '<td style="border: 1px solid #ddd; padding: 10px; color: ' + color + ';">' + status + '</td>';
              html += '<td style="border: 1px solid #ddd; padding: 10px;">$' + (result.price || '12.99') + '</td>';
              html += '</tr>';
            });

            html += '</table>';
            resultDiv.innerHTML = html;
          } else {
            resultDiv.innerHTML = '<div class="test-result error">Error: ' + data.message + '</div>';
          }

        } catch (error) {
          resultDiv.innerHTML = '<div class="test-result error">Error: ' + error.message + '</div>';
          console.error('Error:', error);
        }
      });
    </script>
  </div>
</body>

</html>
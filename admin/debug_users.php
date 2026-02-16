<?php
/**
 * Admin Users Page Debugger
 * Place this file in your admin folder and access it to diagnose issues
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';

?>
<!DOCTYPE html>
<html>

<head>
  <title>Admin Users Debug</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      padding: 20px;
      background: #f5f5f5;
    }

    .result {
      margin: 20px 0;
    }

    .success {
      color: green;
    }

    .error {
      color: red;
    }

    .warning {
      color: orange;
    }

    pre {
      background: #f8f9fa;
      padding: 10px;
      border-radius: 5px;
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>🔍 Admin Users Page Debugger</h1>
    <p>Checking issues at: <code>http://10.10.10.1/misc/waleed/domain-portal/admin/manage_users.php</code></p>

    <hr>

    <?php
    echo "<h2>1. Session Check</h2>";
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
      echo "<p class='success'>✓ Admin is logged in</p>";
      echo "<p>Admin ID: " . ($_SESSION['admin_id'] ?? 'N/A') . "</p>";
    } else {
      echo "<p class='error'>✗ Admin is NOT logged in</p>";
      echo "<p>You need to login first at: <a href='login.php'>Admin Login</a></p>";
    }

    echo "<h2>2. Database Connection</h2>";
    if ($conn) {
      echo "<p class='success'>✓ Database connected</p>";
    } else {
      echo "<p class='error'>✗ Database NOT connected: " . mysqli_connect_error() . "</p>";
      exit;
    }

    echo "<h2>3. Users Table Structure</h2>";
    $columns = mysqli_query($conn, "SHOW COLUMNS FROM users");
    if ($columns) {
      $foundColumns = [];
      echo "<table class='table table-sm'>";
      echo "<tr><th>Column</th><th>Type</th><th>Status</th></tr>";
      while ($col = mysqli_fetch_assoc($columns)) {
        $foundColumns[] = $col['Field'];
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>✓</td>";
        echo "</tr>";
      }
      echo "</table>";

      // Check required columns
      $required = ['id', 'email', 'password', 'first_name', 'last_name', 'is_admin', 'status', 'created_at'];
      $missing = array_diff($required, $foundColumns);

      if (empty($missing)) {
        echo "<p class='success'>✓ All required columns exist</p>";
      } else {
        echo "<p class='error'>✗ Missing columns: " . implode(', ', $missing) . "</p>";
        echo "<p>Run this SQL to fix:</p>";
        echo "<pre>";
        if (in_array('username', $missing))
          echo "ALTER TABLE users ADD COLUMN username VARCHAR(100) NULL AFTER email;\n";
        if (in_array('is_admin', $missing))
          echo "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER phone;\n";
        if (in_array('status', $missing))
          echo "ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active' AFTER is_admin;\n";
        echo "</pre>";
      }
    } else {
      echo "<p class='error'>✗ Cannot read users table</p>";
    }

    echo "<h2>4. Test the Exact API Call</h2>";
    $testUrl = BASE_PATH . "/admin/api/users_list.php?page=1&per=25&sort=created_at&dir=desc";
    echo "<p>Testing: <code>" . htmlspecialchars($testUrl) . "</code></p>";

    // Simulate the API call
    $_GET['page'] = 1;
    $_GET['per'] = 25;
    $_GET['sort'] = 'created_at';
    $_GET['dir'] = 'desc';

    ob_start();

    try {
      // Check if users exist
      $countSql = "SELECT COUNT(*) as cnt FROM users";
      $countRes = mysqli_query($conn, $countSql);
      $count = mysqli_fetch_assoc($countRes)['cnt'];

      echo "<p>Total users in database: <strong>$count</strong></p>";

      if ($count == 0) {
        echo "<div class='alert alert-warning'>";
        echo "<strong>⚠ No users in database!</strong><br>";
        echo "Create an admin user with this SQL:<br>";
        echo "<pre>";
        echo "INSERT INTO users (email, password, first_name, last_name, username, is_admin, status, phone, country)\n";
        echo "VALUES (\n";
        echo "  'admin@domainportal.com',\n";
        echo "  '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',\n";
        echo "  'Admin', 'User', 'admin', 1, 'active', '+1234567890', 'US'\n";
        echo ");";
        echo "</pre>";
        echo "</div>";
      } else {
        // Test the actual query from users_list.php
        $testQuery = "SELECT u.id, u.email, u.first_name, u.last_name, u.username, u.created_at, u.status, u.is_admin,
                              (SELECT COUNT(*) FROM domains d WHERE d.user_id = u.id) as domain_count,
                              (SELECT IFNULL(SUM(total),0) FROM orders o WHERE o.user_id = u.id) as total_spent
                              FROM users u
                              ORDER BY u.created_at DESC
                              LIMIT 25";

        echo "<h5>Running Admin Query:</h5>";
        echo "<pre>" . htmlspecialchars($testQuery) . "</pre>";

        $result = mysqli_query($conn, $testQuery);

        if ($result) {
          $rows = [];
          while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
          }

          echo "<div class='alert alert-success'>";
          echo "✓ Query executed successfully! Found " . count($rows) . " users.";
          echo "</div>";

          if (count($rows) > 0) {
            echo "<h5>Sample Results:</h5>";
            echo "<table class='table table-sm table-bordered'>";
            echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Admin</th><th>Status</th><th>Domains</th><th>Spent</th></tr>";
            foreach ($rows as $row) {
              echo "<tr>";
              echo "<td>" . $row['id'] . "</td>";
              echo "<td>" . htmlspecialchars($row['email']) . "</td>";
              echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
              echo "<td>" . ($row['is_admin'] ? 'Yes' : 'No') . "</td>";
              echo "<td>" . htmlspecialchars($row['status'] ?? 'N/A') . "</td>";
              echo "<td>" . $row['domain_count'] . "</td>";
              echo "<td>$" . number_format($row['total_spent'], 2) . "</td>";
              echo "</tr>";
            }
            echo "</table>";

            echo "<div class='alert alert-info'>";
            echo "<strong>✓ The query works!</strong><br>";
            echo "If manage_users.php still shows an error, the problem might be:<br>";
            echo "<ul>";
            echo "<li>JavaScript console errors (check browser DevTools)</li>";
            echo "<li>BASE_PATH configuration</li>";
            echo "<li>Session/authentication issues</li>";
            echo "<li>File permissions</li>";
            echo "</ul>";
            echo "</div>";
          }
        } else {
          echo "<div class='alert alert-danger'>";
          echo "✗ Query failed: " . htmlspecialchars(mysqli_error($conn));
          echo "</div>";

          // Try to identify the issue
          echo "<h5>Diagnosis:</h5>";
          $domainsCheck = mysqli_query($conn, "SHOW TABLES LIKE 'domains'");
          if (!$domainsCheck || mysqli_num_rows($domainsCheck) == 0) {
            echo "<p class='warning'>⚠ 'domains' table doesn't exist - create it or remove domain_count from query</p>";
          }
          $ordersCheck = mysqli_query($conn, "SHOW TABLES LIKE 'orders'");
          if (!$ordersCheck || mysqli_num_rows($ordersCheck) == 0) {
            echo "<p class='warning'>⚠ 'orders' table doesn't exist - create it or remove total_spent from query</p>";
          }
        }
      }
    } catch (Exception $e) {
      echo "<div class='alert alert-danger'>";
      echo "Exception: " . htmlspecialchars($e->getMessage());
      echo "</div>";
    }

    echo "<h2>5. Check Required Tables</h2>";
    $requiredTables = ['users', 'domains', 'orders'];
    foreach ($requiredTables as $table) {
      $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
      if ($check && mysqli_num_rows($check) > 0) {
        echo "<p class='success'>✓ Table '$table' exists</p>";
      } else {
        echo "<p class='error'>✗ Table '$table' is missing</p>";
      }
    }

    echo "<h2>6. Quick Fixes</h2>";
    echo "<div class='d-flex gap-2'>";
    echo "<a href='manage_users.php' class='btn btn-primary'>Go to Manage Users</a>";
    echo "<a href='login.php' class='btn btn-secondary'>Admin Login</a>";
    echo "<a href='../setup_database.php' class='btn btn-info'>Setup Database</a>";
    echo "</div>";

    echo "<hr>";
    echo "<h2>7. JavaScript Console Check</h2>";
    echo "<p>Open browser DevTools (F12) and check Console tab for JavaScript errors when loading manage_users.php</p>";

    echo "<hr>";
    echo "<h2>8. Direct API Test</h2>";
    echo "<p>Click below to test the API directly:</p>";
    echo "<a href='api/users_list.php?page=1&per=25' class='btn btn-warning' target='_blank'>Test users_list.php API</a>";
    ?>

  </div>
</body>

</html>
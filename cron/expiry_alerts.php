<?php
/**
 * Cron: Send expiry alerts for domains in watchlist
 * Run daily: php cron/expiry_alerts.php
 * Or via cron: 0 9 * * * php /path/to/domain-portal/cron/expiry_alerts.php
 */
require_once __DIR__ . '/../config/database.php';

if (!isset($conn) || !$conn) {
  die("Database connection failed\n");
}

// Ensure domain_watchlist exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS domain_watchlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  domain_name VARCHAR(255) NOT NULL,
  alert_days_before INT DEFAULT 30,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Get users' domains from 'domains' table with expiry_date
// Match watchlist domains to user domains and check if within alert window
$sql = "SELECT w.id, w.user_id, w.domain_name, w.alert_days_before, d.expiry_date, u.email, u.first_name
        FROM domain_watchlist w
        JOIN users u ON u.id = w.user_id
        JOIN domains d ON d.user_id = w.user_id AND d.domain_name = w.domain_name
        WHERE d.expiry_date IS NOT NULL";
$res = mysqli_query($conn, $sql);

$sent = 0;
while ($row = mysqli_fetch_assoc($res)) {
  $expiry = strtotime($row['expiry_date'] ?? '');
  if (!$expiry || $expiry < time()) continue;
  $daysLeft = floor(($expiry - time()) / 86400);
  if ($daysLeft <= (int) $row['alert_days_before'] && $daysLeft > 0) {
    $subject = "Domain Expiry Reminder: {$row['domain_name']}";
    $msg = "Hi {$row['first_name']},\n\nYour domain {$row['domain_name']} will expire in {$daysLeft} days.\n\nPlease renew it to avoid losing your domain.\n\nDomainPortal";
    $headers = "From: no-reply@domainportal.com\r\n";
    if (@mail($row['email'], $subject, $msg, $headers)) {
      $sent++;
    }
  }
}

echo "Sent $sent expiry alert(s)\n";

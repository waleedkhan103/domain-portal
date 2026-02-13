-- Domain Portal - Schema additions for new features
-- Run this SQL to add tables/columns for: password reset, promo codes, billing, contacts, watchlist, DNS records, 2FA

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (token),
  INDEX idx_expires (expires_at)
);

-- Promo/coupon codes
CREATE TABLE IF NOT EXISTS promo_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  discount_value DECIMAL(10,2) NOT NULL,
  min_order DECIMAL(10,2) DEFAULT 0,
  max_uses INT DEFAULT NULL,
  uses_count INT DEFAULT 0,
  valid_from DATETIME DEFAULT NULL,
  valid_until DATETIME DEFAULT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Billing/transaction history
CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  order_id INT DEFAULT NULL,
  type VARCHAR(50) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_user_date (user_id, created_at)
);

-- Domain watchlist for expiry alerts
CREATE TABLE IF NOT EXISTS domain_watchlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  domain_name VARCHAR(255) NOT NULL,
  alert_days_before INT DEFAULT 30,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_domain (user_id, domain_name)
);

-- Full DNS records (A, CNAME, MX, TXT, etc.)
CREATE TABLE IF NOT EXISTS dns_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NOT NULL,
  record_type VARCHAR(10) NOT NULL,
  host VARCHAR(255) NOT NULL,
  value TEXT NOT NULL,
  ttl INT DEFAULT 3600,
  priority INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
  INDEX idx_domain (domain_id)
);

-- 2FA columns for users (run if columns don't exist):
-- ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL;
-- ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0;

-- Contact type for contacts (Registrant, Admin, Tech, Billing):
-- ALTER TABLE contacts ADD COLUMN contact_type VARCHAR(20) DEFAULT 'registrant';

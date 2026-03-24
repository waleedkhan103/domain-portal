-- Wallet System Schema
-- Run this once on your database to enable the wallet feature.

-- 1. Add wallet balance columns to users table
--    (safe to run multiple times - uses IF NOT EXISTS logic via separate statements)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS wallet_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. Wallet audit table - every credit/debit is recorded here for full history
CREATE TABLE IF NOT EXISTS wallet_transactions (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  type              ENUM('credit','debit','refund') NOT NULL,
  amount            DECIMAL(10,2) NOT NULL,
  balance_before    DECIMAL(10,2) NOT NULL,
  balance_after     DECIMAL(10,2) NOT NULL,
  order_id          INT DEFAULT NULL,
  description       VARCHAR(255) NOT NULL,
  added_by          INT DEFAULT NULL COMMENT 'Admin user ID for manual credits',
  payment_method    VARCHAR(50) DEFAULT NULL COMMENT 'wallet, stripe, mixed, admin_credit',
  stripe_payment_id VARCHAR(255) DEFAULT NULL,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_order (order_id),
  INDEX idx_created (created_at)
);

-- 3. Add payment_method column to transactions table (if it exists)
--    This tracks how each order was paid (wallet / stripe / mixed)
ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT NULL AFTER description,
  ADD COLUMN IF NOT EXISTS wallet_amount  DECIMAL(10,2) DEFAULT 0.00 AFTER payment_method,
  ADD COLUMN IF NOT EXISTS stripe_payment_id VARCHAR(255) DEFAULT NULL AFTER wallet_amount;

-- 4. Ensure the settings table has Stripe key rows (insert if missing)
CREATE TABLE IF NOT EXISTS settings (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  setting_key   VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  setting_group VARCHAR(50) DEFAULT 'general',
  description   VARCHAR(255),
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, description) VALUES
  ('stripe_public_key', '', 'payment', 'Stripe Publishable Key (pk_test_... or pk_live_...)'),
  ('stripe_secret_key', '', 'payment', 'Stripe Secret Key (sk_test_... or sk_live_...)'),
  ('stripe_enabled',    '0', 'payment', 'Enable Stripe card payments (1=yes, 0=no)');

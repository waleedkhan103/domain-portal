-- Domain Transfers Tracking Table
-- Tracks the status of domain transfer operations

CREATE TABLE IF NOT EXISTS domain_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NULL,
  domain_name VARCHAR(255) NOT NULL,
  user_id INT NOT NULL,
  auth_code VARCHAR(255) NOT NULL,
  status ENUM('pending', 'in_progress', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
  transfer_type ENUM('in', 'out') DEFAULT 'in',
  initiated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  estimated_completion DATETIME NULL,
  failure_reason TEXT NULL,
  current_registrar VARCHAR(255) NULL,
  foa_email_sent TINYINT(1) DEFAULT 0,
  foa_approved TINYINT(1) DEFAULT 0,
  foa_approved_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_domain_name (domain_name),
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_transfer_type (transfer_type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add transfer-related columns to order_items table if not exists
ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS auth_code VARCHAR(255) NULL AFTER price,
  ADD COLUMN IF NOT EXISTS transfer_status ENUM('pending', 'in_progress', 'completed', 'failed') NULL AFTER auth_code;

COMMENT ON TABLE domain_transfers IS 'Tracks domain transfer requests (both incoming and outgoing)';

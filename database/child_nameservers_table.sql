-- Child Nameservers (Glue Records) Table
-- Allows creating nameservers within the domain itself (e.g., ns1.example.com)

CREATE TABLE IF NOT EXISTS child_nameservers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NOT NULL,
  hostname VARCHAR(255) NOT NULL,
  ipv4_address VARCHAR(45) NULL,
  ipv6_address VARCHAR(45) NULL,
  status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY unique_hostname_per_domain (domain_id, hostname),
  INDEX idx_domain_id (domain_id),
  INDEX idx_status (status),
  FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for hostname lookups
CREATE INDEX idx_hostname ON child_nameservers(hostname);

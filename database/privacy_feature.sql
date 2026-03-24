-- Domain Privacy/WHOIS Protection Feature
-- Add privacy_enabled column to domains table

ALTER TABLE domains
ADD COLUMN privacy_enabled TINYINT(1) DEFAULT 0 AFTER is_locked,
ADD COLUMN privacy_fee DECIMAL(10,2) DEFAULT 0.00 AFTER privacy_enabled;

-- Add index for faster privacy queries
CREATE INDEX idx_privacy ON domains(privacy_enabled);

-- Note: When privacy_enabled = 1, the registrant's contact information
-- should be replaced with the registrar's privacy service contact
-- in WHOIS lookups instead of showing the customer's real details.

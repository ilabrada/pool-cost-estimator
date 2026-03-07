-- Migration 002: Add standard_tier_discount to settings
-- Configurable percentage applied to the unit price for standard-tier clients.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`)
VALUES ('standard_tier_discount', '10');

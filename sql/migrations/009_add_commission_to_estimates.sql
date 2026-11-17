-- Migration 009: Estimator commission (internal; folded into the client-facing subtotal, never shown to the client)
ALTER TABLE `estimates`
    ADD COLUMN `commission_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`,
    ADD COLUMN `commission_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `commission_percent`;

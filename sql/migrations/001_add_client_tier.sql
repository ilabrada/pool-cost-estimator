-- Migration 001: Add tier column to clients table
-- Classifies clients as priority (default) or standard (receives rate adjustment).
ALTER TABLE `clients`
    ADD COLUMN `tier` ENUM('priority','standard') NOT NULL DEFAULT 'priority'
    AFTER `notes`;

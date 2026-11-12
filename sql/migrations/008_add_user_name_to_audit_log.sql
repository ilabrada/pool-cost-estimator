-- Migration 008: Record the name of the user who performed each audited action
ALTER TABLE `audit_log`
    ADD COLUMN `user_name` VARCHAR(100) DEFAULT NULL AFTER `user_role`;

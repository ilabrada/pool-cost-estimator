-- Migration 006: Track which estimator owns each estimate (NULL = admin)
ALTER TABLE `estimates`
    ADD COLUMN `estimator_id` INT DEFAULT NULL AFTER `client_id`,
    ADD INDEX `idx_estimate_estimator` (`estimator_id`),
    ADD CONSTRAINT `fk_estimates_estimator` FOREIGN KEY (`estimator_id`) REFERENCES `estimators`(`id`) ON DELETE SET NULL;

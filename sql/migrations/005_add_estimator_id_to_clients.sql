-- Migration 005: Track which estimator owns each client (NULL = admin)
ALTER TABLE `clients`
    ADD COLUMN `estimator_id` INT DEFAULT NULL AFTER `tier`,
    ADD INDEX `idx_client_estimator` (`estimator_id`),
    ADD CONSTRAINT `fk_clients_estimator` FOREIGN KEY (`estimator_id`) REFERENCES `estimators`(`id`) ON DELETE SET NULL;

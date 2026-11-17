-- Migration 010: Maximum commission % an estimator may add (0 = estimators cannot add commission)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`)
VALUES ('max_estimator_commission', '0');

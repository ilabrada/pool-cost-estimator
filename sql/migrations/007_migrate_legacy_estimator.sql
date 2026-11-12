-- Migration 007: Move the legacy single estimator PIN (settings) into the estimators table.
-- Only runs when the legacy estimator was enabled and no estimators exist yet.
INSERT INTO `estimators` (`name`, `pin_hash`, `active`)
SELECT 'Estimator', h.setting_value, 1
FROM `settings` h
JOIN `settings` en ON en.setting_key = 'estimator_enabled' AND en.setting_value = '1'
WHERE h.setting_key = 'estimator_pin_hash'
  AND h.setting_value <> ''
  AND NOT EXISTS (SELECT 1 FROM `estimators`);

-- Pool Cost Estimator - Database Schema
-- Run this via install.php or manually in phpMyAdmin

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Settings table (key-value store for app configuration)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(50) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Pricing configuration
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pricing` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(50) NOT NULL,
    `item_key` VARCHAR(50) NOT NULL UNIQUE,
    `item_label` VARCHAR(100) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(20) DEFAULT 'each',
    `description` TEXT DEFAULT NULL,
    `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Clients
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estimates
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estimates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `estimate_number` VARCHAR(20) NOT NULL UNIQUE,
    `client_id` INT DEFAULT NULL,

    -- Pool Dimensions
    `pool_length` DECIMAL(8,2) DEFAULT 0.00,
    `pool_width` DECIMAL(8,2) DEFAULT 0.00,
    `pool_depth_shallow` DECIMAL(8,2) DEFAULT 0.00,
    `pool_depth_deep` DECIMAL(8,2) DEFAULT 0.00,
    `pool_shape` VARCHAR(20) DEFAULT 'rectangular',

    -- Pool Construction
    `pool_material` VARCHAR(20) DEFAULT 'concrete',
    `interior_finish` VARCHAR(20) DEFAULT 'plaster',

    -- Features
    `has_jacuzzi` TINYINT(1) DEFAULT 0,
    `jacuzzi_size` VARCHAR(20) DEFAULT 'standard',
    `num_lights` INT DEFAULT 0,
    `has_heating` TINYINT(1) DEFAULT 0,
    `heating_type` VARCHAR(30) DEFAULT 'gas',
    `has_waterfall` TINYINT(1) DEFAULT 0,
    `has_water_feature` TINYINT(1) DEFAULT 0,
    `has_auto_cover` TINYINT(1) DEFAULT 0,
    `has_pool_cleaner` TINYINT(1) DEFAULT 0,

    -- Deck
    `has_deck` TINYINT(1) DEFAULT 0,
    `deck_material` VARCHAR(30) DEFAULT 'concrete',
    `deck_area` DECIMAL(8,2) DEFAULT 0.00,

    -- Fence
    `has_fence` TINYINT(1) DEFAULT 0,
    `fence_type` VARCHAR(30) DEFAULT 'aluminum',
    `fence_length` DECIMAL(8,2) DEFAULT 0.00,

    -- Totals
    `subtotal` DECIMAL(12,2) DEFAULT 0.00,
    `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
    `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
    `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
    `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
    `total` DECIMAL(12,2) DEFAULT 0.00,

    -- Meta
    `notes` TEXT DEFAULT NULL,
    `internal_notes` TEXT DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'draft',
    `valid_until` DATE DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_client` (`client_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estimate line items (cost breakdown)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estimate_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `estimate_id` INT NOT NULL,
    `category` VARCHAR(50) DEFAULT 'general',
    `description` VARCHAR(200) NOT NULL,
    `quantity` DECIMAL(10,2) DEFAULT 1.00,
    `unit` VARCHAR(20) DEFAULT 'each',
    `unit_price` DECIMAL(10,2) DEFAULT 0.00,
    `total` DECIMAL(12,2) DEFAULT 0.00,
    `sort_order` INT DEFAULT 0,
    `is_custom` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`estimate_id`) REFERENCES `estimates`(`id`) ON DELETE CASCADE,
    INDEX `idx_estimate` (`estimate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Default Settings
-- --------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('business_name', 'Pool Builder Pro'),
('business_phone', ''),
('business_email', ''),
('business_address', ''),
('business_logo', ''),
('tax_rate', '7.00'),
('currency_symbol', '$'),
('measurement_unit', 'ft'),
('estimate_validity_days', '30'),
('estimate_terms', 'This estimate is valid for 30 days from the date of issue. Prices are subject to change based on site conditions. A 50% deposit is required to begin work. Final payment is due upon completion. All work includes a 1-year warranty on craftsmanship.'),
('estimate_prefix', 'EST'),
('pin_hash', ''),
('installed', '0');

-- --------------------------------------------------------
-- Default Pricing
-- --------------------------------------------------------
INSERT INTO `pricing` (`category`, `item_key`, `item_label`, `unit_price`, `unit`, `description`, `sort_order`) VALUES
-- Excavation
('excavation', 'excavation', 'Excavation & Grading', 12.00, 'cu ft', 'Site excavation based on pool volume', 1),
('excavation', 'hauling', 'Dirt Hauling & Disposal', 3.00, 'cu ft', 'Removal and disposal of excavated material', 2),

-- Pool Shell
('shell', 'shell_concrete', 'Concrete/Gunite Shell', 55.00, 'sq ft', 'Shotcrete or gunite pool shell', 10),
('shell', 'shell_fiberglass', 'Fiberglass Shell', 42.00, 'sq ft', 'Pre-formed fiberglass pool shell', 11),
('shell', 'shell_vinyl', 'Vinyl Liner Pool', 28.00, 'sq ft', 'Vinyl liner with steel/polymer walls', 12),

-- Interior Finish
('finish', 'finish_plaster', 'Standard Plaster', 9.00, 'sq ft', 'White or colored plaster finish', 20),
('finish', 'finish_pebble', 'Pebble Finish (PebbleTec)', 14.00, 'sq ft', 'Premium pebble aggregate finish', 21),
('finish', 'finish_quartz', 'Quartz Finish', 12.00, 'sq ft', 'Quartz aggregate finish', 22),
('finish', 'finish_tile', 'Full Tile Finish', 30.00, 'sq ft', 'Porcelain or glass tile finish', 23),

-- Plumbing & Equipment
('equipment', 'plumbing', 'Plumbing Package', 3500.00, 'flat', 'Complete plumbing with PVC pipes, valves, and fittings', 30),
('equipment', 'electrical', 'Electrical Package', 2800.00, 'flat', 'Electrical wiring, panel, GFCI, bonding', 31),
('equipment', 'filtration', 'Filtration System', 2200.00, 'flat', 'Pump, filter, and skimmer system', 32),
('equipment', 'equipment_pad', 'Equipment Pad', 800.00, 'flat', 'Concrete pad for pool equipment', 33),

-- Tile & Coping
('tile', 'coping', 'Pool Coping', 35.00, 'lin ft', 'Coping stones around pool perimeter', 40),
('tile', 'waterline_tile', 'Waterline Tile', 25.00, 'lin ft', '6-inch waterline tile band', 41),

-- Features
('features', 'jacuzzi_standard', 'Spa/Jacuzzi (Standard)', 8500.00, 'flat', 'Built-in spa with jets (6-8 person)', 50),
('features', 'jacuzzi_large', 'Spa/Jacuzzi (Large)', 12000.00, 'flat', 'Large built-in spa with jets (8-12 person)', 51),
('features', 'led_light', 'LED Pool Light', 850.00, 'each', 'Color-changing LED pool light', 52),
('features', 'heater_gas', 'Gas Pool Heater', 4200.00, 'flat', 'Natural gas or propane pool heater', 53),
('features', 'heater_heatpump', 'Heat Pump', 5500.00, 'flat', 'Electric heat pump (energy efficient)', 54),
('features', 'heater_solar', 'Solar Heating System', 3800.00, 'flat', 'Solar panel heating system', 55),
('features', 'waterfall', 'Rock Waterfall', 4500.00, 'flat', 'Natural rock waterfall feature', 56),
('features', 'water_feature', 'Water Feature (Fountain/Scupper)', 2000.00, 'flat', 'Decorative water feature', 57),
('features', 'auto_cover', 'Automatic Pool Cover', 18.00, 'sq ft', 'Motorized safety cover', 58),
('features', 'pool_cleaner', 'Automatic Pool Cleaner', 1200.00, 'flat', 'Robotic or suction-side pool cleaner', 59),

-- Deck
('deck', 'deck_concrete', 'Standard Concrete Deck', 12.00, 'sq ft', 'Brushed concrete pool deck', 60),
('deck', 'deck_stamped', 'Stamped Concrete Deck', 18.00, 'sq ft', 'Decorative stamped concrete', 61),
('deck', 'deck_pavers', 'Paver Deck', 22.00, 'sq ft', 'Interlocking paver stones', 62),
('deck', 'deck_travertine', 'Travertine Deck', 28.00, 'sq ft', 'Natural travertine pavers', 63),

-- Fence
('fence', 'fence_aluminum', 'Aluminum Fence', 32.00, 'lin ft', 'Code-compliant aluminum pool fence', 70),
('fence', 'fence_glass', 'Glass Panel Fence', 85.00, 'lin ft', 'Frameless glass pool fence', 71),
('fence', 'fence_mesh', 'Mesh Safety Fence', 20.00, 'lin ft', 'Removable mesh safety fence', 72),

-- Other
('other', 'permits', 'Permits & Inspections', 1500.00, 'flat', 'Building permits and required inspections', 80),
('other', 'engineering', 'Engineering & Design', 1200.00, 'flat', 'Structural engineering and pool design', 81),
('other', 'startup', 'Pool Startup & Chemical Balance', 500.00, 'flat', 'Initial chemical treatment and startup', 82);

COMMIT;

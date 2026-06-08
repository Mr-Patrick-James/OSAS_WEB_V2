-- Migration: Add system_settings table for persistent key-value config
-- Run this once on your AWS database

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the last_monthly_reset key (empty = will trigger on next API call)
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`)
VALUES ('last_monthly_reset', '');

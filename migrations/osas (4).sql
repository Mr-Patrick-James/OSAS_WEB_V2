-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 02, 2026 at 08:29 AM
-- Server version: 8.3.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `osas`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `add_student_violation`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `add_student_violation` (IN `p_student_id` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci, IN `p_violation_type` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci, IN `p_violation_date` DATE, IN `p_violation_time` TIME, IN `p_location` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci, IN `p_reported_by` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci, IN `p_notes` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci, IN `p_case_id` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci)   BEGIN
        DECLARE v_current_level VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL;
        DECLARE v_previous_level VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL;
        DECLARE v_permitted_count INT DEFAULT 0;
        DECLARE v_warning_count INT DEFAULT 0;
        DECLARE v_total_violations INT DEFAULT 0;
        DECLARE v_level_id INT DEFAULT NULL;
        DECLARE v_new_level VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL;
        
        -- Check if student violation level exists
        SELECT id, current_level, permitted_count, warning_count, total_violations
        INTO v_level_id, v_current_level, v_permitted_count, v_warning_count, v_total_violations
        FROM student_violation_levels
        WHERE student_id = p_student_id COLLATE utf8mb4_0900_ai_ci 
          AND violation_type = p_violation_type COLLATE utf8mb4_0900_ai_ci;
        
        IF v_level_id IS NULL THEN
            -- Create new violation level record
            INSERT INTO student_violation_levels (
                student_id, violation_type, current_level, 
                permitted_count, warning_count, total_violations,
                last_violation_date, last_violation_time, last_location,
                last_reported_by, last_notes, status
            ) VALUES (
                p_student_id, p_violation_type, 'permitted1',
                1, 0, 1,
                p_violation_date, p_violation_time, p_location,
                p_reported_by, p_notes, 'active'
            );
            
            SET v_level_id = LAST_INSERT_ID();
            SET v_previous_level = NULL;
            SET v_new_level = 'permitted1';
            SET v_total_violations = 1;
        ELSE
            -- Update existing record
            SET v_previous_level = v_current_level;
            SET v_total_violations = v_total_violations + 1;
            
            -- Determine new level based on total violations
            SET v_new_level = get_next_violation_level(v_current_level, v_total_violations);
            
            -- Update counts based on new level
            IF v_new_level LIKE 'permitted%' THEN
                SET v_permitted_count = v_permitted_count + 1;
            ELSEIF v_new_level LIKE 'warning%' THEN
                SET v_warning_count = v_warning_count + 1;
            END IF;
            
            -- Update the violation level record
            UPDATE student_violation_levels SET
                current_level = v_new_level,
                permitted_count = v_permitted_count,
                warning_count = v_warning_count,
                total_violations = v_total_violations,
                last_violation_date = p_violation_date,
                last_violation_time = p_violation_time,
                last_location = p_location,
                last_reported_by = p_reported_by,
                last_notes = p_notes,
                status = IF(v_new_level = 'disciplinary', 'disciplinary', 'active'),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = v_level_id;
        END IF;
        
        -- Add to history
        INSERT INTO violation_history (
            student_violation_level_id, student_id, violation_type,
            previous_level, new_level, violation_date, violation_time,
            location, reported_by, notes, case_id
        ) VALUES (
            v_level_id, p_student_id, p_violation_type,
            v_previous_level, v_new_level, p_violation_date, p_violation_time,
            p_location, p_reported_by, p_notes, p_case_id
        );
        
        -- Return the result
        SELECT 
            v_level_id as id,
            p_student_id as student_id,
            p_violation_type as violation_type,
            v_new_level as current_level,
            v_permitted_count as permitted_count,
            v_warning_count as warning_count,
            v_total_violations as total_violations,
            p_case_id as case_id;
    END$$

--
-- Functions
--
DROP FUNCTION IF EXISTS `get_next_violation_level`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `get_next_violation_level` (`current_level` VARCHAR(50), `total_violations` INT) RETURNS VARCHAR(50) CHARSET utf8mb4 DETERMINISTIC READS SQL DATA BEGIN
        DECLARE next_level VARCHAR(50);
        
        CASE current_level
            WHEN 'permitted1' THEN
                SET next_level = IF(total_violations >= 2, 'permitted2', 'permitted1');
            WHEN 'permitted2' THEN
                SET next_level = IF(total_violations >= 3, 'warning1', 'permitted2');
            WHEN 'warning1' THEN
                SET next_level = IF(total_violations >= 4, 'warning2', 'warning1');
            WHEN 'warning2' THEN
                SET next_level = IF(total_violations >= 5, 'warning3', 'warning2');
            WHEN 'warning3' THEN
                SET next_level = IF(total_violations >= 6, 'disciplinary', 'warning3');
            ELSE
                SET next_level = 'disciplinary';
        END CASE;
        
        RETURN next_level;
    END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','urgent','warning') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `status` enum('active','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `head_of_department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_code` (`department_code`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `department_code`, `head_of_department`, `description`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Bachelor of Technical-Vocational Teacher Education', 'BTVTED', 'Pamela Faye Gelena', '', 'active', '2026-02-22 22:29:58', '2026-03-12 09:31:13', NULL),
(2, 'Bachelor of Public Administration', 'BPA', 'Cedrick H. Almarez', '', 'active', '2026-02-22 22:29:58', '2026-03-12 08:44:03', NULL),
(3, 'Bachelor of Science in Information Systems', 'BSIS', 'June Paul Anouwevo', '', 'active', '2026-02-22 22:29:58', '2026-03-11 15:16:30', NULL),
(11, '04689457', 'wer', 'wer', 'werewr', 'archived', '2026-03-12 09:25:42', '2026-03-12 09:25:48', NULL),
(12, 'BSIT1', 'IT-0012', 'Pamela Faye Gelena', '', 'archived', '2026-03-12 10:16:27', '2026-03-12 10:22:54', NULL),
(14, 'BSIT12', 'IT-00122', 'Pamela Faye Gelena', '', 'archived', '2026-03-12 10:23:29', '2026-03-12 19:18:57', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `announcement_id` int NOT NULL,
  `sender_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_role` enum('admin','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_announcement_id` (`announcement_id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
CREATE TABLE IF NOT EXISTS `reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section_id` int DEFAULT NULL,
  `yearlevel` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uniform_count` int DEFAULT '0',
  `footwear_count` int DEFAULT '0',
  `no_id_count` int DEFAULT '0',
  `total_violations` int DEFAULT '0',
  `status` enum('permitted','warning','disciplinary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'permitted',
  `last_violation_date` date DEFAULT NULL,
  `report_period_start` date DEFAULT NULL,
  `report_period_end` date DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_id` (`report_id`),
  UNIQUE KEY `unique_report_id` (`report_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_department` (`department_code`),
  KEY `idx_section` (`section_id`),
  KEY `idx_status` (`status`),
  KEY `idx_generated_at` (`generated_at`),
  KEY `idx_report_period` (`report_period_start`,`report_period_end`),
  KEY `idx_reports_student_dept` (`student_id`,`department_code`),
  KEY `idx_reports_status_date` (`status`,`generated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_recommendations`
--

DROP TABLE IF EXISTS `report_recommendations`;
CREATE TABLE IF NOT EXISTS `report_recommendations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `recommendation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','medium','high') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB AUTO_INCREMENT=499 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_violations`
--

DROP TABLE IF EXISTS `report_violations`;
CREATE TABLE IF NOT EXISTS `report_violations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `violation_id` int DEFAULT NULL,
  `violation_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `violation_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `violation_date` date NOT NULL,
  `violation_time` time DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`),
  KEY `idx_violation_id` (`violation_id`),
  KEY `idx_violation_date` (`violation_date`)
) ENGINE=InnoDB AUTO_INCREMENT=334 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

DROP TABLE IF EXISTS `sections`;
CREATE TABLE IF NOT EXISTS `sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` int NOT NULL,
  `academic_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_code` (`section_code`),
  KEY `department_id` (`department_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `section_code`, `department_id`, `academic_year`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'BTVTED-WFT1', 'BTVTED-WFT1', 1, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(2, 'BTVTED-CHS1', 'BTVTED-CHS1', 1, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(3, 'BPA1', 'BPA1', 2, '2023-2026', 'active', '2026-02-22 22:29:58', '2026-03-12 09:51:39', NULL),
(4, 'BSIS1', 'BSIS1', 3, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(5, 'BTVTED-WFT2', 'BTVTED-WFT2', 1, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(6, 'BTVTED-CHS2', 'BTVTED-CHS2', 1, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(7, 'BPA2', 'BPA2', 2, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(8, 'BSIS2', 'BSIS2', 3, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(9, 'BTVTED-CHS3', 'BTVTED-CHS3', 1, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(10, 'BTVTED-WFT3', 'BTVTED-WFT3', 1, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(11, 'BPA3', 'BPA3', 2, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(12, 'BSIS3', 'BSIS3', 3, '2024-2025', 'active', '2026-02-22 22:29:58', '2026-03-15 16:05:41', NULL),
(13, 'BTVTED-CHS4', 'BTVTED-CHS4', 1, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL),
(14, 'BTVTED-WFT4', 'BTVTED-WFT4', 1, NULL, 'active', '2026-02-22 22:29:58', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `setting_type` enum('string','integer','boolean','json') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  UNIQUE KEY `unique_setting_key` (`setting_key`),
  KEY `idx_category` (`category`),
  KEY `idx_is_public` (`is_public`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'system_name', 'OSAS System', 'string', 'general', 'System name displayed in the application', 1, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(2, 'system_email', 'osas@school.edu', 'string', 'general', 'System email address for notifications', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(3, 'system_phone', '+63 912 345 6789', 'string', 'general', 'System contact phone number', 1, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(4, 'system_address', 'School Address', 'string', 'general', 'System physical address', 1, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(5, 'timezone', 'Asia/Manila', 'string', 'general', 'System timezone', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(6, 'date_format', 'Y-m-d', 'string', 'general', 'Date format for display', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(7, 'time_format', 'H:i:s', 'string', 'general', 'Time format for display', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(8, 'items_per_page', '10', 'integer', 'general', 'Number of items per page in tables', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(9, 'enable_notifications', '1', 'boolean', 'notifications', 'Enable system notifications', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(10, 'email_notifications', '1', 'boolean', 'notifications', 'Enable email notifications', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(11, 'sms_notifications', '0', 'boolean', 'notifications', 'Enable SMS notifications', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(12, 'violation_auto_escalate', '1', 'boolean', 'violations', 'Automatically escalate violations after warnings', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(13, 'violation_warning_limit', '3', 'integer', 'violations', 'Number of warnings before disciplinary action', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(14, 'violation_reminder_days', '7', 'integer', 'violations', 'Days before sending violation reminder', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(15, 'report_auto_generate', '0', 'boolean', 'reports', 'Automatically generate reports daily', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(16, 'report_retention_days', '365', 'integer', 'reports', 'Number of days to retain reports', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(17, 'session_timeout', '30', 'integer', 'security', 'Session timeout in minutes', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(18, 'password_min_length', '8', 'integer', 'security', 'Minimum password length', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(19, 'password_require_uppercase', '1', 'boolean', 'security', 'Require uppercase letter in password', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(20, 'password_require_lowercase', '1', 'boolean', 'security', 'Require lowercase letter in password', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(21, 'password_require_number', '1', 'boolean', 'security', 'Require number in password', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(22, 'password_require_special', '0', 'boolean', 'security', 'Require special character in password', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(23, 'login_attempts_limit', '5', 'integer', 'security', 'Maximum login attempts before lockout', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(24, 'lockout_duration', '15', 'integer', 'security', 'Account lockout duration in minutes', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(25, 'enable_2fa', '0', 'boolean', 'security', 'Enable two-factor authentication', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(26, 'maintenance_mode', '0', 'boolean', 'system', 'Enable maintenance mode', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(27, 'maintenance_message', 'System is under maintenance. Please check back later.', 'string', 'system', 'Maintenance mode message', 1, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(28, 'backup_enabled', '1', 'boolean', 'system', 'Enable automatic backups', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(29, 'backup_frequency', 'daily', 'string', 'system', 'Backup frequency (daily, weekly, monthly)', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(30, 'backup_retention', '30', 'integer', 'system', 'Number of backups to retain', 0, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(31, 'theme_default', 'light', 'string', 'appearance', 'Default theme (light, dark, auto)', 1, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(32, 'logo_url', '', 'string', 'appearance', 'System logo URL', 1, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(33, 'favicon_url', '', 'string', 'appearance', 'Favicon URL', 1, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(34, 'primary_color', '#000000', 'string', 'appearance', 'Primary color (gold)', 1, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(35, 'secondary_color', '#E3E3E3', 'string', 'appearance', 'Secondary color', 1, '2026-01-08 11:39:32', '2026-01-09 02:02:39'),
(36, 'last_monthly_reset', '2026-03', 'string', 'system', 'Last month when the violations were archived', 0, '2026-02-15 11:26:48', '2026-03-01 12:19:33');

-- --------------------------------------------------------

--
-- Table structure for table `slip_requests`
--

DROP TABLE IF EXISTS `slip_requests`;
CREATE TABLE IF NOT EXISTS `slip_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `violation_id` int NOT NULL,
  `student_id_code` varchar(50) NOT NULL,
  `requested_by_user_id` int DEFAULT NULL,
  `request_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `approved_by_user_id` int DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `admin_notes` text,
  `processed_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `violation_id` (`violation_id`),
  KEY `requested_by_user_id` (`requested_by_user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `department` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section_id` int DEFAULT NULL,
  `yearlevel` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1st Year',
  `avatar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','graduating','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  UNIQUE KEY `email` (`email`),
  KEY `section_id` (`section_id`),
  KEY `status` (`status`),
  KEY `department` (`department`),
  KEY `idx_students_year_level` (`year_level`)
) ENGINE=InnoDB AUTO_INCREMENT=537 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `gender`, `email`, `contact_number`, `address`, `department`, `section_id`, `yearlevel`, `year_level`, `avatar`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '2025-0760', 'Jerlyn', 'M', 'Aday', 'F', 'jerlyn.aday@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:08', '2026-03-20 15:15:00', NULL),
(2, '2025-0812', 'Althea Nicole Shane', 'M', 'Dudas', 'F', 'altheanicoleshane.dudas@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:08', '2026-03-20 15:15:00', NULL),
(3, '2025-0631', 'Jasmine', 'H', 'Gelena', 'F', 'jasmine.gelena@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:08', '2026-03-20 15:15:00', NULL),
(4, '2025-0714', 'Kyla', 'M', 'Jacob', 'F', 'kyla.jacob@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:08', '2026-03-20 15:15:00', NULL),
(5, '2025-0706', 'Kylyn', 'M', 'Jacob', 'F', 'kylyn.jacob@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:08', '2026-03-20 15:15:00', NULL),
(6, '2025-0607', 'Amaya', 'C', 'Mañibo', 'F', 'amaya.maibo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:08', '2026-03-20 15:15:00', NULL),
(7, '2025-0704', 'Keana', 'G', 'Marquinez', 'F', 'keana.marquinez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:08', '2026-03-20 15:15:00', NULL),
(8, '2025-0792', 'Ashley', 'A', 'Mendoza', 'F', 'ashley.mendoza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(9, '2025-0761', 'Ana Marie', 'A', 'Quimora', 'F', 'anamarie.quimora@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(10, '2025-0707', 'Camille', 'M', 'Tordecilla', 'F', 'camille.tordecilla@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(11, '2025-0630', 'Jonalyn', 'H', 'Untalan', 'F', 'jonalyn.untalan@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(12, '2025-0810', 'Lyra Mae', 'M', 'Villanueva', 'F', 'lyramae.villanueva@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(13, '2025-0608', 'Rhaizza', 'D', 'Villanueva', 'F', 'rhaizza.villanueva@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(14, '2025-0687', 'John Philip Montillana', '', 'Batarlo', 'M', 'johnphilipmontillana.batarlo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(15, '2025-0807', 'Ace Romar', 'B', 'Castillo', 'M', 'aceromar.castillo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(16, '2025-0773', 'John Lloyd', 'B', 'Castillo', 'M', 'johnlloyd.castillo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(17, '2025-0616', 'Jericho', 'M', 'Del Mundo', 'M', 'jericho.delmundo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(18, '2025-0799', 'Khyn', 'C', 'Delos Reyes', 'M', 'khyn.delosreyes@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(19, '2025-0604', 'Gian Dominic Riza', '', 'Dudas', 'M', 'giandominicriza.dudas@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(20, '2025-0703', 'Mark Neil', 'V', 'Fajil', 'M', 'markneil.fajil@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:09', '2026-03-20 15:15:00', NULL),
(21, '2025-0602', 'Mark Angelo Riza', '', 'Francisco', 'M', 'markangeloriza.francisco@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(22, '2025-0363', 'Jhake Perillo', '', 'Garan', 'M', 'jhakeperillo.garan@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(23, '2025-0593', 'Jared', '', 'Gasic', 'M', 'jared.gasic@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(24, '2025-0603', 'Bobby Jr.', 'M', 'Godoy', 'M', 'bobbyjr.godoy@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(25, '2025-0795', 'Edward John', 'S', 'Holgado', 'M', 'edwardjohn.holgado@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(26, '2025-0794', 'Jaypee', 'G', 'Jacob', 'M', 'jaypee.jacob@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(27, '2025-0746', 'Jhon Loyd', 'D', 'Macapuno', 'M', 'jhonloyd.macapuno@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(28, '2025-0672', 'Paul Tristan', 'V', 'Madla', 'M', 'paultristan.madla@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(29, '2025-0594', 'Marlex', 'L', 'Mendoza', 'M', 'marlex.mendoza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(30, '2025-0649', 'Ron-Ron', '', 'Montero', 'M', 'ronron.montero@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(31, '2025-0757', 'Sandy', 'M', 'Laylay', 'F', 'johnlord.moreno@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(32, '2025-0686', 'Johnwin', 'A', 'Pastor', 'M', 'johnwin.pastor@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:10', '2026-03-20 15:15:00', NULL),
(33, '2025-0606', 'Jhon Jake', '', 'Perez', 'M', 'jhonjake.perez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(34, '2025-0692', 'John Kenneth', '', 'Perez', 'M', 'johnkenneth.perez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(35, '2025-0534', 'Khim', 'M', 'Tejada', 'M', 'khim.tejada@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 1, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(36, '2025-0784', 'Mary Ann', 'B', 'Asi', 'F', 'maryann.asi@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(37, '2025-0797', 'Marydith', 'L', 'Atienza', 'F', 'marydith.atienza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(38, '2025-0745', 'Charisma', 'M', 'Banila', 'F', 'charisma.banila@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(39, '2025-0658', 'Myka', 'S', 'Braza', 'F', 'myka.braza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(40, '2025-0676', 'Rhealyne', 'C', 'Cardona', 'F', 'rhealyne.cardona@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(41, '2025-0758', 'Danica Bea', 'T', 'Castillo', 'F', 'danicabea.castillo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(42, '2025-0793', 'Marra Jane', 'V', 'Cleofe', 'F', 'marrajane.cleofe@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(43, '2025-0637', 'Jocelyn', 'T', 'De Guzman', 'F', 'jocelyn.deguzman@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(44, '2025-0790', 'Anna Nicole', '', 'De Leon', 'F', 'annanicole.deleon@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(45, '2025-0778', 'Shane', 'M', 'Dudas', 'F', 'shane.dudas@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:11', '2026-03-20 15:15:00', NULL),
(46, '2025-0754', 'Analyn', 'M', 'Fajardo', 'F', 'analyn.fajardo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(47, '2025-0668', 'Zean Dane', 'A', 'Falcutila', 'F', 'zeandane.falcutila@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(48, '2025-0755', 'Sharmaine', 'G', 'Fonte', 'F', 'sharmaine.fonte@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(49, '2025-0756', 'Crystal', 'E', 'Gagote', 'F', 'crystal.gagote@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(50, '2025-0667', 'Janel', 'M', 'Garcia', 'F', 'janel.garcia@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(51, '2025-0800', 'Aleah', 'G', 'Gida', 'F', 'aleah.gida@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(52, '2025-0786', 'Bhea Jane', 'Y', 'Gillado', 'F', 'bheajane.gillado@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(53, '2025-0805', 'Mae', 'M', 'Hernandez', 'F', 'mae.hernandez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(54, '2025-0656', 'Arian Bello', '', 'Maculit', 'F', 'arianbello.maculit@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(55, '2025-0771', 'Mikee', 'M', 'Manay', 'F', 'mikee.manay@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(56, '2025-0763', 'Lorain B', '', 'Medina', 'F', 'lorainb.medina@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(57, '2025-0767', 'Lovely Joy', 'A', 'Mercado', 'F', 'lovelyjoy.mercado@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:12', '2026-03-20 15:15:00', NULL),
(58, '2025-0772', 'Romelyn', 'M', 'Mongcog', 'F', 'romelyn.mongcog@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(59, '2025-0699', 'Lleyn Angela', 'J', 'Olympia', 'F', 'lleynangela.olympia@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(60, '2025-0766', 'Althea', 'A', 'Paala', 'F', 'althea.paala@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(61, '2025-0770', 'Ivy Kristine', 'A', 'Petilo', 'F', 'ivykristine.petilo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(62, '2025-0789', 'Irish Catherine', 'M', 'Ramos', 'F', 'irishcatherine.ramos@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(63, '2025-0796', 'Rubilyn', 'V', 'Roxas', 'F', 'rubilyn.roxas@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(64, '2025-0718', 'Marie Bernadette', 'S', 'Tolentino', 'F', 'mariebernadette.tolentino@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(65, '2025-0643', 'Wyncel', 'A', 'Tolentino', 'F', 'wyncel.tolentino@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(66, '2025-0629', 'Felicity', 'O', 'Villegas', 'F', 'felicity.villegas@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(67, '2025-0705', 'Danilo R. Jr', '', 'Cabiles', 'M', 'danilorjr.cabiles@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(68, '2025-0726', 'Aldrin', 'L', 'Carable', 'M', 'aldrin.carable@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(69, '2025-0743', 'Daniel', 'A', 'Franco', 'M', 'daniel.franco@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:13', '2026-03-20 15:15:00', NULL),
(70, '2025-0636', 'Jarred', 'L', 'Gomez', 'M', 'jarred.gomez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(71, '2025-0785', 'James Andrei', 'D', 'Fajardo', 'M', 'jairus.macuha@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:01', NULL),
(72, '2025-0801', 'Mel Gabriel', 'N', 'Magat', 'M', 'melgabriel.magat@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(73, '2025-0762', 'Erwin', 'M', 'Tejedor', 'M', 'erwin.tejedor@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 2, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(74, '2025-0747', 'Jaydie A', '', 'Fabiano', 'F', 'brixmatthew.velasco@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:01', NULL),
(75, '2025-0617', 'K-Ann', 'E', 'Abela', 'F', 'kann.abela@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(76, '2025-0733', 'Shane Ashley', 'C', 'Abendan', 'F', 'shaneashley.abendan@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(77, '2025-0619', 'Hanna', 'N', 'Aborde', 'F', 'hanna.aborde@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(78, '2025-0765', 'Rysa Mae', 'G', 'Alfante', 'F', 'rysamae.alfante@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(79, '2025-0809', 'Jeny', 'M', 'Amado', 'F', 'jeny.amado@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(80, '2025-0680', 'Jonah Trisha', 'D', 'Asi', 'F', 'jonahtrisha.asi@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(81, '2025-0646', 'Jhovelyn', 'G', 'Bacay', 'F', 'jhovelyn.bacay@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(82, '2025-0679', 'Alexa Jane', '', 'Bon', 'F', 'alexajane.bon@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:14', '2026-03-20 15:15:00', NULL),
(83, '2025-0783', 'Lorraine', 'D', 'Bonado', 'F', 'lorraine.bonado@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(84, '2025-0638', 'Shiella Mae', 'A', 'Bonifacio', 'F', 'shiellamae.bonifacio@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(85, '2025-0711', 'Claren', 'I', 'Carable', 'F', 'claren.carable@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(86, '2025-0727', 'Prences Angel', 'L', 'Consigo', 'F', 'prencesangel.consigo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(87, '2025-0742', 'Jamhyca', 'C', 'De Chavez', 'F', 'jamhyca.dechavez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(88, '2025-0673', 'Nicole', 'P', 'Defeo', 'F', 'nicole.defeo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(89, '2025-0722', 'Sophia Angela', 'M', 'Delos Reyes', 'F', 'sophiaangela.delosreyes@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(90, '2025-0612', 'Romelyn', '', 'Elida', 'F', 'romelyn.elida@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(91, '2025-0611', 'Christina Sofia Lie', 'D', 'Enriquez', 'F', 'christinasofialie.enriquez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(92, '2025-0688', 'Elayca Mae', 'E', 'Fajardo', 'F', 'elaycamae.fajardo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(93, '2025-0657', 'Ailla', 'F', 'Fajura', 'F', 'ailla.fajura@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(94, '2025-0618', 'Judith', 'B', 'Fallarna', 'F', 'judith.fallarna@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(95, '2025-0654', 'Jenelyn', 'R', 'Fonte', 'F', 'jenelyn.fonte@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:15', '2026-03-20 15:15:00', NULL),
(96, '2025-0713', 'Katrice', 'I', 'Garcia', 'F', 'katrice.garcia@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(97, '2025-0737', 'Shalemar', 'M', 'Geroleo', 'F', 'shalemar.geroleo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(98, '2025-0655', 'Edlyn', 'M', 'Hernandez', 'F', 'edlyn.hernandez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(99, '2025-0633', 'Angela', 'T', 'Lotho', 'F', 'angela.lotho@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(100, '2025-0808', 'Remz Ann Escarlet', 'G', 'Macapuno', 'F', 'remzannescarlet.macapuno@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(101, '2025-0609', 'Leslie', 'B', 'Melgar', 'F', 'leslie.melgar@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(102, '2025-0729', 'Camille', 'B', 'Milambiling', 'F', 'camille.milambiling@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(103, '2025-0710', 'Erica Mae', 'B', 'Motol', 'F', 'ericamae.motol@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(104, '2025-0728', 'Ma. Teresa', 'S', 'Obando', 'F', 'materesa.obando@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(105, '2025-0647', 'Argel', 'B', 'Ocampo', 'F', 'argel.ocampo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(106, '2025-0779', 'Jea Francine', '', 'Rivera', 'F', 'jeafrancine.rivera@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(107, '2025-0788', 'Ashly Nicole', '', 'Rana', 'F', 'ashlynicole.rana@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:16', '2026-03-20 15:15:00', NULL),
(108, '2025-0741', 'Aimie Jane', 'M', 'Reyes', 'F', 'aimiejane.reyes@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:00', NULL),
(109, '2025-0734', 'Rhenelyn', 'A', 'Sandoval', 'F', 'rhenelyn.sandoval@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:00', NULL),
(110, '2025-0777', 'Nicole', 'S', 'Silva', 'F', 'nicole.silva@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:00', NULL),
(111, '2025-0731', 'Jeane', 'T', 'Sulit', 'F', 'jeane.sulit@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:00', NULL),
(112, '2025-0723', 'Pauleen', 'H', 'Villaruel', 'F', 'pauleen.villaruel@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:00', NULL),
(113, '2025-0806', 'Megan Michaela', 'M', 'Visaya', 'F', 'meganmichaela.visaya@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:01', NULL),
(114, '2025-0684', 'Rodel', '', 'Arenas', 'M', 'rodel.arenas@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:01', NULL),
(115, '2025-0690', 'Rexner', 'M', 'Eguillon', 'M', 'rexner.eguillon@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:01', NULL),
(116, '2025-0815', 'Aldrin', 'J', 'Bueno', 'M', 'reymart.elmido@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:01', NULL),
(117, '2025-0627', 'Kervin', 'B', 'Garachico', 'M', 'kervin.garachico@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:17', '2026-03-20 15:15:01', NULL),
(118, '2025-0865', 'Zyris', 'A', 'Guavez', 'M', 'zyris.guavez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(119, '2025-0740', 'Marjun A', '', 'Linayao', 'M', 'marjuna.linayao@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(120, '2025-0660', 'John Lloyd', '', 'Macapuno', 'M', 'johnlloyd.macapuno@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(121, '2025-0732', 'Helbert', 'F', 'Maulion', 'M', 'helbert.maulion@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(122, '2025-0645', 'Dindo', 'S', 'Tolentino', 'M', 'dindo.tolentino@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 3, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(123, '2025-0621', 'Novelyn', 'D', 'Albufera', 'F', 'novelyn.albufera@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(124, '2025-0775', 'Angela', 'F', 'Aldea', 'F', 'angela.aldea@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(125, '2025-0601', 'Maria Fe', 'C', 'Aldovino', 'F', 'mariafe.aldovino@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(126, '2025-0661', 'Aizel', 'M', 'Alvarez', 'F', 'aizel.alvarez@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(127, '2025-0752', 'Sherilyn', 'T', 'Anyayahan', 'F', 'sherilyn.anyayahan@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(128, '2025-0623', 'Mika Dean', '', 'Buadilla', 'F', 'mikadean.buadilla@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(129, '2025-0669', 'Daniela Faye', '', 'Cabiles', 'F', 'danielafaye.cabiles@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:18', '2026-03-20 15:15:01', NULL),
(130, '2025-0599', 'Prinses Gabriela', 'Q', 'Calaolao', 'F', 'prinsesgabriela.calaolao@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(131, '2025-0719', 'Deah Angella S', '', 'Carpo', 'F', 'deahangellas.carpo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(132, '2025-0802', 'Jedidiah', 'C', 'Gelena', 'F', 'jedidiah.gelena@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(133, '2025-0664', 'Aleyah Janelle', 'B', 'Jara', 'F', 'aleyahjanelle.jara@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(134, '2025-0720', 'Charese', 'M', 'Jolo', 'F', 'charese.jolo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(135, '2025-0682', 'Janice', 'G', 'Lugatic', 'F', 'janice.lugatic@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(136, '2025-0739', 'Abegail', '', 'Malogueño', 'F', 'abegail.malogueo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(137, '2025-0708', 'Ericca', 'A', 'Marquez', 'F', 'ericca.marquez@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(138, '2025-0748', 'Arien', 'M', 'Montesa', 'F', 'arien.montesa@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(139, '2025-0653', 'Jasmine', 'Q', 'Nuestro', 'F', 'jasmine.nuestro@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(140, '2025-0738', 'Nicole', 'G', 'Ola', 'F', 'nicole.ola@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:19', '2026-03-20 15:15:01', NULL),
(141, '2025-0628', 'Alyssa Mae', 'M', 'Quintia', 'F', 'alyssamae.quintia@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(142, '2025-0774', 'Jona Marie', 'G', 'Romero', 'F', 'jonamarie.romero@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(143, '2025-0634', 'Marbhel', 'H', 'Rucio', 'F', 'marbhel.rucio@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(144, '2025-0814', 'Lovely', 'K', 'Torres', 'F', 'lovely.torres@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(145, '2025-0620', 'Rexon', 'E', 'Abanilla', 'M', 'rexon.abanilla@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(146, '2025-0791', 'Ramfel', 'H', 'Azucena', 'M', 'ramfel.azucena@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(147, '2025-0632', 'Jeverson', 'M', 'Bersoto', 'M', 'jeverson.bersoto@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(148, '2025-0626', 'Shervin Jeral', 'M', 'Castro', 'M', 'shervinjeral.castro@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(149, '2025-0652', 'Daniel', 'D', 'De Ade', 'M', 'daniel.deade@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(150, '2025-0782', 'Dave Ruzzele', 'D', 'Despa', 'M', 'daveruzzele.despa@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(151, '2025-0696', 'Alexander', 'R', 'Ducado', 'M', 'alexander.ducado@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(152, '2025-0595', 'Uranus', 'R', 'Evangelista', 'M', 'uranus.evangelista@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(153, '2025-0697', 'Joshua', 'M', 'Gabon', 'M', 'joshua.gabon@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:20', '2026-03-20 15:15:01', NULL),
(154, '2025-0681', 'John Andrew', 'R', 'Gavilan', 'M', 'johnandrew.gavilan@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(155, '2025-0715', 'Mc Lenard', 'A', 'Gibo', 'M', 'mclenard.gibo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(156, '2025-0716', 'Dan Kian', 'A', 'Hatulan', 'M', 'dankian.hatulan@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(157, '2025-0803', 'Benjamin Jr. D', '', 'Hernandez', 'M', 'benjaminjrd.hernandez@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(158, '2025-0753', 'Renz', 'F', 'Hernandez', 'M', 'renz.hernandez@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(159, '2025-0662', 'Ralph Adriane', 'D', 'Javier', 'M', 'ralphadriane.javier@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(160, '2025-0598', 'Andrew', 'M', 'Laredo', 'M', 'andrew.laredo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(161, '2025-0663', 'Janryx', 'S', 'Las Pinas', 'M', 'janryx.laspinas@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(162, '2025-0735', 'Bricks', 'M', 'Lindero', 'M', 'bricks.lindero@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(163, '2025-0639', 'Luigi', 'B', 'Lomio', 'M', 'luigi.lomio@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(164, '2025-0596', 'John Lemuel', 'O', 'Macalindol', 'M', 'johnlemuel.macalindol@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(165, '2025-0781', 'Jandy', 'S', 'Macapuno', 'M', 'jandy.macapuno@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:21', '2026-03-20 15:15:01', NULL),
(166, '2025-0693', 'Cedrick', 'M', 'Mandia', 'M', 'cedrick.mandia@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(167, '2025-0650', 'Eric John', 'C', 'Marinduque', 'M', 'ericjohn.marinduque@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(168, '2025-0730', 'Jimrex', 'M', 'Mayano', 'M', 'jimrex.mayano@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(169, '2025-0624', 'Hedyen', 'C', 'Mendoza', 'M', 'hedyen.mendoza@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(170, '2025-0625', 'Mark Angelo', 'E', 'Montevirgen', 'M', 'markangelo.montevirgen@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(171, '2025-0651', 'JM', 'B', 'Nas', 'M', 'jm.nas@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(172, '2025-0725', 'Vhon Jerick O', '', 'Ornos', 'M', 'vhonjericko.ornos@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(173, '2025-0659', 'Carl Justine', 'D', 'Padua', 'M', 'carljustine.padua@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(174, '2025-0600', 'Patrick Lanz', '', 'Paz', 'M', 'patricklanz.paz@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(175, '2025-0622', 'Mark Justin', 'C', 'Pecolados', 'M', 'markjustin.pecolados@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(176, '2025-0764', 'Tristan Jay', 'M', 'Plata', 'M', 'tristanjay.plata@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(177, '2025-0776', 'Jude Michael', '', 'Somera', 'M', 'judemichael.somera@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(178, '2025-0695', 'Philip Jhon', 'N', 'Tabor', 'M', 'philipjhon.tabor@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:22', '2026-03-20 15:15:01', NULL),
(179, '2025-0597', 'Ivan Lester', 'D', 'Ylagan', 'M', 'ivanlester.ylagan@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 4, '1', '1st Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(180, '2024-0513', 'Kiana Jane', 'P', 'Añonuevo', 'F', 'kianajane.aonuevo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(181, '2024-0514', 'Kyla', '', 'Anonuevo', 'F', 'kyla.anonuevo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(182, '2024-0569', 'Katrice', 'F', 'Antipasado', 'F', 'katrice.antipasado@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(183, '2024-0591', 'Regine', '', 'Antipasado', 'F', 'regine.antipasado@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(184, '2024-0550', 'Juneth', 'H', 'Baliday', 'F', 'juneth.baliday@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(185, '2024-0546', 'Gielysa', 'C', 'Concha', 'F', 'gielysa.concha@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(186, '2024-0506', 'Maecelle', 'V', 'Fiedalan', 'F', 'maecelle.fiedalan@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(187, '2024-0508', 'Lara Mae', 'E', 'Garcia', 'F', 'laramae.garcia@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(188, '2024-0459', 'Jade', 'S', 'Garing', 'F', 'jade.garing@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(189, '2024-0446', 'Rica', 'D', 'Glodo', 'F', 'rica.glodo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(190, '2024-0549', 'Danica Mae', 'N', 'Hornilla', 'F', 'danicamae.hornilla@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:23', '2026-03-20 15:15:01', NULL),
(191, '2024-0473', 'Jenny', 'F', 'Idea', 'F', 'jenny.idea@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(192, '2024-0487', 'Roma', 'L', 'Mendoza', 'F', 'roma.mendoza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(193, '2024-0535', 'Evangeline', 'V', 'Mojica', 'F', 'evangeline.mojica@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(194, '2024-0570', 'Carla', 'G', 'Nineria', 'F', 'carla.nineria@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(195, '2024-0516', 'Kyla', 'G', 'Oliveria', 'F', 'kyla.oliveria@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(196, '2024-0457', 'Mikayla', 'M', 'Paala', 'F', 'mikayla.paala@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(197, '2024-0442', 'Necilyn', 'B', 'Ramos', 'F', 'necilyn.ramos@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(198, '2024-0469', 'Mischell', 'U', 'Velasquez', 'F', 'mischell.velasquez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(199, '2024-0539', 'Emerson', 'M', 'Adarlo', 'M', 'emerson.adarlo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(200, '2024-0491', 'Shim Andrian', 'L', 'Adarlo', 'M', 'shimandrian.adarlo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(201, '2024-0485', 'Cedrick', 'C', 'Cardova', 'M', 'cedrick.cardova@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(202, '2024-0477', 'John Paul', 'M', 'De Lemos', 'M', 'johnpaul.delemos@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:24', '2026-03-20 15:15:01', NULL),
(203, '2024-0489', 'Reymar', 'G', 'Faeldonia', 'M', 'reymar.faeldonia@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(204, '2024-0500', 'John Ray', 'A', 'Fegidero', 'M', 'johnray.fegidero@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(205, '2024-0488', 'John Lester', 'C', 'Gaba', 'M', 'johnlester.gaba@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(206, '2024-0475', 'Antonio Gabriel', 'A', 'Francisco', 'M', 'antoniogabriel.francisco@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(207, '2024-0345', 'karl Andrew', 'R', 'Hardin', 'M', 'karlandrew.hardin@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(208, '2024-0499', 'Prince', 'L', 'Geneta', 'M', 'prince.geneta@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(209, '2024-0495', 'John Reign', 'A', 'Laredo', 'M', 'johnreign.laredo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(210, '2024-0490', 'Mc Ryan', '', 'Masangkay', 'M', 'mcryan.masangkay@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(211, '2025-0592', 'Aaron Vincent', 'R', 'Manalo', 'M', 'aaronvincent.manalo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(212, '2024-0494', 'Great', 'B', 'Mendoza', 'M', 'great.mendoza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(213, '2024-0497', 'Jhon Marc', 'D', 'Oliveria', 'M', 'jhonmarc.oliveria@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(214, '2024-0455', 'Kevin', 'G', 'Rucio', 'M', 'kevin.rucio@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 5, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:25', '2026-03-20 15:15:01', NULL),
(215, '2024-0445', 'Arhizza Sheena', 'R', 'Abanilla', 'F', 'arhizzasheena.abanilla@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(216, '2024-0503', 'Angelica', 'M', 'Cabello', 'F', 'carlaandrea.azucena@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(217, '2024-0548', 'Angel Ann', 'D', 'Fajardo', 'F', 'angel.cason@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(218, '2024-0461', 'KC May', 'A', 'De Guzman', 'F', 'kcmay.deguzman@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(219, '2024-0531', 'Francene', '', 'Delos Santos', 'F', 'francene.delossantos@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(220, '2024-0470', 'Shane Ayessa', 'L', 'Elio', 'F', 'shaneayessa.elio@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(221, '2024-0502', 'Maria Angela', 'B', 'Garcia', 'F', 'mariaangela.garcia@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(222, '2024-0466', 'Shane Mary', 'C', 'Gardoce', 'F', 'shanemary.gardoce@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(223, '2024-0441', 'Janah', 'M', 'Glor', 'F', 'janah.glor@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(224, '2024-0476', 'Catherine', 'R', 'Gomez', 'F', 'catherine.gomez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(225, '2024-0554', 'April Joy', '', 'Llamoso', 'F', 'apriljoy.llamoso@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(226, '2024-0440', 'Irene', 'Y', 'Loto', 'F', 'irene.loto@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:26', '2026-03-20 15:15:01', NULL),
(227, '2024-0463', 'Angela', 'M', 'Lumanglas', 'F', 'angela.lumanglas@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(228, '2024-0464', 'Michelle Micah', 'M', 'Lumanglas', 'F', 'michellemicah.lumanglas@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(229, '2024-0545', 'Febelyn', 'M', 'Magboo', 'F', 'febelyn.magboo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(230, '2024-0458', 'Chelo Rose', 'P', 'Marasigan', 'F', 'chelorose.marasigan@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(231, '2024-0456', 'Joana Marie', 'L', 'Paala', 'F', 'joanamarie.paala@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(232, '2024-0538', 'Maria Irene', 'T', 'Pasado', 'F', 'mariairene.pasado@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(233, '2024-0563', 'Danica', '', 'Pederio', 'F', 'danica.pederio@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(234, '2024-0444', 'Angela Clariss', 'P', 'Teves', 'F', 'angelaclariss.teves@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(235, '2024-0454', 'Zairene', 'R', 'Undaloc', 'F', 'zairene.undaloc@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(236, '2024-0449', 'John Ivan', 'P', 'Cuasay', 'M', 'johnivan.cuasay@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(237, '2024-0505', 'Bert', 'B', 'Ferrera', 'M', 'bert.ferrera@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(238, '2024-0450', 'Rickson', 'C', 'Ferry', 'M', 'rickson.ferry@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(239, '2024-0555', 'John Mariol', 'L', 'Fransisco', 'M', 'johnmariol.fransisco@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:27', '2026-03-20 15:15:01', NULL),
(240, '2024-0530', 'Allan', 'Y', 'Loto', 'M', 'allan.loto@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(241, '2024-0401', 'Jhon Kenneth', 'S', 'Obando', 'M', 'jhonkenneth.obando@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(242, '2024-0462', 'Rodel', 'T', 'Roldan', 'M', 'rodel.roldan@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 6, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(243, '2024-0358', 'Ashlyn Kieth', 'V', 'Abanilla', 'F', 'ashlynkieth.abanilla@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(244, '2024-0352', 'Patricia Mae', 'M', 'Agoncillo', 'F', 'patriciamae.agoncillo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(245, '2024-0378', 'Benelyn', 'D', 'Aguho', 'F', 'benelyn.aguho@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(246, '2024-0504', 'Lynse', 'C', 'Albufera', 'F', 'lynse.albufera@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL);
INSERT INTO `students` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `gender`, `email`, `contact_number`, `address`, `department`, `section_id`, `yearlevel`, `year_level`, `avatar`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(247, '2024-0521', 'Lara Mae', 'M', 'Altamia', 'F', 'laramae.altamia@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(248, '2024-0379', 'Crislyn', 'M', 'Anyayahan', 'F', 'crislyn.anyayahan@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(249, '2024-0360', 'Rocel Liegh', 'L', 'Arañez', 'F', 'rocelliegh.araez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(250, '2024-0372', 'Katrice Allaine', 'A', 'Atienza', 'F', 'katriceallaine.atienza@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(251, '2024-0354', 'Maica', 'C', 'Bacal', 'F', 'maica.bacal@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:28', '2026-03-20 15:15:01', NULL),
(252, '2024-0347', 'Cherylyn', 'C', 'Bacsa', 'F', 'cherylyn.bacsa@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(253, '2024-0364', 'Realyn', 'M', 'Bercasi', 'F', 'realyn.bercasi@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(254, '2024-0355', 'Elyza', 'M', 'Buquis', 'F', 'elyza.buquis@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(255, '2024-0474', 'Kim Ashley Nicole', 'M', 'Caringal', 'F', 'kimashleynicole.caringal@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(256, '2024-0351', 'Shane', 'B', 'Dalisay', 'F', 'shane.dalisay@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(257, '2024-0369', 'Mariel', 'V', 'Delos Santos', 'F', 'mariel.delossantos@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(258, '2024-0520', 'Angel', 'G', 'Dimoampo', 'F', 'angel.dimoampo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(259, '2024-0374', 'Kristine', 'B', 'Dris', 'F', 'kristine.dris@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(260, '2024-0367', 'Rexlyn Joy', 'M', 'Eguillon', 'F', 'rexlynjoy.eguillon@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(261, '2024-0363', 'Maricar', 'A', 'Evangelista', 'F', 'maricar.evangelista@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(262, '2024-0388', 'Chariz', 'M', 'Fajardo', 'F', 'chariz.fajardo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:29', '2026-03-20 15:15:01', NULL),
(263, '2024-0366', 'Hazel Ann', 'B', 'Feudo', 'F', 'hazelann.feudo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(264, '2024-0385', 'Marie Joy', 'C', 'Gado', 'F', 'mariejoy.gado@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(265, '2024-0371', 'Leah', 'M', 'Galit', 'F', 'leah.galit@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(266, '2024-0507', 'Aiexa Danielle', 'A', 'Guira', 'F', 'aiexadanielle.guira@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(267, '2024-0375', 'Andrea Mae', 'M', 'Hernandez', 'F', 'andreamae.hernandez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(268, '2024-0501', 'Eslley Ann', 'T', 'Hernandez', 'F', 'eslleyann.hernandez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(269, '2024-0376', 'Jazleen', '', 'Llamoso', 'F', 'jazleen.llamoso@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(270, '2024-0368', 'Joan Kate', 'G', 'Lomio', 'F', 'joankate.lomio@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(271, '2024-0391', 'Kriselle Ann', 'T', 'Mabuti', 'F', 'kriselleann.mabuti@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(272, '2024-0387', 'Angel Rose', 'S', 'Mascarinas', 'F', 'angelrose.mascarinas@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(273, '2024-0587', 'Hannah', 'A', 'Melgar', 'F', 'hannah.melgar@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(274, '2024-0586', 'Rexy Mae', 'D', 'Mingo', 'F', 'rexymae.mingo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:30', '2026-03-20 15:15:01', NULL),
(275, '2024-0349', 'Precious Nicole', 'N', 'Moya', 'F', 'preciousnicole.moya@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(276, '2024-0377', 'Cherese Gelyn', 'C', 'Nao', 'F', 'cheresegelyn.nao@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(277, '2024-0384', 'Margie', 'N', 'Nuñez', 'F', 'margie.nuez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(278, '2024-0350', 'Hazel Ann', 'F', 'Panganiban', 'F', 'hazelann.panganiban@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(279, '2024-0568', 'Angela', '', 'Papasin', 'F', 'angela.papasin@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(280, '2024-0359', 'Jasmine', 'A', 'Prangue', 'F', 'jasmine.prangue@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(281, '2024-0380', 'Jeyzelle', 'G', 'Rellora', 'F', 'jeyzelle.rellora@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(282, '2024-0264', 'Katrina T', '', 'Rufino', 'F', 'katrinat.rufino@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(283, '2024-0382', 'Niña Zyrene', 'R', 'Sanchez', 'F', 'niazyrene.sanchez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(284, '2024-0509', 'Edcel Jane', 'B', 'Santillan', 'F', 'edceljane.santillan@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(285, '2024-0451', 'Mary Joy', 'M', 'Sara', 'F', 'maryjoy.sara@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(286, '2024-0453', 'Cynthia', '', 'Torres', 'F', 'cynthia.torres@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:31', '2026-03-20 15:15:01', NULL),
(287, '2024-0556', 'Jolie', 'L', 'Tugmin', 'F', 'jolie.tugmin@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(288, '2024-0356', 'Lesley Ann', 'M', 'Villanueva', 'F', 'lesleyann.villanueva@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(289, '2024-0365', 'Lany', 'G', 'Ylagan', 'F', 'lany.ylagan@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(290, '2024-0373', 'Marvin', 'M', 'Caraig', 'M', 'marvin.caraig@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(291, '2024-0557', 'Denniel', 'C', 'Delos Santos', 'M', 'denniel.delossantos@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(292, '2024-0389', 'Alex', 'T', 'Magsisi', 'M', 'alex.magsisi@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(293, '2024-0525', 'Jan Carlo', 'G', 'Manalo', 'M', 'jancarlo.manalo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(294, '2024-0386', 'AJ', 'M', 'Masangkay', 'M', 'aj.masangkay@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(295, '2024-0480', 'John Paul', 'M', 'Roldan', 'M', 'johnpaul.roldan@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(296, '2024-0523', 'Ronald', '', 'Tañada', 'M', 'ronald.taada@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(297, '2024-0492', 'D-Jay', 'G', 'Teriompo', 'M', 'djay.teriompo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 7, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(298, '2025-0816', 'Marsha Lhee', 'G', 'Azucena', 'F', 'marshalhee.azucena@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:32', '2026-03-20 15:15:01', NULL),
(299, '2024-0438', 'Melsan', 'G', 'Aday', 'F', 'melsan.aday@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:33', '2026-03-20 15:15:01', NULL),
(300, '2024-0405', 'Jonice', 'P', 'Alturas', 'F', 'jonice.alturas@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:33', '2026-03-20 15:15:01', NULL),
(301, '2024-0411', 'Precious', 'S', 'Apil', 'F', 'precious.apil@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:33', '2026-03-20 15:15:01', NULL),
(302, '2024-0418', 'Ludelyn', 'T', 'Belbes', 'F', 'ludelyn.belbes@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:33', '2026-03-20 15:15:01', NULL),
(303, '2024-0424', 'Princess Hazel', 'D', 'Cabasi', 'F', 'princesshazel.cabasi@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:33', '2026-03-20 15:15:01', NULL),
(304, '2024-0342', 'Charlaine', 'M', 'De Belen', 'F', 'charlaine.debelen@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:33', '2026-03-20 15:15:01', NULL),
(305, '2024-0437', 'Arjean Joy', 'S', 'De Castro', 'F', 'arjeanjoy.decastro@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:33', '2026-03-20 15:15:01', NULL),
(306, '2024-0343', 'Precious Cindy', 'G', 'De Guzman', 'F', 'preciouscindy.deguzman@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:33', '2026-03-20 15:15:01', NULL),
(307, '2024-0404', 'Marina', 'M', 'De Luzon', 'F', 'marina.deluzon@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:33', '2026-03-20 15:15:01', NULL),
(308, '2024-0417', 'Nesvita', 'V', 'Dorias', 'F', 'nesvita.dorias@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(309, '2024-0432', 'Stella Rey', 'A', 'Flores', 'F', 'stellarey.flores@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(310, '2024-0567', 'Arlene', 'S', 'Gaba', 'F', 'arlene.gaba@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(311, '2024-0422', 'Jay-Ann', 'G', 'Jamilla', 'F', 'jayann.jamilla@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(312, '2024-0416', 'Mikaela Joy', 'M', 'Layson', 'F', 'mikaelajoy.layson@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(313, '2024-0427', 'Christine Joy', 'A', 'Lomio', 'F', 'christinejoy.lomio@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(314, '2024-0544', 'Ariane', 'M', 'Magboo', 'F', 'ariane.magboo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(315, '2024-0415', 'Nerissa', 'R', 'Magsisi', 'F', 'nerissa.magsisi@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(316, '2024-0472', 'Keycel Joy', 'M', 'Manalo', 'F', 'keyceljoy.manalo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(317, '2024-0412', 'Grace Cell', 'G', 'Manibo', 'F', 'gracecell.manibo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(318, '2024-0571', 'Lovelyn', 'A', 'Marcos', 'F', 'lovelyn.marcos@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:34', '2026-03-20 15:15:01', NULL),
(319, '2024-0314', 'Shenna Marie', 'P', 'Obando', 'F', 'shennamarie.obando@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(320, '2024-0348', 'Myzell', 'U', 'Ramos', 'F', 'myzell.ramos@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(321, '2024-0582', 'Shella Mae', 'T', 'Ramos', 'F', 'shellamae.ramos@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(322, '2024-0426', 'Desiree', 'G', 'Raymundo', 'F', 'desiree.raymundo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(323, '2023-0433', 'Romelyn', 'A', 'Rocha', 'F', 'romelyn.rocha@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(324, '2023-0519', 'John Michael', '', 'Bacsa', 'M', 'johnmichael.bacsa@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(325, '2024-0043', 'John Kenneth Joseph', 'G', 'Balansag', 'M', 'johnkennethjoseph.balansag@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(326, '2024-0398', 'Raphael', 'M', 'Bugayong', 'M', 'raphael.bugayong@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(327, '2024-0572', 'Mark Jayson', 'D', 'Bunag', 'M', 'markjayson.bunag@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(328, '2024-0561', 'Alvin', 'M', 'Corona', 'M', 'alvin.corona@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(329, '2023-0407', 'Joseph', 'E', 'Elio', 'M', 'markjanssen.cueto@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(330, '2023-0447', 'Charles Darwin', 'S', 'Dimailig', 'M', 'charlesdarwin.dimailig@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:35', '2026-03-20 15:15:01', NULL),
(331, '2024-0413', 'Airon', 'R', 'Evangelista', 'M', 'airon.evangelista@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(332, '2024-0517', 'Gino', 'L', 'Genabe', 'M', 'gino.genabe@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(333, '2024-0420', 'Miklo', 'M', 'Lumanglas', 'M', 'miklo.lumanglas@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(334, '2023-0151', 'Ramcil', 'M', 'Macapuno', 'M', 'ramcil.macapuno@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(335, '2024-0395', 'Florence', 'R', 'Macalelong', 'M', 'florence.macalelong@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(336, '2023-0465', 'Patrick', 'T', 'Matanguihan', 'M', 'patrick.matanguihan@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(337, '2024-0478', 'Dranzel', 'L', 'Miranda', 'M', 'dranzel.miranda@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(338, '2024-0394', 'Carlo', 'G', 'Mondragon', 'M', 'carlo.mondragon@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(339, '2024-0410', 'John Rexcel', 'E', 'Montianto', 'M', 'johnrexcel.montianto@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(340, '2024-0428', 'Christian', 'M', 'Moreno', 'M', 'christian.moreno@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(341, '2024-0393', 'Amiel Geronne', 'M', 'Pantua', 'M', 'amielgeronne.pantua@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:36', '2026-03-20 15:15:01', NULL),
(342, '2024-0392', 'James Lorence', 'C', 'Paradijas', 'M', 'jameslorence.paradijas@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(343, '2024-0436', 'Jhezreel', 'P', 'Pastorfide', 'M', 'jhezreel.pastorfide@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(344, '2024-0578', 'Matt Raphael', 'G', 'Reyes', 'M', 'mattraphael.reyes@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(345, '2024-0580', 'Merwin', 'D', 'Santos', 'M', 'merwin.santos@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(346, '2024-0423', 'Benjamin Jr.', 'D', 'Sarvida', 'M', 'benjaminjr.sarvida@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(347, '2024-0408', 'Jerus', 'B', 'Savariz', 'M', 'jerus.savariz@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(348, '2024-0406', 'Gerson', 'C', 'Urdanza', 'M', 'gerson.urdanza@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(349, '2024-0397', 'Jyrus', 'M', 'Ylagan', 'M', 'jyrus.ylagan@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 8, '2', '2nd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(350, '2023-0304', 'Jonah Rhyza', 'N', 'Anyayahan', 'F', 'jonahrhyza.anyayahan@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(351, '2023-0337', 'Leica', 'M', 'Banila', 'F', 'leica.banila@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(352, '2023-0327', 'Juvylyn', 'G', 'Basa', 'F', 'juvylyn.basa@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(353, '2022-0088', 'Rashele', 'M', 'Delgaco', '', 'rashele.delgaco@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:37', '2026-03-20 15:15:01', NULL),
(354, '2023-0288', 'Cristal Jean', 'D', 'De Chusa', 'F', 'cristaljean.dechusa@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(355, '2023-0305', 'Jaime Elizabeth', 'L', 'Evora', 'F', 'jaimeelizabeth.evora@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(356, '2023-0317', 'Jeanlyn', 'B', 'Garcia', 'F', 'jeanlyn.garcia@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(357, '2023-0161', 'Baby Anh Marie', 'M', 'Godoy', 'F', 'babyanhmarie.godoy@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(358, '2023-0169', 'Herjane', 'F', 'Gozar', 'F', 'herjane.gozar@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(359, '2023-0200', 'Zyra', 'M', 'Gutierrez', 'F', 'zyra.gutierrez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(360, '2023-0251', 'Angielene', 'C', 'Landicho', 'F', 'angielene.landicho@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(361, '2023-0298', 'Laila', 'A', 'Limun', 'F', 'laila.limun@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(362, '2023-0244', 'Jennie Vee', 'P', 'Lopez', 'F', 'jennievee.lopez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(363, '2023-0215', 'Judy Ann', 'M', 'Madrigal', 'F', 'judyann.madrigal@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(364, '2023-0285', 'Maan', 'M', 'Masangkay', 'F', 'maan.masangkay@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(365, '2023-0225', 'Genesis Mae', 'M', 'Mendoza', 'F', 'genesismae.mendoza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:38', '2026-03-20 15:15:01', NULL),
(366, '2023-0224', 'Marian', 'L', 'Mendoza', 'F', 'marian.mendoza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(367, '2023-0173', 'Lailin', 'S', 'Obando', 'F', 'lailin.obando@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(368, '2023-0303', 'Kyla', 'G', 'Rucio', 'F', 'kyla.rucio@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(369, '2023-0241', 'Anthony', 'L', 'Sto. Niño', 'M', 'lyn.velasquez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(370, '2023-0336', 'Jhon Jerald', 'P', 'Acojedo', 'M', 'jhonjerald.acojedo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(371, '2023-0345', 'Sherwin', 'T', 'Calibot', 'M', 'sherwin.calibot@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(372, '2023-0233', 'Joriz Cezar', 'M', 'Collado', 'M', 'jorizcezar.collado@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(373, '2023-1080', 'Mark Lee', 'C', 'Dalay', 'M', 'marklee.dalay@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(374, '2023-0239', 'Adrian', 'C', 'Dilao', 'M', 'adrian.dilao@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(375, '2023-0167', 'Mc Lowell', 'F', 'Fabellon', 'M', 'mclowell.fabellon@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(376, '2023-0177', 'John Paul', 'M', 'Fernandez', 'M', 'johnpaul.fernandez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(377, '2023-0249', 'Mark Lyndon', 'L', 'Fransisco', 'M', 'marklyndon.fransisco@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:39', '2026-03-20 15:15:01', NULL),
(378, '2023-0243', 'Princess Elaine', 'A', 'De Torres', 'F', 'kianvash.gale@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(379, '2023-0332', 'Michael', 'B', 'Magat', 'M', 'michael.magat@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(380, '2023-0308', 'John Khim', 'J', 'Moreno', 'M', 'johnkhim.moreno@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(381, '2023-0255', 'Jayson', 'A', 'Ramos', 'M', 'jayson.ramos@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(382, '2023-0322', 'Joel', 'B', 'Villena', 'M', 'joel.villena@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 9, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(383, '2023-0248', 'Jazzle Irish', 'M', 'Cudiamat', 'F', 'jazzleirish.cudiamat@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(384, '2023-0240', 'Jenny', 'M', 'Fajardo', 'F', 'jenny.fajardo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(385, '2023-0299', 'Mary Joy', 'D', 'Sim', 'F', 'maryjoy.sim@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(386, '2023-0309', 'Jordan', 'V', 'Abeleda', 'M', 'jordan.abeleda@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(387, '2023-0150', 'Ralf Jenvher', 'V', 'Atienza', 'M', 'ralfjenvher.atienza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(388, '2023-0284', 'Mon Andrei', 'M', 'Bae', 'M', 'monandrei.bae@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(389, '2023-0261', 'John Mark', 'M', 'Balmes', 'M', 'johnmark.balmes@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:40', '2026-03-20 15:15:01', NULL),
(390, '2023-0209', 'John Russel', 'G', 'Bolaños', 'M', 'johnrussel.bolaos@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(391, '2023-0166', 'Justine James', 'A', 'Dela Cruz', 'M', 'justinejames.delacruz@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(392, '2023-0313', 'Carl John', 'M', 'Evangelista', 'M', 'carljohn.evangelista@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(393, '2023-0274', 'Mon Lester', 'B', 'Faner', 'M', 'monlester.faner@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(394, '2023-0159', 'John Paul', '', 'Freyra', 'M', 'johnpaul.freyra@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(395, '2023-0258', 'Ryan', 'I', 'Garcia', 'M', 'ryan.garcia@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(396, '2023-0223', 'Apple', 'M', 'Braña', 'F', 'jeshlerclifford.gervacio@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(397, '2023-0333', 'Melvic John', 'A', 'Magsino', 'M', 'melvicjohn.magsino@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(398, '2023-0213', 'Jerome', 'B', 'Mauro', 'M', 'jerome.mauro@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(399, '2023-0279', 'Jundell', 'M', 'Morales', 'M', 'jundell.morales@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(400, '2023-0171', 'Adrian', 'R', 'Pampilo', 'M', 'adrian.pampilo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(401, '2023-0300', 'John Carl', 'C', 'Pedragoza', 'M', 'johncarl.pedragoza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:41', '2026-03-20 15:15:01', NULL),
(402, '2023-0295', 'King', 'C', 'Saranillo', 'M', 'king.saranillo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:42', '2026-03-20 15:15:01', NULL),
(403, '2023-0260', 'Jhon Laurence', 'D', 'Victoriano', 'M', 'jhonlaurence.victoriano@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 10, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:42', '2026-03-20 15:15:01', NULL),
(404, '2023-0210', 'Janelle', 'R', 'Absin', 'F', 'janelle.absin@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:42', '2026-03-20 15:15:01', NULL),
(405, '2023-0188', 'Jan Ashley', 'R', 'Bonado', 'F', 'janashley.bonado@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:42', '2026-03-20 15:15:01', NULL),
(406, '2023-0202', 'Robelyn', 'D', 'Bonado', 'F', 'robelyn.bonado@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:42', '2026-03-20 15:15:01', NULL),
(407, '2023-0253', 'Princes', 'A', 'Capote', 'F', 'princes.capote@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:42', '2026-03-20 15:15:01', NULL),
(408, '2023-0228', 'Joann', 'M', 'Carandan', 'F', 'joann.carandan@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:42', '2026-03-20 15:15:01', NULL),
(409, '2023-0272', 'Christine Rose', 'F', 'Catapang', 'F', 'christinerose.catapang@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:42', '2026-03-20 15:15:01', NULL),
(410, '2023-0192', 'Arlyn', 'P', 'Corona', 'F', 'arlyn.corona@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:42', '2026-03-20 15:15:01', NULL),
(411, '2023-0185', 'Stacy Anne', 'G', 'Cortez', 'F', 'stacyanne.cortez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(412, '2023-0199', '', '', 'De Claro Alexa Jane C.', 'F', '.declaroalexajanec@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(413, '2023-0266', 'Angel Ann', 'M', 'De Lara', 'F', 'angelann.delara@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(414, '2023-0172', 'Lorebel', 'A', 'De Leon', 'F', 'lorebel.deleon@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(415, '2023-0257', 'Rocelyn', 'P', 'Dela Rosa', 'F', 'rocelyn.delarosa@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(416, '2023-0256', 'Ronalyn Paulita', '', 'Dela Rosa', 'F', 'ronalynpaulita.delarosa@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(417, '2023-0137', 'Krisnah Joy', 'V', 'Dorias', 'F', 'krisnahjoy.dorias@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(418, '2023-0287', 'Ayessa Jhoy', 'M', 'Gaba', 'F', 'ayessajhoy.gaba@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(419, '2023-0193', 'Margie', 'R', 'Gatilo', 'F', 'margie.gatilo@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(420, '2023-0296', 'Jasmine', 'C', 'Gayao', 'F', 'jasmine.gayao@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(421, '2023-0197', 'Mikaela M', '', 'Hernandez', 'F', 'mikaelam.hernandez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(422, '2023-0189', 'Vanessa Nicole', '', 'Latoga', 'F', 'vanessanicole.latoga@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:43', '2026-03-20 15:15:01', NULL),
(423, '2023-0262', 'Alwena', 'A', 'Madrigal', 'F', 'alwena.madrigal@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(424, '2023-0191', 'Maria Eliza', 'T', 'Magsisi', 'F', 'mariaeliza.magsisi@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(425, '2023-0227', 'Carla Joy', 'L', 'Matira', 'F', 'carlajoy.matira@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(426, '2023-0163', 'Allysa Mae', 'A', 'Mirasol', 'F', 'allysamae.mirasol@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(427, '2023-0247', 'Manilyn', 'G', 'Narca', 'F', 'manilyn.narca@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(428, '2023-0211', 'Sharah Mae', 'P', 'Ojales', 'F', 'sharahmae.ojales@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(429, '2023-0340', 'Geselle', 'C', 'Rivas', 'F', 'geselle.rivas@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(430, '2023-0184', 'Angel Joy', 'A', 'Sanchez', 'F', 'angeljoy.sanchez@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(431, '2023-0341', 'Jamaica Rose', 'M', 'Sarabia', 'F', 'jamaicarose.sarabia@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(432, '2023-0194', 'Nicole', 'A', 'Villafranca', 'F', 'nicole.villafranca@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(433, '2023-0203', 'Jennylyn', 'T', 'Villanueva', 'F', 'jennylyn.villanueva@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:44', '2026-03-20 15:15:01', NULL),
(434, '2023-0277', 'John Lloyd David', 'M', 'Amido', 'M', 'johnlloyddavid.amido@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(435, '2023-0290', 'Reniel', 'L', 'Borja', 'M', 'reniel.borja@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(436, '2023-0179', 'John Carlo', 'G', 'Chiquito', 'M', 'johncarlo.chiquito@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(437, '2023-0301', 'Justin', 'S', 'Como', 'M', 'justin.como@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(438, '2023-0236', 'Moises', 'G', 'Delos Santos', 'M', 'moises.delossantos@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(439, '2023-0226', 'Philip', 'F', 'Garcia', 'M', 'philip.garcia@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(440, '2023-0182', 'Bryan', 'A', 'Peñaescosa', 'M', 'bryan.peaescosa@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(441, '2023-0297', 'John Rick', 'F', 'Ramos', 'M', 'johnrick.ramos@colegiodenaujan.edu.ph', NULL, NULL, 'BPA', 11, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(442, '2023-0220', 'Rezlyn Jhoy', 'S', 'Aguba', 'F', 'rezlynjhoy.aguba@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(443, '2023-0153', 'Lyzel', 'G', 'Bool', 'F', 'lyzel.bool@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(444, '2023-0219', 'Jesca Mae', 'M', 'Chavez', 'F', 'jescamae.chavez@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:45', '2026-03-20 15:15:01', NULL),
(445, '2023-0270', 'Hiedie', 'H', 'Claus', 'F', 'hiedie.claus@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(446, '2023-0155', 'KC', 'D', 'Dela Roca', 'F', 'kc.delaroca@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(447, '2023-0154', 'Bea', 'A', 'Fajardo', 'F', 'bea.fajardo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(448, '2023-0320', 'Sherlyn', '', 'Festin', 'F', 'sherlyn.festin@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(449, '2023-0204', 'Clarissa', 'B', 'Feudo', 'F', 'clarissa.feudo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(450, '2023-0156', 'Irish Karyl', 'G', 'Magcamit', 'F', 'irishkaryl.magcamit@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(451, '2023-0216', 'Cristine', 'S', 'Manalo', 'F', 'cristine.manalo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(452, '2023-0331', 'Geraldine', 'G', 'Manalo', 'F', 'geraldine.manalo@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(453, '2023-0198', 'Shiloh', 'G', 'Manhic', 'F', 'shiloh.manhic@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(454, '2023-0242', 'Shylyn', '', 'Mansalapus', 'F', 'shylyn.mansalapus@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(455, '2023-0291', 'Irish May Roselle', 'C', 'Nao', 'F', 'irishmayroselle.nao@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:46', '2026-03-20 15:15:01', NULL),
(456, '2023-0208', 'Paulyn Grace', '', 'Perez', 'F', 'paulyngrace.perez@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(457, '2023-0181', 'Shane', 'T', 'Ramos', 'F', 'shane.ramos@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(458, '2023-0566', 'Andrea Chel', 'D', 'Rivera', 'F', 'andreachel.rivera@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(459, '2023-0344', 'Angel Bellie', 'G', 'Vargas', 'F', 'angelbellie.vargas@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(460, '2023-0221', 'Jamaica Mickaela', 'Y', 'Villena', 'F', 'jamaicamickaela.villena@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(461, '2023-0268', 'Monaliza', 'F', 'Waing', 'F', 'monaliza.waing@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(462, '2023-0157', 'Jay', 'T', 'Aguilar', 'M', 'jay.aguilar@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(463, '2023-0263', 'Ken Celwyn', 'R', 'Algaba', 'M', 'kencelwyn.algaba@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(464, '2023-0273', 'Mark Lester', 'M', 'Baes', 'M', 'marklester.baes@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(465, '2023-0293', 'John Albert', 'C', 'Bastida', 'M', 'johnalbert.bastida@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(466, '2023-0218', 'Vitoel', 'G', 'Curatcha', 'M', 'vitoel.curatcha@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(467, '2023-0286', 'Karl Marion', 'R', 'De Leon', 'M', 'karlmarion.deleon@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:47', '2026-03-20 15:15:01', NULL),
(468, '2023-0212', 'Renzie Carl', 'C', 'Escaro', 'M', 'renziecarl.escaro@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(469, '2023-0196', 'Nathaniel', 'C', 'Falcunaya', 'M', 'nathaniel.falcunaya@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(470, '2023-0292', 'Kyzer', 'A', 'Gonda', 'M', 'kyzer.gonda@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(471, '2023-0283', 'John Dexter', '', 'Gonzales', 'M', 'johndexter.gonzales@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(472, '2023-0319', 'Reniel', 'B', 'Jara', 'M', 'reniel.jara@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(473, '2023-0158', 'Steven Angelo', '', 'Legayada', 'M', 'stevenangelo.legayada@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(474, '2023-0152', 'Angelo', 'M', 'Lumanglas', 'M', 'angelo.lumanglas@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(475, '2023-0214', 'Jhon Lester', 'M', 'Madrigal', 'M', 'jhonlester.madrigal@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(476, '2023-0162', 'Rhaven', 'G', 'Magmanlac', 'M', 'rhaven.magmanlac@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(477, '2023-0195', 'Jumyr', 'M', 'Moreno', 'M', 'jumyr.moreno@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(478, '2023-0176', 'Dan Lloyd', 'B', 'Paala', 'M', 'danlloyd.paala@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(479, '2023-0206', 'Patrick James', 'U', 'Romasanta', 'M', 'patrickjames.romasanta@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:48', '2026-03-20 15:15:01', NULL),
(480, '2023-0186', 'Jereck', 'M', 'Roxas', 'M', 'jereck.roxas@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(481, '2023-0217', 'Jan Denmark', 'C', 'Santos', 'M', 'jandenmark.santos@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(482, '2023-0267', 'John Paolo', 'N', 'Torralba', 'M', 'johnpaolo.torralba@colegiodenaujan.edu.ph', NULL, NULL, 'BSIS', 12, '3', '3rd Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(483, '2022-0079', 'Dianne Christine Joy', 'A', 'Alulod', 'F', 'diannechristinejoy.alulod@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(484, '2022-0080', 'Rechel', 'R', 'Arenas', 'F', 'rechel.arenas@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(485, '2022-0081', 'Allyna', 'A', 'Atienza', 'F', 'allyna.atienza@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(486, '2022-0130', 'Angela', 'A', 'Bonilla', 'F', 'angela.bonilla@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(487, '2022-0082', 'Aira', 'F', 'Cabulao', 'F', 'aira.cabulao@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(488, '2022-0124', 'Janice', 'C', 'Cadacio', 'F', 'janice.cadacio@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(489, '2022-0083', 'Maries', 'D', 'Cantos', 'F', 'maries.cantos@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(490, '2022-0084', 'Veronica', 'C', 'Cantos', 'F', 'veronica.cantos@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL),
(491, '2022-0139', 'Diana', 'G', 'Caringal', 'F', 'diana.caringal@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:49', '2026-03-20 15:15:01', NULL);
INSERT INTO `students` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `gender`, `email`, `contact_number`, `address`, `department`, `section_id`, `yearlevel`, `year_level`, `avatar`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(492, '2022-0085', 'Lorebeth', 'C', 'Casapao', 'F', 'lorebeth.casapao@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(493, '2022-0086', 'Carla Jane', 'G', 'Chiquito', 'F', 'carlajane.chiquito@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(494, '2022-0089', 'Melody', 'T', 'Enriquez', 'F', 'melody.enriquez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(495, '2022-0090', 'Maricon', 'A', 'Evangelista', 'F', 'maricon.evangelista@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(496, '2022-0091', 'Mary Ann', 'D', 'Fajardo', 'F', 'maryann.fajardo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(497, '2022-0092', 'Kaecy', 'F', 'Ferry', 'F', 'kaecy.ferry@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(498, '2022-0140', 'Zybel', 'V', 'Garan', 'F', 'zybel.garan@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(499, '2022-0118', 'IC Pamela', 'M', 'Gutierrez', 'F', 'icpamela.gutierrez@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(500, '2022-0096', 'Jane Monica', 'P', 'Mansalapus', 'F', 'janemonica.mansalapus@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(501, '2022-0097', 'Hanna Yesha Mae', 'D', 'Mercado', 'F', 'hannayeshamae.mercado@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(502, '2022-0098', 'Abegail', 'D', 'Moong', 'F', 'abegail.moong@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(503, '2022-0125', 'Laiza Marie', 'M', 'Pole', 'F', 'laizamarie.pole@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:50', '2026-03-20 15:15:01', NULL),
(504, '2022-0142', 'Jarryfel', 'N', 'Tembrevilla', 'F', 'jarryfel.tembrevilla@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(505, '2022-0136', 'Jay Mark', 'G', 'Avelino', 'M', 'jaymark.avelino@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(506, '2022-0072', 'Jairus', 'A', 'Cabales', 'M', 'jairus.cabales@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(507, '2022-0075', 'Jleo Nhico Mari', 'M', 'Mazo', 'M', 'jleonhicomari.mazo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(508, '2022-0076', 'Mark Cyrel', 'F', 'Panganiban', 'M', 'markcyrel.panganiban@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(509, '2022-0117', 'Bernabe Dave', 'F', 'Solas', 'M', 'bernabedave.solas@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(510, '2022-0078', 'Mark June', 'G', 'Villena', 'M', 'markjune.villena@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 13, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(511, '2022-0122', 'Nhicel', 'M', 'Bueno', 'F', 'nhicel.bueno@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(512, '2022-0135', 'Dianne Mae', 'R', 'Cezar', 'F', 'diannemae.cezar@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(513, '2022-0147', 'Princess Joy', 'P', 'De Castro', 'F', 'princessjoy.decastro@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(514, '2022-0141', 'Shiela Mae', 'M', 'Fajardo', 'F', 'shielamae.fajardo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(515, '2022-0115', 'Shiela Marie', 'B', 'Garcia', 'F', 'shielamarie.garcia@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:51', '2026-03-20 15:15:01', NULL),
(516, '2022-0129', 'Jessa', 'M', 'Geneta', 'F', 'jessa.geneta@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(517, '2022-0094', 'Jee Anne', 'R', 'Llamoso', 'F', 'jeeanne.llamoso@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(518, '2022-0123', 'Princess Jenille', 'A', 'Santos', 'F', 'princessjenille.santos@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(519, '2022-0099', 'Von Lester', 'R', 'Algaba', 'M', 'vonlester.algaba@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(520, '2022-0100', 'John Aaron', 'M', 'Aniel', 'M', 'johnaaron.aniel@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(521, '2022-0101', 'Keil John', 'C', 'Antenor', 'M', 'keiljohn.antenor@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(522, '2022-0102', 'Mark Joshua', 'M', 'Bacay', 'M', 'markjoshua.bacay@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(523, '2022-0128', 'Michael', 'A', 'De Guzman', 'M', 'michael.deguzman@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(524, '2022-0107', 'Christian', '', 'Delda', 'M', 'christian.delda@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(525, '2022-0108', 'Mark Vincent Earl', 'R', 'Gan', 'M', 'lloyd.evangelista@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(526, '2022-0073', 'Samson', 'L', 'Fulgencio', 'M', 'samson.fulgencio@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:52', '2026-03-20 15:15:01', NULL),
(527, '2022-0145', 'John Dragan', 'B', 'Gardoce', 'M', 'johndragan.gardoce@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL),
(528, '2022-0127', 'John Elmer', '', 'Gonzales', 'M', 'johnelmer.gonzales@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL),
(529, '2022-0144', 'Mark Vender', 'N', 'Muhi', 'M', 'markvender.muhi@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL),
(530, '2022-0112', 'Marc Paulo', 'B', 'Relano', 'M', 'marcpaulo.relano@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL),
(531, '2022-0113', 'Cee Jey', 'G', 'Rellora', 'M', 'ceejey.rellora@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL),
(532, '2022-0134', 'Franklin', 'R', 'Salcedo', 'M', 'franklin.salcedo@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL),
(533, '2022-0120', 'Russel', 'I', 'Sason', 'M', 'russel.sason@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL),
(534, '2022-0132', 'John Paul', 'D', 'Teves', 'M', 'johnpaul.teves@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL),
(535, '2022-0131', 'John Xavier', 'A', 'Villanueva', 'M', 'johnxavier.villanueva@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL),
(536, '2022-0114', 'Reinier Aron', 'L', 'Visayana', 'M', 'reinieraron.visayana@colegiodenaujan.edu.ph', NULL, NULL, 'BTVTED', 14, '4', '4th Year', NULL, 'active', '2026-03-18 20:52:53', '2026-03-20 15:15:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_violation_levels`
--

DROP TABLE IF EXISTS `student_violation_levels`;
CREATE TABLE IF NOT EXISTS `student_violation_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `violation_type` varchar(50) NOT NULL,
  `current_level` enum('permitted1','permitted2','warning1','warning2','warning3','disciplinary') NOT NULL DEFAULT 'permitted1',
  `permitted_count` int NOT NULL DEFAULT '0',
  `warning_count` int NOT NULL DEFAULT '0',
  `total_violations` int NOT NULL DEFAULT '0',
  `last_violation_date` date DEFAULT NULL,
  `last_violation_time` time DEFAULT NULL,
  `last_location` varchar(50) DEFAULT NULL,
  `last_reported_by` varchar(100) DEFAULT NULL,
  `last_notes` text,
  `status` enum('active','resolved','disciplinary') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_violation` (`student_id`,`violation_type`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_violation_type` (`violation_type`),
  KEY `idx_current_level` (`current_level`),
  KEY `idx_status` (`status`),
  KEY `idx_last_violation_date` (`last_violation_date`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `username` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=MyISAM AUTO_INCREMENT=269 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `username`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 02:10:21'),
(2, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 02:10:49'),
(3, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 02:11:00'),
(4, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 02:15:48'),
(5, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 02:20:38'),
(6, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 02:23:22'),
(7, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 02:40:55'),
(8, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 03:12:44'),
(9, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 03:15:17'),
(10, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:15:46'),
(11, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:18:53'),
(12, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:26:05'),
(13, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:27:40'),
(14, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:27:53'),
(15, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:30:16'),
(16, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:33:24'),
(17, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:35:48'),
(18, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:44:04'),
(19, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:58:36'),
(20, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 04:59:33'),
(21, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 05:04:16'),
(22, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 05:27:42'),
(23, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 05:31:30'),
(24, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 05:35:26'),
(25, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 05:43:34'),
(26, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 06:18:05'),
(27, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 15:35:50'),
(28, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 15:49:02'),
(29, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 16:00:29'),
(30, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 16:05:33'),
(31, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 16:13:07'),
(32, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 16:20:43'),
(33, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 16:25:23'),
(34, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 16:53:21'),
(35, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 17:04:39'),
(36, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 17:12:13'),
(37, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 17:16:24'),
(38, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 17:17:33'),
(39, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 17:17:42'),
(40, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 17:23:24'),
(41, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 17:26:10'),
(42, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-09 23:27:36'),
(43, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 02:48:52'),
(44, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 02:49:29'),
(45, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 03:49:55'),
(46, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/145.0.0.0', '2026-03-10 04:00:19'),
(47, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 04:01:06'),
(48, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/145.0.0.0', '2026-03-10 04:01:29'),
(49, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/145.0.0.0', '2026-03-10 04:01:45'),
(50, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 04:09:11'),
(51, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 04:10:44'),
(52, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 04:10:52'),
(53, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 04:11:39'),
(54, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 04:13:22'),
(55, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 07:28:39'),
(56, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 07:54:11'),
(57, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 22:56:19'),
(58, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 23:05:00'),
(59, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 03:32:00'),
(60, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 03:32:33'),
(61, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 03:36:59'),
(62, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 03:39:40'),
(63, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 03:39:57'),
(64, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 03:52:24'),
(65, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 06:18:42'),
(66, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 07:11:10'),
(67, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 07:44:46'),
(68, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 12:16:14'),
(69, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 13:56:56'),
(70, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 14:02:25'),
(71, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 14:04:46'),
(72, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 14:17:12'),
(73, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 14:20:55'),
(74, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 15:20:14'),
(75, 3051, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 15:20:53'),
(76, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 20:45:18'),
(77, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 20:53:30'),
(78, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 20:54:05'),
(79, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 21:00:19'),
(80, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 21:05:02'),
(81, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 21:07:08'),
(82, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 00:40:49'),
(83, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 00:41:39'),
(84, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 00:53:09'),
(85, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 00:53:41'),
(86, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 01:01:47'),
(87, 3053, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 01:02:35'),
(88, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 01:03:34'),
(89, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 01:14:19'),
(90, 3025, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 01:14:57'),
(91, 3025, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 01:22:20'),
(92, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 01:25:18'),
(93, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 01:48:42'),
(94, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 02:00:21'),
(95, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 02:04:18'),
(96, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 02:21:11'),
(97, 2572, 'adminOsas@colegio.edu', 'Admin Created', 'New admin created: user (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 02:42:40'),
(98, 3116, 'user', 'Login', 'User logged in: user (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 02:43:05'),
(99, 3025, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 02:56:34'),
(100, 3116, 'user', 'Login', 'User logged in: user (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 11:17:56'),
(101, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 12:27:42'),
(102, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 11:29:43'),
(103, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 23:26:34'),
(104, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-15 06:05:36'),
(105, 2572, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-15 07:32:50'),
(106, 3116, 'user', 'Login', 'User logged in: user (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-15 13:39:41'),
(107, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-15 13:41:26'),
(108, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-15 14:02:56'),
(109, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-15 14:13:51'),
(110, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-15 14:14:40'),
(111, 5739, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-15 14:15:10'),
(112, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-15 23:11:08'),
(113, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-16 10:31:52'),
(114, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-17 18:37:44'),
(115, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 00:39:36'),
(116, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 06:09:26'),
(117, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 12:23:25'),
(118, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 21:29:51'),
(119, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 21:40:00'),
(120, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 21:43:52'),
(121, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 21:51:39'),
(122, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 21:58:03'),
(123, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 22:08:17'),
(124, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 22:12:44'),
(125, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 22:17:29'),
(126, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 22:27:51'),
(127, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 22:28:38'),
(128, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 22:30:13'),
(129, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 22:43:11'),
(130, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 22:48:05'),
(131, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:00:32'),
(132, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:01:03'),
(133, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:03:58'),
(134, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:10:54'),
(135, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:15:48'),
(136, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:20:36'),
(137, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:29:29'),
(138, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:35:24'),
(139, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:37:03'),
(140, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 23:37:56'),
(141, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:11:59'),
(142, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:12:47'),
(143, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:13:52'),
(144, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:34:06'),
(145, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:34:35'),
(146, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:35:10'),
(147, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:35:28'),
(148, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:44:42'),
(149, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:45:20'),
(150, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:45:57'),
(151, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:46:30'),
(152, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:52:05'),
(153, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:55:45'),
(154, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:56:19'),
(155, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:57:44'),
(156, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 00:58:42'),
(157, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:01:18'),
(158, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:09:41'),
(159, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:18:15'),
(160, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:25:50'),
(161, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:29:35'),
(162, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:29:59'),
(163, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:34:59'),
(164, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:35:29'),
(165, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:39:38'),
(166, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:40:09'),
(167, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:42:19'),
(168, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:44:52'),
(169, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:48:33'),
(170, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:49:02'),
(171, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:49:37'),
(172, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:53:47'),
(173, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 01:54:45'),
(174, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:00:55'),
(175, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:07:00'),
(176, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:08:17'),
(177, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:09:10'),
(178, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:13:05'),
(179, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:14:47'),
(180, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:22:18'),
(181, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:23:45'),
(182, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:28:24'),
(183, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:29:04'),
(184, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:34:24'),
(185, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:34:58'),
(186, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:35:39'),
(187, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:40:38'),
(188, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:41:08'),
(189, 6273, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:42:15'),
(190, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:42:37'),
(191, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:48:13'),
(192, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:52:22'),
(193, 6273, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 02:52:59'),
(194, 6273, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:00:57'),
(195, 6273, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:01:48'),
(196, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:03:26'),
(197, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:11:36'),
(198, 6273, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:12:18');
INSERT INTO `system_logs` (`id`, `user_id`, `username`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(199, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:14:46'),
(200, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:16:25'),
(201, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:16:55'),
(202, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:17:45'),
(203, 6273, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:18:05'),
(204, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:18:34'),
(205, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:24:56'),
(206, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:27:10'),
(207, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:27:29'),
(208, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:29:02'),
(209, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:40:35'),
(210, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:48:29'),
(211, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:49:00'),
(212, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:49:33'),
(213, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 03:49:57'),
(214, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 04:03:16'),
(215, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 04:07:39'),
(216, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 04:11:41'),
(217, 6273, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 04:13:09'),
(218, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 04:14:24'),
(219, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 04:16:53'),
(220, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 04:43:49'),
(221, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 04:49:03'),
(222, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 05:21:24'),
(223, 6273, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 05:24:52'),
(224, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 05:25:24'),
(225, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 05:29:11'),
(226, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 05:30:52'),
(227, 6273, '2023-0195', 'Login', 'User logged in: 2023-0195 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 05:31:35'),
(228, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 05:32:11'),
(229, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-19 05:38:24'),
(230, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-20 02:32:52'),
(231, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-20 02:35:03'),
(232, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-20 02:35:26'),
(233, 6247, '2023-0216', 'Login', 'User logged in: 2023-0216 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-20 02:36:05'),
(234, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-20 07:10:29'),
(235, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 05:26:12'),
(236, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 05:44:55'),
(237, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 05:48:17'),
(238, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 05:59:30'),
(239, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 06:12:56'),
(240, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-30 04:36:11'),
(241, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-31 04:02:29'),
(242, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-31 05:02:28'),
(243, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-31 05:32:08'),
(244, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 03:57:59'),
(245, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-07 14:49:36'),
(246, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-13 04:36:53'),
(247, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 05:29:48'),
(248, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 02:06:54'),
(249, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 02:32:49'),
(250, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 07:17:03'),
(251, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 07:22:51'),
(252, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 07:23:03'),
(253, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:01:40'),
(254, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:17:06'),
(255, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:31:37'),
(256, 6275, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:32:29'),
(257, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:34:49'),
(258, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:40:05'),
(259, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:41:06'),
(260, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:47:23'),
(261, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:50:28'),
(262, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:56:43'),
(263, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:57:03'),
(264, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 09:57:53'),
(265, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 10:00:12'),
(266, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 10:41:08'),
(267, 6275, '2023-0206', 'Login', 'User logged in: 2023-0206 (Role: user)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 10:41:49'),
(268, 3116, 'adminOsas@colegio.edu', 'Login', 'User logged in: adminOsas@colegio.edu (Role: admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-02 07:16:16');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'last_monthly_reset', '2026-05', '2026-05-02 07:16:18');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `facebook_id` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `full_name` varchar(100) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_google_id` (`google_id`(250)),
  KEY `idx_users_facebook_id` (`facebook_id`(250))
) ENGINE=MyISAM AUTO_INCREMENT=6333 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `google_id`, `facebook_id`, `profile_picture`, `password`, `role`, `full_name`, `student_id`, `is_active`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(6330, '2022-0132', 'johnpaul.teves@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$P6k0Ux9Xx6wqLebbH9JSjOoO.YFWQYv/1otys1uBCcjdLa135VdVK', 'user', 'John Paul Teves', '2022-0132', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6331, '2022-0131', 'johnxavier.villanueva@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$fchMen0uN/8KpwQziQNYaegL7X.bwPe/rNcw/.GZtKtl9gJs9TIwS', 'user', 'John Xavier Villanueva', '2022-0131', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6332, '2022-0114', 'reinieraron.visayana@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zqX/JnfPAyG.kACEOLaNbOkTjFcoAiGPnWedNZxKhzSGleAq9jKNi', 'user', 'Reinier Aron Visayana', '2022-0114', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6329, '2022-0120', 'russel.sason@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$QnP8XmyT9hbS9CVsEPBexu/LaFHjRyd8AkEb7dDICNWLUObY0Qa9S', 'user', 'Russel Sason', '2022-0120', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6328, '2022-0134', 'franklin.salcedo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$icwAskHcEEKS751t9CAAaOxknqpfpzNuYFztbRh2DCCcljuxdx5V.', 'user', 'Franklin Salcedo', '2022-0134', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6327, '2022-0113', 'ceejey.rellora@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qkV/6hCp7f6Yk7pJi2ng2uETKNC0V841QSmCCBDv5kLz84pTfZQMe', 'user', 'Cee Jey Rellora', '2022-0113', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6323, '2022-0145', 'johndragan.gardoce@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$8FpzJA.Xcx3DIPFAbskO..XbVK4Th0dDFjZonvsu2HEkT/OzGQs.q', 'user', 'John Dragan Gardoce', '2022-0145', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6324, '2022-0127', 'johnelmer.gonzales@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$iozQzieL6BC1WzM8KSFXcetOMUfUPmWWJZTHo7Voc3BL837y82yn2', 'user', 'John Elmer Gonzales', '2022-0127', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6325, '2022-0144', 'markvender.muhi@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$PJVGboIwA90dJhUm8z.77OVDmQ1cAmJB9FMoRsOpcwjuT95NhyEUe', 'user', 'Mark Vender Muhi', '2022-0144', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6326, '2022-0112', 'marcpaulo.relano@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$by01f.XVlqWZgTM7caDLxOkA7/Fv.FQdbdkquZUyAy1VFg3/OSDLS', 'user', 'Marc Paulo Relano', '2022-0112', 1, 'active', '2026-03-18 12:52:53', '2026-03-18 12:52:53', NULL),
(6322, '2022-0073', 'samson.fulgencio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$1Nb3CuJdNYAtPl9LCc6BJOKl6sVNZnGBDyj3SndIGnku9RShTJOSa', 'user', 'Samson Fulgencio', '2022-0073', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6321, '2022-0108', 'lloyd.evangelista@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ANo1FYz/WYbjIpo8GDmzO.qWFjZmulgbZbTx/Sob5zUctdLbCWEZe', 'user', 'Lloyd Evangelista', '2022-0108', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6320, '2022-0107', 'christian.delda@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$5IvJkRUHVUbcZRe.2nMzwuNUIvWH1Rd3y4PtQVe/2zkitRiO6wf8a', 'user', 'Christian Delda', '2022-0107', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6319, '2022-0128', 'michael.deguzman@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$SG2PFiJowZ3fwGKVziOq5.9Hx4YRM6NtyBu6YA5D4hsTiBYrLxvYy', 'user', 'Michael De Guzman', '2022-0128', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6315, '2022-0099', 'vonlester.algaba@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WmFZHXjxD5hKP/jvbU43vugvWQrWqLWNwz0CG3en23UBA5pQID3Rm', 'user', 'Von Lester Algaba', '2022-0099', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6316, '2022-0100', 'johnaaron.aniel@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$gZlxuomWF9ct7qQMOY3iJenAWx1Dd7uO5V21H5hZqchvvKx5ziCK6', 'user', 'John Aaron Aniel', '2022-0100', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6317, '2022-0101', 'keiljohn.antenor@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$/zhRnP/eTak5sutL7Cobcebrusf4gd0pfbhAPXSDyY6MDQzcrrBQi', 'user', 'Keil John Antenor', '2022-0101', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6318, '2022-0102', 'markjoshua.bacay@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$OGRI1shBZ0Qj4FeMBLZYpuDGQ19SobOnMYdDZSewe4EgqEieSngQy', 'user', 'Mark Joshua Bacay', '2022-0102', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6314, '2022-0123', 'princessjenille.santos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$n3lbM7uVk/PZBH5wA0W6tOCMPewPAcjIOAC1.ha.NLBuwAFesaY0a', 'user', 'Princess Jenille Santos', '2022-0123', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6311, '2022-0115', 'shielamarie.garcia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$2Tj8jPO3mihOzs3QviMN9ece410DVmz4HJkhVElBTcj4B2FddltNW', 'user', 'Shiela Marie Garcia', '2022-0115', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6313, '2022-0094', 'jeeanne.llamoso@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$YVASowPQI7YhjsHYPIAVwedR9Buy8SwQuBBnpd/v.hJLVp4soSo4u', 'user', 'Jee Anne Llamoso', '2022-0094', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6312, '2022-0129', 'jessa.geneta@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$72FSZKhHS89x3UgmZVjuz.Xlk/caaPZRlsf.CRzS92AtUiD4jJH3K', 'user', 'Jessa Geneta', '2022-0129', 1, 'active', '2026-03-18 12:52:52', '2026-03-18 12:52:52', NULL),
(6310, '2022-0141', 'shielamae.fajardo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$MvvsGa9IhF/KmV5MpPM5Au5Va71kB9xNAHCAW7NCnfBkvO/ALAgLu', 'user', 'Shiela Mae Fajardo', '2022-0141', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6309, '2022-0147', 'princessjoy.decastro@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$LQLPwDFFqGA3DxenRgZNFuuJJP/DK9gkf8ZPS5.8rRT/Ov/CZaQ.K', 'user', 'Princess Joy De Castro', '2022-0147', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6308, '2022-0135', 'diannemae.cezar@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qpkhb9iUMzsrEV5q9mWdqegNBUj68ft1rO/ugPfsktHTufvJY1AFO', 'user', 'Dianne Mae Cezar', '2022-0135', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6306, '2022-0078', 'markjune.villena@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$NuvHgqGMb3g/d0YxPk4fcuJWOxWWoPBR7iX.PjqXtxaR4smcF2aBC', 'user', 'Mark June Villena', '2022-0078', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6307, '2022-0122', 'nhicel.bueno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$/vK.1TgnHO4XEqqr/fD/feGoVw1i9gpwhDQrFDLvkca1xy6hb8oxC', 'user', 'Nhicel Bueno', '2022-0122', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6302, '2022-0072', 'jairus.cabales@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$uNPFz046H818OTJx3zpOnu1z8w9EdnkP2Ai.oYj1Hgqp.B.wrQOm2', 'user', 'Jairus Cabales', '2022-0072', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6305, '2022-0117', 'bernabedave.solas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$GAgTeOPojjZvaxmE5N1J3.j9d2325DYIAgB77cwn4Ei/xkpN3ACK6', 'user', 'Bernabe Dave Solas', '2022-0117', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6304, '2022-0076', 'markcyrel.panganiban@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$j74QGBsgfeq2Mu.LFY3dN.CBXxf1LOoKKlWBzr6QCs2GCicBr0N.C', 'user', 'Mark Cyrel Panganiban', '2022-0076', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6303, '2022-0075', 'jleonhicomari.mazo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$dKmJEY11z0a.VUmTWUCeKOlAEzTHFECFKDz6l5JspAS7BtXYxSdy2', 'user', 'Jleo Nhico Mari Mazo', '2022-0075', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6301, '2022-0136', 'jaymark.avelino@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Yaxk3mCoKGSWsGNrsPTnX.SS.7U3WYcQvjvKjs9/Og0MFtvhsyO8i', 'user', 'Jay Mark Avelino', '2022-0136', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6300, '2022-0142', 'jarryfel.tembrevilla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$.xvRUTORqkjfsIUNh7UFO.4xCNHgeqsgE121AifjncGuvPw/i09ny', 'user', 'Jarryfel Tembrevilla', '2022-0142', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6298, '2022-0098', 'abegail.moong@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$FtlP97O2CuMBW5uTdYJtLuQfnwxN0zgFblIafJ1hQSXK5xt/wSHlO', 'user', 'Abegail Moong', '2022-0098', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6299, '2022-0125', 'laizamarie.pole@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$SqYQ.0a.GUaVXWaFftQL2eRIVFwPB9.IzS/o7kNniosHfVcLnX89S', 'user', 'Laiza Marie Pole', '2022-0125', 1, 'active', '2026-03-18 12:52:51', '2026-03-18 12:52:51', NULL),
(6293, '2022-0092', 'kaecy.ferry@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$1c7cUh0Q7JuM0i6XMvQafeFsqBG3x0WGAhpzzkH8FQAvYId334Mv.', 'user', 'Kaecy Ferry', '2022-0092', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6297, '2022-0097', 'hannayeshamae.mercado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$q9LOc9LE7dvj6RCNUzENw..N/vuTsdd5THHFtMLW087Rq0X3XKHo6', 'user', 'Hanna Yesha Mae Mercado', '2022-0097', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6296, '2022-0096', 'janemonica.mansalapus@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$iDONyQpzcbxE2x15MUd1a.VGEXEX40dUkrsY19Cs3H1WYJZ2ao2BO', 'user', 'Jane Monica Mansalapus', '2022-0096', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6295, '2022-0118', 'icpamela.gutierrez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$MhvJAyG0zPlnIRbcyjTWLe6rlUqKRiKrt3O0UAZpz6r8eP6RKvUFS', 'user', 'IC Pamela Gutierrez', '2022-0118', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6294, '2022-0140', 'zybel.garan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$O0/wa0Sg2N5X./zgZTUCkOlCRDdPrk5vD.1or5.u/26evHusitbaW', 'user', 'Zybel Garan', '2022-0140', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6292, '2022-0091', 'maryann.fajardo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$VQmfnNSEQAvPMIZioVgQ/OO0y.L9YO3xLpfhvHCuk7xGQAPVHvoR6', 'user', 'Mary Ann Fajardo', '2022-0091', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6291, '2022-0090', 'maricon.evangelista@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$nCNZp/JlRtI6O/k/BMzv0.8o3aNYECYwX0.PWiwebAXyXCIaIAYkm', 'user', 'Maricon Evangelista', '2022-0090', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6288, '2022-0085', 'lorebeth.casapao@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qZiSpv3nPYbmCZotJ.MAIeHE0wNNH8QW2h.9QdysKw6.arNhjWF9i', 'user', 'Lorebeth Casapao', '2022-0085', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6289, '2022-0086', 'carlajane.chiquito@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$QdipgFRBrjVBLeZ0sVKeXO6gE9mQt4jUJkpv702eSP3KU9yZn4zTG', 'user', 'Carla Jane Chiquito', '2022-0086', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6290, '2022-0089', 'melody.enriquez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$B/VApFuRJ5Y4IvDbJivqqucHezlXyO5e6dxaU4Yf0iTFQMZ7Ea1zW', 'user', 'Melody Enriquez', '2022-0089', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6287, '2022-0139', 'diana.caringal@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$VtlmE8UdAsiuVC05DW14AezuJdBL3RJPbxh5p/wc2szdiin8GJ0BC', 'user', 'Diana Caringal', '2022-0139', 1, 'active', '2026-03-18 12:52:50', '2026-03-18 12:52:50', NULL),
(6286, '2022-0084', 'veronica.cantos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$sA7.DoBV.GBQNpzz4eGGSuKbR1RnUR0lA8wYT4Rm05AvIdYyreEd.', 'user', 'Veronica Cantos', '2022-0084', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6284, '2022-0124', 'janice.cadacio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$8.11x40hxViPMEA65aAxMe/xeGK.A4YuQIJTDZnqibFbznX/vKGBC', 'user', 'Janice Cadacio', '2022-0124', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6285, '2022-0083', 'maries.cantos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Cqh7rusmK68lsulgoYecSuzYLBmpKcYlDAAc894DelxryBnG/um9K', 'user', 'Maries Cantos', '2022-0083', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6282, '2022-0130', 'angela.bonilla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$vKCJOKhCvWpJDc4z0S80POoSP0hYmtU7TdKAgLeqDyWxMSC4UkuXe', 'user', 'Angela Bonilla', '2022-0130', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6283, '2022-0082', 'aira.cabulao@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$LWHBPZUDBseADJq8UjRiMOBXReBoZkbiy7QdSRy/cP0y3FD9jkdoy', 'user', 'Aira Cabulao', '2022-0082', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6281, '2022-0081', 'allyna.atienza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$w8DmV0cZeH9ZmQ7Pew34CuENXwesnEDFTgniAYL1pF2sYdfIk4u06', 'user', 'Allyna Atienza', '2022-0081', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6279, '2022-0079', 'diannechristinejoy.alulod@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$HLJ12oY..fWa1rbYa1IJ8.O//41zuMaJ7w996XJ8Dd.Bo1gPWh/sO', 'user', 'Dianne Christine Joy Alulod', '2022-0079', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6280, '2022-0080', 'rechel.arenas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$N9Ccyk7MIOR0dbrCTwK.0eYImPKjkeMg/AZICQmQMxPqX8RffCc8y', 'user', 'Rechel Arenas', '2022-0080', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6276, '2023-0186', 'jereck.roxas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Uqsc8ILcSl0WQB7a6QHQ5eNez6klXpriOjOvD9jfjCsSmJTC1Nr9O', 'user', 'Jereck Roxas', '2023-0186', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6277, '2023-0217', 'jandenmark.santos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zt4ZLQCkO8OabrsgfHpsgulah4hTAI3B7wIXGZIjLHEdDcBxcVu52', 'user', 'Jan Denmark Santos', '2023-0217', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6278, '2023-0267', 'johnpaolo.torralba@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$jmzTl3qlwmZ2D2q3uKZqE.zbcjml1e1givHFqaBQY7R5bxrd6LaVC', 'user', 'John Paolo Torralba', '2023-0267', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6275, '2023-0206', 'patrickjames.romasanta@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$sGRHC9AaGMra7B44AGy6q..wkR9DRErgvEqeQ3iGWA2PUYmMK18GW', 'user', 'Patrick James Romasanta', '2023-0206', 1, 'active', '2026-03-18 12:52:49', '2026-03-18 12:52:49', NULL),
(6272, '2023-0162', 'rhaven.magmanlac@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$bmD8fo9JHIYzVyY2FNB9P.rFaA0IL9bCiY6emZJdURm1XJIupNSvK', 'user', 'Rhaven Magmanlac', '2023-0162', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6273, '2023-0195', 'jumyr.moreno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$L7hSFM6fXPC4KLuSDTXxUejm2TbBBQya3Rzg6C8geWakOZ00qyosO', 'user', 'Jumyr Moreno', '2023-0195', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6274, '2023-0176', 'danlloyd.paala@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$VU9R6IGh3Z5pamK5HegxOONefLjq8hDbrIRWTqusfP7qz0pWyWYhe', 'user', 'Dan Lloyd Paala', '2023-0176', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6270, '2023-0152', 'angelo.lumanglas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$g1HNDv77Ang3k.X4cGCbXedOVpJsBURDIdFLVO1bcKKVUEzI9UiTC', 'user', 'Angelo Lumanglas', '2023-0152', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6271, '2023-0214', 'jhonlester.madrigal@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$4wnaJgZ.XkklcyUo1ZQ3Meia2zt/jPNrNVShov8yqeTmeA/ukdnhq', 'user', 'Jhon Lester Madrigal', '2023-0214', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6269, '2023-0158', 'stevenangelo.legayada@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$hiQao2juwcyfn7.9yOXrauZrMUgXlfJ2pVxfRDSPOWxXX3QN2AtJi', 'user', 'Steven Angelo Legayada', '2023-0158', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6266, '2023-0292', 'kyzer.gonda@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$edM2BwY0v6b1JFyHtFokCeBWx1PqXtL1Xx0G1amLQK5dJDTy0avfK', 'user', 'Kyzer Gonda', '2023-0292', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6267, '2023-0283', 'johndexter.gonzales@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$gpX.tcNGftFwDQNQCrmhyOGXGMe25LH8cyTPrA5K5vQTYAf.GhU8O', 'user', 'John Dexter Gonzales', '2023-0283', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6268, '2023-0319', 'reniel.jara@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qzqyOKY96dEIqyMX4ENQzOpzE2fD9jm9BLkAIMzQbNaPXqFklYkVG', 'user', 'Reniel Jara', '2023-0319', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6264, '2023-0212', 'renziecarl.escaro@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$9SYjdjSaUXSIg1.Pif1bb.nUN0iARdA7xK4QomLrJoR9sMGJ.4V0S', 'user', 'Renzie Carl Escaro', '2023-0212', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6265, '2023-0196', 'nathaniel.falcunaya@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$JAaJpa25yOZKx1.dmYwqG.0KimFiyT/5t4zkdWbiSaD7vaxzgE9A2', 'user', 'Nathaniel Falcunaya', '2023-0196', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6263, '2023-0286', 'karlmarion.deleon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$q4w/7Ka7B3c7YTxmbgfEDedoO84pM5XM/uCd76EIZYNrMdnIgE6zW', 'user', 'Karl Marion De Leon', '2023-0286', 1, 'active', '2026-03-18 12:52:48', '2026-03-18 12:52:48', NULL),
(6262, '2023-0218', 'vitoel.curatcha@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$W7Zhv40Md15gtXKCS3m6.u8tiwh/BY52XkANaAYkfdJ9gnZfCmKty', 'user', 'Vitoel Curatcha', '2023-0218', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6259, '2023-0263', 'kencelwyn.algaba@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$BMoOc.NR3oCgPXACRqf3du93v06yJ5FnyKfJ5Zg8hJ.auZegeP85q', 'user', 'Ken Celwyn Algaba', '2023-0263', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6260, '2023-0273', 'marklester.baes@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$NDohbEdZBiYb8YzlQsMBkuc5GhLnkHnUTSbGFQD3Cub.PdZZFjX5O', 'user', 'Mark Lester Baes', '2023-0273', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6261, '2023-0293', 'johnalbert.bastida@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$/DvUB243o/lSc.fcYp9kvOpw9douDDhoK.NJZBvFlQ7C8k6dpdDe6', 'user', 'John Albert Bastida', '2023-0293', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6256, '2023-0221', 'jamaicamickaela.villena@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$EcP3iVowQRrFHG5B7yGzVufnMbh47JxF9DSzJmOquZRtY9K/QVzZO', 'user', 'Jamaica Mickaela Villena', '2023-0221', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6257, '2023-0268', 'monaliza.waing@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$tiF52k1fj8sP91dhtHGcX.ePyzWb0X8toF41nPb/upov4LFxWyDO6', 'user', 'Monaliza Waing', '2023-0268', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6258, '2023-0157', 'jay.aguilar@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$QiFTMJEWWBcy97OuDhqoNuojQX8NW9QUiEGn71Po1ZRFFMYjht3qi', 'user', 'Jay Aguilar', '2023-0157', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6255, '2023-0344', 'angelbellie.vargas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$V.9kK0/3GmSqSSHkibru.uU7gcMd3jnDB7uCi0W9rY1V7sUYl4hMa', 'user', 'Angel Bellie Vargas', '2023-0344', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6250, '2023-0242', 'shylyn.mansalapus@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$wmjGut51Cfkqq1RCJIy3GusI.YsufinXJxy3ef6YSeIBYFCcV0uEu', 'user', 'Shylyn Mansalapus', '2023-0242', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6251, '2023-0291', 'irishmayroselle.nao@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$idgKZL78BpjnnQUaqhthae8cprUdeEj4Ge.jEI.obxJm9majDRNrK', 'user', 'Irish May Roselle Nao', '2023-0291', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6252, '2023-0208', 'paulyngrace.perez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6VIoiJXga8QiccyPIx6Ycu5iVLeYrwG3kkyxl2Xjhsse89PjjW04G', 'user', 'Paulyn Grace Perez', '2023-0208', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6254, '2023-0566', 'andreachel.rivera@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Lc6fkACEk3pyzFdrgWwlDeeKZzTA6Vz1pyzRCA0/zKaG0oDU3854.', 'user', 'Andrea Chel Rivera', '2023-0566', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6253, '2023-0181', 'shane.ramos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$rmugVsSgmQx7StYAga9ZxeO.owv5MR1uqAJtOJMEk1yScZYZKsgay', 'user', 'Shane Ramos', '2023-0181', 1, 'active', '2026-03-18 12:52:47', '2026-03-18 12:52:47', NULL),
(6249, '2023-0198', 'shiloh.manhic@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$fbARlgmHddpIiiVhq/TR8O9d4X27qQaZ8y9Gc3pVJMTFD1FmguZw2', 'user', 'Shiloh Manhic', '2023-0198', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6248, '2023-0331', 'geraldine.manalo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qSTrTIbJwnnrlmAY6I53D.7EqYZarrBEksw/JpK3Umt1CbSbjWqzm', 'user', 'Geraldine Manalo', '2023-0331', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6247, '2023-0216', 'cristine.manalo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$8XjBN4XEmmHNVhhL.6PbnOSRhwcQvsLKKcFfU1hHTwAzUGQHOy7xG', 'user', 'Cristine Manalo', '2023-0216', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6246, '2023-0156', 'irishkaryl.magcamit@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$RTWR6rimIwE4/3ihPbR9j.VQlzhDX1qe7MTMj9ZizOXRh76uPfRNa', 'user', 'Irish Karyl Magcamit', '2023-0156', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6245, '2023-0204', 'clarissa.feudo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$BbXA2dT5ro8qLd9Diie1iO.gnhtg0kdI4eOYSGQbRRhzHdkiAWlve', 'user', 'Clarissa Feudo', '2023-0204', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6244, '2023-0320', 'sherlyn.festin@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$rIWfMdHcnPFC8gpk7XPl4.dF/SRAGr5s5K.Pii69uk1ipfkPJABRW', 'user', 'Sherlyn Festin', '2023-0320', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6243, '2023-0154', 'bea.fajardo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$uumBs1s2d24On8laHIdIU.DPEWb6ku6qoKFtKkvM/jD9pz07jedFu', 'user', 'Bea Fajardo', '2023-0154', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6242, '2023-0155', 'kc.delaroca@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$MxSbqPaSThiiqLeQ46b0CeJ.V0vGH88U6MjrxopK87RzkT4oAAn4u', 'user', 'KC Dela Roca', '2023-0155', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6241, '2023-0270', 'hiedie.claus@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ZxQlFfdtiU8xEKsCkNJN3eEll0ZktYuD/f0cBiQSkJnMKoqzINIma', 'user', 'Hiedie Claus', '2023-0270', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6240, '2023-0219', 'jescamae.chavez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$QpP.XHqszaNMvbBeMlWGrOP2w05tS3ALaGJ4gmdu5qTwrQouJjpua', 'user', 'Jesca Mae Chavez', '2023-0219', 1, 'active', '2026-03-18 12:52:46', '2026-03-18 12:52:46', NULL),
(6237, '2023-0297', 'johnrick.ramos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$hkH4HpqsSqHnGayJRf19huTOYH9Y7yAKRMLQ3s2873u0utVaCsgLu', 'user', 'John Rick Ramos', '2023-0297', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6238, '2023-0220', 'rezlynjhoy.aguba@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6GlHBj6Tl7wLwqgdjj.BSu5WA2irpLi7NuyVM1UvoVhYbhI5ps/d.', 'user', 'Rezlyn Jhoy Aguba', '2023-0220', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6239, '2023-0153', 'lyzel.bool@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$MCv9SUD9/RyQjD..VxINa.CCH4q/k4mPorU6kf7gL.XWeZJBkNiU6', 'user', 'Lyzel Bool', '2023-0153', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6236, '2023-0182', 'bryan.peaescosa@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$koKkE9yxBIyeAiDebJYArO7iLRavJjFOGwXgOVbKea/9F4oGyZi7q', 'user', 'Bryan Peñaescosa', '2023-0182', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6235, '2023-0226', 'philip.garcia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$27fuHQ4IZVRDK8fZTeqVb.6SnCSJmxYDffpR34fH05xrUd09VI1oq', 'user', 'Philip Garcia', '2023-0226', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6233, '2023-0301', 'justin.como@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$4Zs7CvuHZcDrfXm.JicHfuCDX56IAogaTIqa6021JtwPeIdLlHZ.a', 'user', 'Justin Como', '2023-0301', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6234, '2023-0236', 'moises.delossantos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ZiVAwuTwUO0/RKKIFZToTely1A5AwRZrn/DD7cW8pVmE0zcTuZr1u', 'user', 'Moises Delos Santos', '2023-0236', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6232, '2023-0179', 'johncarlo.chiquito@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$DNhTtzqAYHkMmuwIPoKIYu9RuAg/ZhyHuUovU4L.lWMdHSpSbEklK', 'user', 'John Carlo Chiquito', '2023-0179', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6230, '2023-0277', 'johnlloyddavid.amido@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$KhoqpyYrWyxdoQTsgmBH0OLo7drgC2ImKJDrh4lq/0I5u4R6Q57te', 'user', 'John Lloyd David Amido', '2023-0277', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6231, '2023-0290', 'reniel.borja@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$oPVdJpXxPtpr.dkiKAaN3.hB90wSV0nZjz63HZiibW6ajN.5J5z96', 'user', 'Reniel Borja', '2023-0290', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6227, '2023-0341', 'jamaicarose.sarabia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$1gbRyRNsMOXT3MDGm7I0lOoQeO4WG50JqRlfoVEiPyTRS7poM0T0e', 'user', 'Jamaica Rose Sarabia', '2023-0341', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6228, '2023-0194', 'nicole.villafranca@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$hyHVyfO6WeE.71tUnrrnPOKQsbUmxryPcBzpoccYauDl0xK4E.xR.', 'user', 'Nicole Villafranca', '2023-0194', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6229, '2023-0203', 'jennylyn.villanueva@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$MyHi8bzrUbRSxQZvOj1Y8OXhamoc1MYzUHioVnsRBA/iOTe18hVT2', 'user', 'Jennylyn Villanueva', '2023-0203', 1, 'active', '2026-03-18 12:52:45', '2026-03-18 12:52:45', NULL),
(6226, '2023-0184', 'angeljoy.sanchez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$rRCJkzxP7G5bnoWg9eqX9OGNWdToMFpYTtp8WDPiX81spbMV21BU.', 'user', 'Angel Joy Sanchez', '2023-0184', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6224, '2023-0211', 'sharahmae.ojales@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$s5AozXyUBUK48mXrXX.NIeQj9F22fUjwSqQSRcDViaA9WlrDDfBAC', 'user', 'Sharah Mae Ojales', '2023-0211', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6225, '2023-0340', 'geselle.rivas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$90qEFq5K2VG9fQ7muSbV5uHA82KClzKI187d.SeSQIZWvV43yBNEi', 'user', 'Geselle Rivas', '2023-0340', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6223, '2023-0247', 'manilyn.narca@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$YmYP7sGxbT/F2hi/7zp29OmAdXUTW0W7ig/jFqH7bA2AFGAni7NXi', 'user', 'Manilyn Narca', '2023-0247', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6220, '2023-0191', 'mariaeliza.magsisi@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$hlrHtBuJ7Z81/dMRlkKOFeko15zMReAceQHs.S3UubEIV2KMFm3Vi', 'user', 'Maria Eliza Magsisi', '2023-0191', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6221, '2023-0227', 'carlajoy.matira@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$sBsQlpzDSIO.rfy2thOoG.dU4QHq.VkdPP/lW4tW.3SEislZ0MHfK', 'user', 'Carla Joy Matira', '2023-0227', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6222, '2023-0163', 'allysamae.mirasol@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$u/aIOgv97LDtnTe8hRlHn.UiSPKgQXTEyH02gqtLAvL9UsZQllGMW', 'user', 'Allysa Mae Mirasol', '2023-0163', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6218, '2023-0189', 'vanessanicole.latoga@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$i0bGnUi2lmux6jmTgz3WI.YJymmYb91KvyFRkc7p3wDsz/2kSqs.y', 'user', 'Vanessa Nicole Latoga', '2023-0189', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6219, '2023-0262', 'alwena.madrigal@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Vx8v43hAaOqU.x8LQvenR.8mZQzETBHqW9lkrrku9DN7PlIdw299.', 'user', 'Alwena Madrigal', '2023-0262', 1, 'active', '2026-03-18 12:52:44', '2026-03-18 12:52:44', NULL),
(6217, '2023-0197', 'mikaelam.hernandez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$xH/n5SnoSAQYRZz2vvQXq.s7Tb1dB/eSA0cACICs6OKCw9SKDTvbq', 'user', 'Mikaela M Hernandez', '2023-0197', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6216, '2023-0296', 'jasmine.gayao@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$wQiHwUIBSoVj/5Wu7VHdVeuFMq9l/8.guwPgAnInDoTjGVRTalSi2', 'user', 'Jasmine Gayao', '2023-0296', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6214, '2023-0287', 'ayessajhoy.gaba@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$TZq.MQesQDBMkmYHJ2y3DefMVuEZwyDbPzFdFOMz7yUb7/fVdyWyK', 'user', 'Ayessa Jhoy Gaba', '2023-0287', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6215, '2023-0193', 'margie.gatilo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ApKDHuehHVGWgkbKKcDbYODyGOiK/V8z0/tBSgkjWRfPYuA77Uzmy', 'user', 'Margie Gatilo', '2023-0193', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6213, '2023-0137', 'krisnahjoy.dorias@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$JiJvXaQD4OvYjzJt.XAyeOMlxGNqQZQsuZhYznVQ7qnpCS/MxwV4m', 'user', 'Krisnah Joy Dorias', '2023-0137', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6211, '2023-0257', 'rocelyn.delarosa@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$HWXJH8omKtzk/suF1XIdb.HTRdm99gVR2A.aesHySbUpKHAVzcxOK', 'user', 'Rocelyn Dela Rosa', '2023-0257', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6212, '2023-0256', 'ronalynpaulita.delarosa@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ETWdMlO1TUF45stXyT9ZmeZXrtfkwMRcLcrRKVRK86PRhB2/DKyHm', 'user', 'Ronalyn Paulita Dela Rosa', '2023-0256', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6209, '2023-0266', 'angelann.delara@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$f3a5HkT5429FlJyDEjDmw.XeDAGxN4XsWwF4/.Lg03Fj36NOptsxi', 'user', 'Angel Ann De Lara', '2023-0266', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6210, '2023-0172', 'lorebel.deleon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$fng8tqiGNiuBfpR2LrP8KOKSPDLgCo7vDrnj7ga3EWRARaADA7Boa', 'user', 'Lorebel De Leon', '2023-0172', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6207, '2023-0185', 'stacyanne.cortez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ptA3mVRQYxU5MRGdelkPK.MTt865WnE2IrCfAMKM5ckExHyokfN9a', 'user', 'Stacy Anne Cortez', '2023-0185', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6205, '2023-0272', 'christinerose.catapang@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$1FqUmT15yz1bUpNaCsNyPeA/1SGdQ9s0vFM.YsjlMCsdJTrwG8IA6', 'user', 'Christine Rose Catapang', '2023-0272', 1, 'active', '2026-03-18 12:52:42', '2026-03-18 12:52:42', NULL),
(6208, '2023-0199', '.declaroalexajanec@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$H1CUXkMmi.gdgwbTbl5/LuD5HvILP7560GOsu2hOBjDiHCvMhMhZS', 'user', ' De Claro Alexa Jane C.', '2023-0199', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6206, '2023-0192', 'arlyn.corona@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$4f4mFwybVshUujOflhFmJeH0O/rCOjjNKSXaI7pY.1AOkEXIeVZjm', 'user', 'Arlyn Corona', '2023-0192', 1, 'active', '2026-03-18 12:52:43', '2026-03-18 12:52:43', NULL),
(6203, '2023-0253', 'princes.capote@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$.xxnzI6NIByaatvwhKHr6O2yN/0kg8mkwmLNUC8Jb/BikKfOOli8i', 'user', 'Princes Capote', '2023-0253', 1, 'active', '2026-03-18 12:52:42', '2026-03-18 12:52:42', NULL),
(6204, '2023-0228', 'joann.carandan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$svTHFnFJtWTWbkd439Z3u.sWLyhMnKV2S5xi1VSs.59U51uv0rpta', 'user', 'Joann Carandan', '2023-0228', 1, 'active', '2026-03-18 12:52:42', '2026-03-18 12:52:42', NULL),
(6198, '2023-0295', 'king.saranillo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zLEOQPS72ZKRJ4SNMnBp2.7aK1Y6q/4toaIoWRV1NZ7guKq4a5qyO', 'user', 'King Saranillo', '2023-0295', 1, 'active', '2026-03-18 12:52:42', '2026-03-18 12:52:42', NULL),
(6199, '2023-0260', 'jhonlaurence.victoriano@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$1/oLAmv0yU7ZkGemf6cD5.cI7QMikeqg09euTSYu9t8lo2l4XdY8G', 'user', 'Jhon Laurence Victoriano', '2023-0260', 1, 'active', '2026-03-18 12:52:42', '2026-03-18 12:52:42', NULL),
(6202, '2023-0202', 'robelyn.bonado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$juOG0exx6Kx953F56vMOFuzOodFSAj5hXOv5woSprQJJNrcbDNeYm', 'user', 'Robelyn Bonado', '2023-0202', 1, 'active', '2026-03-18 12:52:42', '2026-03-18 12:52:42', NULL),
(6201, '2023-0188', 'janashley.bonado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$gP629aRqnUoCzrv7uacd..wQYHuH8v3yh/2yQrdF/cMb1id9YLMc6', 'user', 'Jan Ashley Bonado', '2023-0188', 1, 'active', '2026-03-18 12:52:42', '2026-03-18 12:52:42', NULL),
(6200, '2023-0210', 'janelle.absin@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$2Qjs1xN37WoM9XlatBm7ae3W20L33Zt/VXD1CaLmJ4eh/E1cVuwcu', 'user', 'Janelle Absin', '2023-0210', 1, 'active', '2026-03-18 12:52:42', '2026-03-18 12:52:42', NULL),
(6197, '2023-0300', 'johncarl.pedragoza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$PF6fyMdROOCEuFfkinJA.eBz7qZkxUvyruH/FX9gk6LPv/AFwqep.', 'user', 'John Carl Pedragoza', '2023-0300', 1, 'active', '2026-03-18 12:52:42', '2026-03-18 12:52:42', NULL),
(6194, '2023-0213', 'jerome.mauro@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$aJt4Wwxw1F33vZWTFljII.eLfX0FsbPdpL72LGI7v/Zb7mDlvQF92', 'user', 'Jerome Mauro', '2023-0213', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6195, '2023-0279', 'jundell.morales@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$TRRyOiVrjv9fZaHmWXnvA.ps6q2pj5q59IL9OHDRaC3W3jUIN56Eq', 'user', 'Jundell Morales', '2023-0279', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6196, '2023-0171', 'adrian.pampilo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$10viu19VLDLajUVzeOWhneCwl78fVXsexmah3qkKADRhAK3vLED8G', 'user', 'Adrian Pampilo', '2023-0171', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6192, '2023-0223', 'jeshlerclifford.gervacio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$UY7E8hYh77SQaJOI05lcOect1qhJSIYU.RtD0z7T7UdvNxlS2xbw2', 'user', 'Jeshler Clifford Gervacio', '2023-0223', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6193, '2023-0333', 'melvicjohn.magsino@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$0tB2FFSOCk6OjaMrmuA42ucBxyg9dRYWGk4jBk2JKIsNzjKcDjZT.', 'user', 'Melvic John Magsino', '2023-0333', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6190, '2023-0159', 'johnpaul.freyra@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$EHMbRO59HQRQDDGMDG/PLOZfJFF4Lzs0i88UFVx30hz37l.5J3Odu', 'user', 'John Paul Freyra', '2023-0159', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6191, '2023-0258', 'ryan.garcia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$lqNmq49qHCse65yKFeaDxeQQusr5UVWX8rDsFuDfgNW7IzzBkkjaa', 'user', 'Ryan Garcia', '2023-0258', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6186, '2023-0209', 'johnrussel.bolaos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Y2wGtA4g97IW4IgpSu6TVeUwTEyatDvPk/WmfKFZedSVplTRDRjp6', 'user', 'John Russel Bolaños', '2023-0209', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6187, '2023-0166', 'justinejames.delacruz@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$klleQlnUxQruUkjDkt6kcuCOOILv5Z3spSKgJTqJeHkbXX9LjuCoC', 'user', 'Justine James Dela Cruz', '2023-0166', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6189, '2023-0274', 'monlester.faner@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qgt1XMt5uzL9ZZEFVnkxgezySxOyh0OV5KC2FEJLldI9aLdj0UAbK', 'user', 'Mon Lester Faner', '2023-0274', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6188, '2023-0313', 'carljohn.evangelista@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$LGhHtaZLWIdVD9CdSeOyE.wngkFRy7jc50dgY1dH3fnyx3W0O.lq2', 'user', 'Carl John Evangelista', '2023-0313', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6185, '2023-0261', 'johnmark.balmes@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$M9VYB.AapQVHNWju7gLM5O5DLwJIqZVjEC.HY5bpJX1nq/NwbD4kO', 'user', 'John Mark Balmes', '2023-0261', 1, 'active', '2026-03-18 12:52:41', '2026-03-18 12:52:41', NULL),
(6184, '2023-0284', 'monandrei.bae@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$MadihgUNyRtmPJD0DLTtfeKLWzAhIntzPqwSKBIF8ERpblgFs91.2', 'user', 'Mon Andrei Bae', '2023-0284', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6183, '2023-0150', 'ralfjenvher.atienza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$gKmH74vzlTvAKPOQWH9r6ez2m0yEzzTFB2seSNsgOOUGVzlTaz466', 'user', 'Ralf Jenvher Atienza', '2023-0150', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6182, '2023-0309', 'jordan.abeleda@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$NnXNEB3bsrtTGdBbBu6mTOOyyLEvCePmiSw0przfVsncIFaj0Eis6', 'user', 'Jordan Abeleda', '2023-0309', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6180, '2023-0240', 'jenny.fajardo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$RxNvQPMP5Ar3e6FGWXmFGOtXCCYw/3kyc52W7onaWpalzi7iYS3Te', 'user', 'Jenny Fajardo', '2023-0240', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6181, '2023-0299', 'maryjoy.sim@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$DSGavQyBLPF6UHubpv3saOdXWGLo7J9K1DVT3r.gc.rKSZ/d.sLTS', 'user', 'Mary Joy Sim', '2023-0299', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6178, '2023-0322', 'joel.villena@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$LW9ynnK45MGQ68fIAHwtf.32PVjxDM1fAZrfEq.3ic9rg1FWUVV/G', 'user', 'Joel Villena', '2023-0322', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6179, '2023-0248', 'jazzleirish.cudiamat@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$p79rYHASeCQMpgEKdpY3QuCrl8A1FNldE6hdUfC3jHoMfRwOU.ING', 'user', 'Jazzle Irish Cudiamat', '2023-0248', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6177, '2023-0255', 'jayson.ramos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$EoZeTBxJpdA60OO7KCb0ouYRgdanh3RrqpT854XylOInpf3Nt1CF6', 'user', 'Jayson Ramos', '2023-0255', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6176, '2023-0308', 'johnkhim.moreno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$i6ERaiUIeH4aoyO/ZCu7/.750kL3dwyUOvMxVEXCzI3Wb8jdg27Zm', 'user', 'John Khim Moreno', '2023-0308', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6175, '2023-0332', 'michael.magat@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$riTfnrI9q9ZbGHMUgj/FFe6xdVMACZociqmi9u2MqBh..7XwVktZu', 'user', 'Michael Magat', '2023-0332', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6174, '2023-0243', 'kianvash.gale@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Yj23q3msiUHyAOdbf3iSZuyIpEMBBMn67uk57YQbbbNeqowOV9eL.', 'user', 'Kian Vash Gale', '2023-0243', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6173, '2023-0249', 'marklyndon.fransisco@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$j9PCUpRPa1KeS77NZFvsZ.jquTmbTTIGPXNTanUjlm80xejPLWe3u', 'user', 'Mark Lyndon Fransisco', '2023-0249', 1, 'active', '2026-03-18 12:52:40', '2026-03-18 12:52:40', NULL),
(6170, '2023-0239', 'adrian.dilao@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$3PgHSFrQxpXnDiA.bYTiwOruc8jIrSC5I43S4xCulWQWsKSg0Zj9K', 'user', 'Adrian Dilao', '2023-0239', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6171, '2023-0167', 'mclowell.fabellon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Cmj47YE4mUjozw0LoOORzOeTL83xNQRcqkGGGD0Ayj6lR.Mj/g4oC', 'user', 'Mc Lowell Fabellon', '2023-0167', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6168, '2023-0233', 'jorizcezar.collado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$4ocC63s2iRL3oGJyoNXuc.JhciX4BwehJV2gE9Lx/VJvji1ONmhxK', 'user', 'Joriz Cezar Collado', '2023-0233', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6172, '2023-0177', 'johnpaul.fernandez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$mkU4MoFBk/xHHttNCWRO.eGmIcbcC.L0EtWhzsXo8U7eCqeRnA2ZG', 'user', 'John Paul Fernandez', '2023-0177', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6169, '2023-1080', 'marklee.dalay@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$vkzB3EhtgCqBEcJAFK0.dOj4i77BwQLlAWXSfNIRJhGrH/OTAKmG6', 'user', 'Mark Lee Dalay', '2023-1080', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6167, '2023-0345', 'sherwin.calibot@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$HMxOgl2sPa/DvLkeBlaVu.pFGh6/KAv4A.BKXL6s9Bm6v5nrbB8yG', 'user', 'Sherwin Calibot', '2023-0345', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6166, '2023-0336', 'jhonjerald.acojedo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$sPhMxyXWdEOnQzQ8Vr1cguSjF1m9Dw261cGDXVbhqqBLaNY33t1ca', 'user', 'Jhon Jerald Acojedo', '2023-0336', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6165, '2023-0241', 'lyn.velasquez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zA5hJxmCpEDKvRERs8c4Z.11d28Z2m1FXPEqzx0vsKNVUMG.uLltm', 'user', 'Lyn Velasquez', '2023-0241', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6161, '2023-0225', 'genesismae.mendoza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Aqv/5AV0MWO4Wig9NKpnYOwk5PI3Zivhy6cp0jeWX8bfN7rBYCqWa', 'user', 'Genesis Mae Mendoza', '2023-0225', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6162, '2023-0224', 'marian.mendoza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$2r7DvZLrLoTyydPOoVcURe2MOv64rs.uTu4168fotyU1oEn5HozaS', 'user', 'Marian Mendoza', '2023-0224', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6164, '2023-0303', 'kyla.rucio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$pvs/kuONUansKGDKEkjmtOXL/fwe/.Knq3geSu5rOGKP1fHGe74o6', 'user', 'Kyla Rucio', '2023-0303', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6163, '2023-0173', 'lailin.obando@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$4oR/s833SWPKLYoz8s2.C.6cJTP23Pm5eUI3uP2RyaU7dhzdsT7W6', 'user', 'Lailin Obando', '2023-0173', 1, 'active', '2026-03-18 12:52:39', '2026-03-18 12:52:39', NULL),
(6160, '2023-0285', 'maan.masangkay@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Q5UZScKkUZBg1ioKfgObX.QP/W2fU27dci4P3Y6FDCSVV6jBFogF.', 'user', 'Maan Masangkay', '2023-0285', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6159, '2023-0215', 'judyann.madrigal@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ICYtONYVP0kAiOgRm2tBZ.WRyAEQcyZf4GmpKEdf034AwCZ3om4Sy', 'user', 'Judy Ann Madrigal', '2023-0215', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6156, '2023-0251', 'angielene.landicho@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$osoWg8V/ZmkM1xpFH6MpiOSTZkfnOyRub9v3tWjrMzXi6U3g2GTku', 'user', 'Angielene Landicho', '2023-0251', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6157, '2023-0298', 'laila.limun@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$S93Ej.XyzK27pPYKFZCAdeAwOWcudgFuxychuNCh8wykd4XPGOEdK', 'user', 'Laila Limun', '2023-0298', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6158, '2023-0244', 'jennievee.lopez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$CqLbNdYEpsj9PTGYyTCQVuL7BamLLyv8.Hoe9xmdTdzZtRpY29l6u', 'user', 'Jennie Vee Lopez', '2023-0244', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6155, '2023-0200', 'zyra.gutierrez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$29YJwFm0J/LVp5Sver98suhnKRrvDrc4UALX1nLx3u4o2cuW6NWIO', 'user', 'Zyra Gutierrez', '2023-0200', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6153, '2023-0161', 'babyanhmarie.godoy@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$9fD0fb.LWnid8U/LhwT01OiBU4usJ/hjyNqLrrVy597E8ijtGVdGm', 'user', 'Baby Anh Marie Godoy', '2023-0161', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6154, '2023-0169', 'herjane.gozar@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$GgsVqn0JTLAIh6PqjpVRkuZuN5EsuV/YFzCKleHQo8FLqH0z/Ru6S', 'user', 'Herjane Gozar', '2023-0169', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6152, '2023-0317', 'jeanlyn.garcia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$689lbCRdvtic1y4fcG5Ojey40pE.ewIpY/kcEGczuxXuf7AzKh0Vu', 'user', 'Jeanlyn Garcia', '2023-0317', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6151, '2023-0305', 'jaimeelizabeth.evora@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$UEq.YDu4yvLSSyrLnnpJIuVkhSaNBethJga7.qeze57hOibcOW8Ae', 'user', 'Jaime Elizabeth Evora', '2023-0305', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6148, '2023-0327', 'juvylyn.basa@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$vByTYgmlmjBXLjflxVy25OxcQsPrqxIIDuRqDBuC3le.lh5UrTpyy', 'user', 'Juvylyn Basa', '2023-0327', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6149, '2022-0088', 'rashele.delgaco@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zhdlMrL7Oy3GO5J9BrfBWObMZwP4meZ4VXgf0YU.8mehFKgsq10ni', 'user', 'Rashele Delgaco', '2022-0088', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6150, '2023-0288', 'cristaljean.dechusa@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$btV0y1JXdCgLGEs9hAHE5OZCwgcmoMQoQH6FZFn0O9QGJceXcVgPK', 'user', 'Cristal Jean De Chusa', '2023-0288', 1, 'active', '2026-03-18 12:52:38', '2026-03-18 12:52:38', NULL),
(6146, '2023-0304', 'jonahrhyza.anyayahan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$eDBxxrcy8Iik4efjLQbvBukq7VvnVq83yKrXNQaA.QqOroZytSulm', 'user', 'Jonah Rhyza Anyayahan', '2023-0304', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6147, '2023-0337', 'leica.banila@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$dzBUkiL4zWikOANjhUXIl.iM2Iv7OxcQVGieYeM4SvRDB0/RETwju', 'user', 'Leica Banila', '2023-0337', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6144, '2024-0406', 'gerson.urdanza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$pn8VkGVBarQmUAZ./tTsA.kSOexNmxe.cNXDEuAHI9Okm80zmks22', 'user', 'Gerson Urdanza', '2024-0406', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6145, '2024-0397', 'jyrus.ylagan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Cv/pgaWY0hAc4JAqbDDLze.uuXpxLwEMLHF8wiaCt76lYwzNrZMdC', 'user', 'Jyrus Ylagan', '2024-0397', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6143, '2024-0408', 'jerus.savariz@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$EBi34CWYDKF4K39jkUAVBeIlVWMr.uQdPGCDybrHw/VTUrLXMNTsy', 'user', 'Jerus Savariz', '2024-0408', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6142, '2024-0423', 'benjaminjr.sarvida@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WHzB8l/Vaj5xtO6PS/fEGeI8ievh.FSlKS7Y/Czf23UEtLZ6/QLWS', 'user', 'Benjamin Jr. Sarvida', '2024-0423', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6141, '2024-0580', 'merwin.santos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$1nRqkr9Wfor35RRscXqKTOYDbcwaAhOUk8m91Ma8vNkND63ypeULa', 'user', 'Merwin Santos', '2024-0580', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6139, '2024-0436', 'jhezreel.pastorfide@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6aI/3i/oBYvG.DSWpyUYIe9GwEJsBPBHOJEhInqYC0UTIvdkbiW/e', 'user', 'Jhezreel Pastorfide', '2024-0436', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6140, '2024-0578', 'mattraphael.reyes@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$LU6zvu4h6lctiWnu6PJb9eRmyr3QIavI5uls9yjFJc1XFtRVq.KNm', 'user', 'Matt Raphael Reyes', '2024-0578', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6137, '2024-0393', 'amielgeronne.pantua@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$4tl2HqIB8r18W4l9mRyziOXznkxXRXVSwSUyrdziRnc/oongbsnDC', 'user', 'Amiel Geronne Pantua', '2024-0393', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6138, '2024-0392', 'jameslorence.paradijas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$XVvtL9pl3tNT6qG.um7HSup4PotnFOkXk773qiE20CYSoZVb0kJpi', 'user', 'James Lorence Paradijas', '2024-0392', 1, 'active', '2026-03-18 12:52:37', '2026-03-18 12:52:37', NULL),
(6136, '2024-0428', 'christian.moreno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$.CHBsSd/IXWiXJQGz2Xe8OqjUiVbGXeAwt4v9YAbIRlP9Ek1qFzQK', 'user', 'Christian Moreno', '2024-0428', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6134, '2024-0394', 'carlo.mondragon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Fbz3hHc/FPTa5xB4XUc5Eujy8NEKkEB4l/6yDjsA6YPCIqH1.HZMi', 'user', 'Carlo Mondragon', '2024-0394', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL);
INSERT INTO `users` (`id`, `username`, `email`, `google_id`, `facebook_id`, `profile_picture`, `password`, `role`, `full_name`, `student_id`, `is_active`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(6135, '2024-0410', 'johnrexcel.montianto@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$UJ4CF9M09mWQwmK/Ar09DeDa1HOb7Sl317gf83LwjKXYSv3rHrtfy', 'user', 'John Rexcel Montianto', '2024-0410', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6132, '2023-0465', 'patrick.matanguihan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zwvUZvglR40tNHTFEREoT./aFTStOfbcSh1vWEdUgtscweMGlYslO', 'user', 'Patrick Matanguihan', '2023-0465', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6133, '2024-0478', 'dranzel.miranda@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Ra/Q6XQ4qZkE9rJ/wjjvyuW4KLLUKDXh4NC5wu48j/TAnbXl8oxui', 'user', 'Dranzel Miranda', '2024-0478', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6130, '2023-0151', 'ramcil.macapuno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ESmXhUz.2ugYhL22.DwWwOLHZ8neqlIUwuF/c52x9.MO6i6oScU2.', 'user', 'Ramcil Macapuno', '2023-0151', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6131, '2024-0395', 'florence.macalelong@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Oc6rwMtne/DzGjhvyo7Z0efNwv1XzVWPxp/zPKH8t25ZQaIqSnZ5m', 'user', 'Florence Macalelong', '2024-0395', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6129, '2024-0420', 'miklo.lumanglas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$se36HSYzxAzSY0zsqQBHGOgNMrn6o814jgZDOmSwDL/V8Y4KCK1FO', 'user', 'Miklo Lumanglas', '2024-0420', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6128, '2024-0517', 'gino.genabe@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$g5.SOCgo7tUp43f3yLA6GuoRw4cZAytLrQuJIWvKHmfvdGPYZjbyq', 'user', 'Gino Genabe', '2024-0517', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6126, '2023-0447', 'charlesdarwin.dimailig@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$lMxEDCoHZJI4zmfxSeNuiOhT9x.GEeQZ4I.VpRb/nGcy6NeF6bxfC', 'user', 'Charles Darwin Dimailig', '2023-0447', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6127, '2024-0413', 'airon.evangelista@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$r2CYdxfGFwtvg79xETfl0.a0vKHINVEH3qJYIjPcO9ORe3c3nc/Z6', 'user', 'Airon Evangelista', '2024-0413', 1, 'active', '2026-03-18 12:52:36', '2026-03-18 12:52:36', NULL),
(6123, '2024-0572', 'markjayson.bunag@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6rzlqZ/cJkCQzQv4gEB9..CP9LZCCEQpQI/7GkEIiO9HCMtvXd9ue', 'user', 'Mark Jayson Bunag', '2024-0572', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6124, '2024-0561', 'alvin.corona@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6foPIi1HoZba557CKerB2eBK1YJk9b0tRJpjdtMrbmUDZniz1XZrm', 'user', 'Alvin Corona', '2024-0561', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6121, '2024-0043', 'johnkennethjoseph.balansag@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$e8IhEJzJzYjywAceFQCTde11lIrSrcg5HtbmVqF63KWjs9v9vreXO', 'user', 'John Kenneth Joseph Balansag', '2024-0043', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6125, '2023-0407', 'markjanssen.cueto@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WcI/I1/jt56SBJINIUCA0u43X5.AwhTy2ukCnrdEbzVI9EwVAbqWW', 'user', 'Mark Janssen Cueto', '2023-0407', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6122, '2024-0398', 'raphael.bugayong@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$iACKnEf/j05LZn8OskcYm./4nN.Cl7t.kglnAHwkZnFE2.IALaLI.', 'user', 'Raphael Bugayong', '2024-0398', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6120, '2023-0519', 'johnmichael.bacsa@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$D/7dHPrzMW1etlO1N7v2XupmeAmKtPB/Ilm2mY44QW7WEPX7s52Wy', 'user', 'John Michael Bacsa', '2023-0519', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6119, '2023-0433', 'romelyn.rocha@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$W8jJrMej3DhJPEeuldzZyufK3ycmA/5Mm6c/T8zbotTq4uXLqg3Ka', 'user', 'Romelyn Rocha', '2023-0433', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6113, '2024-0412', 'gracecell.manibo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$CIxfsPJjRV6Tgn6IOxvgCuWP.eTUeVO28qZBUC.lGpRqbVYGGVOOu', 'user', 'Grace Cell Manibo', '2024-0412', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6114, '2024-0571', 'lovelyn.marcos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$sM38Ut9dIk8p5riyjZblK.ShrPrBnw4IwV7cLm3tK28Cp8z9A.Lgq', 'user', 'Lovelyn Marcos', '2024-0571', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6115, '2024-0314', 'shennamarie.obando@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$tScLL7topnOKvaK54YeN6OA8uOHJUNKK0qgRAXnqbuIAGiVlRT9dK', 'user', 'Shenna Marie Obando', '2024-0314', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6118, '2024-0426', 'desiree.raymundo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$VbIfHSMWMsFNbehGhVtLFOjIH06X1xwyqGiIv14XX1C6d6sw.uiQC', 'user', 'Desiree Raymundo', '2024-0426', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6117, '2024-0582', 'shellamae.ramos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$fmko1Lwk2I6s/32m4R9fAOcuyTzPUu5zmgOSXWlHEc0WfgotiYAZ2', 'user', 'Shella Mae Ramos', '2024-0582', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6116, '2024-0348', 'myzell.ramos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$P.fJKWb43OJuW6hfBtH7kOUK4IjarASv2V.vkTty5HbMNxC.LNPhW', 'user', 'Myzell Ramos', '2024-0348', 1, 'active', '2026-03-18 12:52:35', '2026-03-18 12:52:35', NULL),
(6112, '2024-0472', 'keyceljoy.manalo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$SEUg0oByGD0VHdO8V2Ubhe6d3hSSG3k0gDbNuqar62rsupdRd4dRa', 'user', 'Keycel Joy Manalo', '2024-0472', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6110, '2024-0544', 'ariane.magboo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6C5y6bgtEKm3TG9IWWjDXuWxqcIGcy8IvC3GXqNcFfW.aJ2jJ81HG', 'user', 'Ariane Magboo', '2024-0544', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6111, '2024-0415', 'nerissa.magsisi@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$o31nI7gFr79Hf0/GmKuC2e8GyPmrbHA.V009OIO8iMZmajLLvYVwy', 'user', 'Nerissa Magsisi', '2024-0415', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6107, '2024-0422', 'jayann.jamilla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$tDmpi7iaidUP1YW/wugBzOaqjnwc41sF5hrpMdb0vYUamDs.WP83.', 'user', 'Jay-Ann Jamilla', '2024-0422', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6108, '2024-0416', 'mikaelajoy.layson@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$93.udOoO7AZv6omeVEgrDutRuWxDWhLINrFS8UA9U494JteQGmSc6', 'user', 'Mikaela Joy Layson', '2024-0416', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6109, '2024-0427', 'christinejoy.lomio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$eut2U9SJpeumXcOg4GM20O.D6Cgj/fWJJOjRrou5YyrxKz9isI34q', 'user', 'Christine Joy Lomio', '2024-0427', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6104, '2024-0417', 'nesvita.dorias@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$akytzy1HqEFpzejOo4DwyuvsEoD.5Ii0eY2FLfDLU6Mh.k3b4TpAi', 'user', 'Nesvita Dorias', '2024-0417', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6106, '2024-0567', 'arlene.gaba@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$E0lbDgYQxLMZ06uxnpwDWeqKzdBmQzaBtWKsULxvRuAslvZMK3Ma6', 'user', 'Arlene Gaba', '2024-0567', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6105, '2024-0432', 'stellarey.flores@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ApdP33NNt21jcgq.uX1JWukfeVwrGoCwgCaKO87gYjZ.CfUqltpTi', 'user', 'Stella Rey Flores', '2024-0432', 1, 'active', '2026-03-18 12:52:34', '2026-03-18 12:52:34', NULL),
(6103, '2024-0404', 'marina.deluzon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$e4PjLIo8S0r4khjJY6LRyOMRBnhRPEW2jw/eEpjXJrSu6uJ0kfVt6', 'user', 'Marina De Luzon', '2024-0404', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6102, '2024-0343', 'preciouscindy.deguzman@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$hcPQAaa9CMQ3XaGbygVtTOUARCmu8QpKyAgd93kyDTlAROHD2cRru', 'user', 'Precious Cindy De Guzman', '2024-0343', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6101, '2024-0437', 'arjeanjoy.decastro@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$UEn22j/T7vmWnrWGgJVfpeAyBjbwSx1Yujyv8Ek/VfUeq4Wu89rRG', 'user', 'Arjean Joy De Castro', '2024-0437', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6100, '2024-0342', 'charlaine.debelen@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$pKBNo94wqr8yj7bnRLTo/e4ZS3Qt5kVkPpx5g4k5VZzdDoaiqIai6', 'user', 'Charlaine De Belen', '2024-0342', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6099, '2024-0424', 'princesshazel.cabasi@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$wT.obBlJjx6QZzPBfblGOek1rvH3Gr1/zsTyaRgZDFaOFe4xoZnXi', 'user', 'Princess Hazel Cabasi', '2024-0424', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6097, '2024-0411', 'precious.apil@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$eldBvAL6I6cRt84eDWOlzukpL8f47isgfbH.pF86ab5wXsoN0PsKG', 'user', 'Precious Apil', '2024-0411', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6098, '2024-0418', 'ludelyn.belbes@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$2uCMuVsDNt9NzurTE0uvu.0CQWDwRHzLcuJ3VlCl6ebj7.WB08H7y', 'user', 'Ludelyn Belbes', '2024-0418', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6095, '2024-0438', 'melsan.aday@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$TqRDWkLIJG4JqMAV0AceCuXXkuT4h8LibqhgvYuH343x9Z3DU1oN6', 'user', 'Melsan Aday', '2024-0438', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6096, '2024-0405', 'jonice.alturas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$1Cpr9LVJIbCieY8FqcLs4uDyvkREUxO5HvtWqeprlEZeH2.F70G2G', 'user', 'Jonice Alturas', '2024-0405', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6094, '2025-0816', 'marshalhee.azucena@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$xNfEC/6ALABVPyXBINtjFOvX5iZfaNRofaTyvqYznkw5FuXJgrAFq', 'user', 'Marsha Lhee Azucena', '2025-0816', 1, 'active', '2026-03-18 12:52:33', '2026-03-18 12:52:33', NULL),
(6093, '2024-0492', 'djay.teriompo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Zox/gENwM.sBo0oUgzkX3erI8YN38Y5OXJijO.O077oCV6cfggXGu', 'user', 'D-Jay Teriompo', '2024-0492', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6092, '2024-0523', 'ronald.taada@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$7N9pcwfL2t7A6ZAOWuuQs.n2jwtzTnJUSlYINy02bnxV.80Es/9P2', 'user', 'Ronald Tañada', '2024-0523', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6090, '2024-0386', 'aj.masangkay@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$gVLeh/V4iiCJjK53UkBEHuv0Soc4SWiLnNTZDLQhpWcsMld5Fph4e', 'user', 'AJ Masangkay', '2024-0386', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6091, '2024-0480', 'johnpaul.roldan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$9LAeXPxPmI1vBYa33kKHfOaRZUb4Vu2dtV6YnQQIH/ht8ba7pbl2.', 'user', 'John Paul Roldan', '2024-0480', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6088, '2024-0389', 'alex.magsisi@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6HhduRr39C4xYYJ6yP30/eEMRbQBzYt/Ycfme42v4h6jAaky.qQvi', 'user', 'Alex Magsisi', '2024-0389', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6089, '2024-0525', 'jancarlo.manalo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$HF8WvlBS2NEFt9jW7jn9kOyEzAUDxTEqTSSBmpBJyWxfaZyXzq4nq', 'user', 'Jan Carlo Manalo', '2024-0525', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6087, '2024-0557', 'denniel.delossantos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$g2d1asjIEamKbuTOPcpCJuwXjEQ8hnh/0xq7F1cxAfIrrfolaVS4.', 'user', 'Denniel Delos Santos', '2024-0557', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6085, '2024-0365', 'lany.ylagan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$SW0RLbH5lkxMm.nMOgku4OzeDtd13vNsaXOONXwpsLVaHD.CZPtNW', 'user', 'Lany Ylagan', '2024-0365', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6086, '2024-0373', 'marvin.caraig@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Nqq2Rxvq.wngpiSevRqXo.SVrx7dLBLnyfHc46fQmr52Pt45H7q/C', 'user', 'Marvin Caraig', '2024-0373', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6084, '2024-0356', 'lesleyann.villanueva@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$.sou.pF0ZwOeqtRhPyzgc.5jUIN6VSwFuevt6fkLqZ6aX.ZEhBt..', 'user', 'Lesley Ann Villanueva', '2024-0356', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6083, '2024-0556', 'jolie.tugmin@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$OKu9twhS/pCrtSI4ZbD1RujUhRbtchMcj0vDrV7u4aLOWZqJ8eKxq', 'user', 'Jolie Tugmin', '2024-0556', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6082, '2024-0453', 'cynthia.torres@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$i/pM4rkYPMcxkQrhLLwXEOKwPIbwbE0ilTwBpuFhJOP15o90aO0aO', 'user', 'Cynthia Torres', '2024-0453', 1, 'active', '2026-03-18 12:52:32', '2026-03-18 12:52:32', NULL),
(6081, '2024-0451', 'maryjoy.sara@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$rhGiZoazkPrWl2/jVcPDPOAijiusd9zzDvt2bj1ShrkgKaZNriyEO', 'user', 'Mary Joy Sara', '2024-0451', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6080, '2024-0509', 'edceljane.santillan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$dv1NMR7i7cKcqWSNMToyqeosNhc18lyWX7sDcZJeb.y1vPmmfx5tu', 'user', 'Edcel Jane Santillan', '2024-0509', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6077, '2024-0380', 'jeyzelle.rellora@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$RV0zhgjeyuUHTQkCsnYnUe2fBYMAa3dOar2uvQwMi1oFq15J0cWrC', 'user', 'Jeyzelle Rellora', '2024-0380', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6078, '2024-0264', 'katrinat.rufino@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$kXH4hmS/J923VzAsmk2J8.ty/oIl3x0F0kAuF7vo6nDKz5dmce4zu', 'user', 'Katrina T Rufino', '2024-0264', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6079, '2024-0382', 'niazyrene.sanchez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$c/VOYXOeXbArjC/v60eILOgcD98SXxBbwSFo7Q.lKLD/W9Evs54k6', 'user', 'Niña Zyrene Sanchez', '2024-0382', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6075, '2024-0568', 'angela.papasin@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$oRWPtsUn.Itp52xBdIyaJO74CqjLo5.sK3H9WgVafPjluDiKZQxoK', 'user', 'Angela Papasin', '2024-0568', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6076, '2024-0359', 'jasmine.prangue@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$lteDpK.MWrFNQxUzGUBtTerV7teg9D9hOLHbNNpaAAL4/1u6dwsTW', 'user', 'Jasmine Prangue', '2024-0359', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6074, '2024-0350', 'hazelann.panganiban@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$9mnzVP9dlrolhtpAD2XEwOSENrUG5fy5WUO327hrnUgEi6Y5w.4BO', 'user', 'Hazel Ann Panganiban', '2024-0350', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6073, '2024-0384', 'margie.nuez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$3Rn/gxCIQ.yY2xxWKx/wLO0v6vJUvRgg1wUjgVo4iZUX2HzOHHdrC', 'user', 'Margie Nuñez', '2024-0384', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6072, '2024-0377', 'cheresegelyn.nao@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$j/QvoVXTc23JP5.AMlWrgOPf3/FmCRca6uw8Rgd4hutkOT9547H8e', 'user', 'Cherese Gelyn Nao', '2024-0377', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6069, '2024-0587', 'hannah.melgar@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$vMwmtbafawF3mf2JFje1rOPYThKHAtM/IefrTMXwxKIyyoYdIBFJe', 'user', 'Hannah Melgar', '2024-0587', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6070, '2024-0586', 'rexymae.mingo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$V30YrYgO0YUYRrXkTNRNnuzGD8GEiLm/S1YVkiHne2KmkdTcYbiZu', 'user', 'Rexy Mae Mingo', '2024-0586', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6071, '2024-0349', 'preciousnicole.moya@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zvQMceqH9OBTuKTKjXcBMu3CyDKecRWhiiLd3o2IK2nISt.SHnAsy', 'user', 'Precious Nicole Moya', '2024-0349', 1, 'active', '2026-03-18 12:52:31', '2026-03-18 12:52:31', NULL),
(6067, '2024-0391', 'kriselleann.mabuti@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$g0yAF8AAECLff.Gfr/KlQOaLWeoDahDM4jm7teyE57NMv2nl3etVy', 'user', 'Kriselle Ann Mabuti', '2024-0391', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6068, '2024-0387', 'angelrose.mascarinas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$RqUm87OepV7oRoHrqgrY5eLoRtUGQg6kdibTdeyqJ6is/B/YAJ41a', 'user', 'Angel Rose Mascarinas', '2024-0387', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6066, '2024-0368', 'joankate.lomio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$UgPO7tAS06oDuFk2O4lz5.ibKhZJaxHQA0DONdBxl9IqWZ2DmIGQG', 'user', 'Joan Kate Lomio', '2024-0368', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6064, '2024-0501', 'eslleyann.hernandez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$0QSRUcewByk3HWOAzTYM2OJB7t6PRd7ynAZz/FB7K8zK9Ny9Tkt9K', 'user', 'Eslley Ann Hernandez', '2024-0501', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6065, '2024-0376', 'jazleen.llamoso@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$kWB7ZOj4f50AC7YTIIECGOwIuGyh8p6jPy8ZSiBFR0aflpfk8wwmK', 'user', 'Jazleen Llamoso', '2024-0376', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6062, '2024-0507', 'aiexadanielle.guira@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$bsHzDof9R6YmeBo5fZ2Beus0bdxpnPRfhRjJfmSGOV9oNbbJbgrCi', 'user', 'Aiexa Danielle Guira', '2024-0507', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6063, '2024-0375', 'andreamae.hernandez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$SHckzJLtnbfSY7Fvw17fEOlaTesO8Q8z6fKMvDomwCR3KUadgV4G.', 'user', 'Andrea Mae Hernandez', '2024-0375', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6061, '2024-0371', 'leah.galit@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$vacKD76AscZUF2FHW.C4cubWhF24zYIuL.jEE7jUgbNcxgvmz4EYC', 'user', 'Leah Galit', '2024-0371', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6060, '2024-0385', 'mariejoy.gado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$D0x/5zQGFsX1168d0oNDuuRikqEOp6CZss0lvl3Yve0rvDwsfgm7u', 'user', 'Marie Joy Gado', '2024-0385', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6059, '2024-0366', 'hazelann.feudo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$evYSTd16858iM.BmtJtW4OtOnKYIuUY3ZMkFuWiWrwLyLkmJuGw6S', 'user', 'Hazel Ann Feudo', '2024-0366', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6058, '2024-0388', 'chariz.fajardo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$hvATLsXrlI9hQ5MbN62yVuJD7p5JZ2P173PIGUXhELy1Mxd7TE/MC', 'user', 'Chariz Fajardo', '2024-0388', 1, 'active', '2026-03-18 12:52:30', '2026-03-18 12:52:30', NULL),
(6057, '2024-0363', 'maricar.evangelista@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$gIeYDPLyK8XVczP3CxHI4eRx5VonbHxIB5jcY.O2EFTzWjMQGGb1q', 'user', 'Maricar Evangelista', '2024-0363', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6053, '2024-0369', 'mariel.delossantos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qLin8rFYE4jB954a7UPIKuYX1yZFMuFcjwFddSEsFndoF0NVAsG7G', 'user', 'Mariel Delos Santos', '2024-0369', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6051, '2024-0474', 'kimashleynicole.caringal@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$xj6ggnWSojniwqUXnUQFsOBFhtaytZYmqoyPwY7cmskd6cYOfKmcq', 'user', 'Kim Ashley Nicole Caringal', '2024-0474', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6056, '2024-0367', 'rexlynjoy.eguillon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$v.GskCz0pIsMZWBnlujLJuosFnF.UWYW.N/caPaDDypUJdaye4x.S', 'user', 'Rexlyn Joy Eguillon', '2024-0367', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6055, '2024-0374', 'kristine.dris@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$bPNgws/1eDjz4Xsjvy66uOeU8NQWX1.YttHa2oEDXceWvn8xnABM.', 'user', 'Kristine Dris', '2024-0374', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6054, '2024-0520', 'angel.dimoampo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$V5HnyvKt3M4HOCyUObSqou7rDFc.0Hp0ZRReo.qm4fArTX4InzhN.', 'user', 'Angel Dimoampo', '2024-0520', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6052, '2024-0351', 'shane.dalisay@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Mp2ykY3dQJdl7/hXWsfB/Ow2Zw4xvoUYhEqQYSyh36UR5w4lI4sHS', 'user', 'Shane Dalisay', '2024-0351', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6050, '2024-0355', 'elyza.buquis@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$2P5bJaJop0cBfLl.GMwEzO2RRTXgkH4j0OHnIfiZWW.IGHj9lzh5u', 'user', 'Elyza Buquis', '2024-0355', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6048, '2024-0347', 'cherylyn.bacsa@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$eOjBFgWWTSL6yz4kK88FA.G3wAOTjvsyK60nC0bFtp43uBu0AfSg6', 'user', 'Cherylyn Bacsa', '2024-0347', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6049, '2024-0364', 'realyn.bercasi@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$NpS3Yqd3lPUxXgOMgOrUi.jFR3h6fldoMTt3xPydP8rnSU3yOHbRa', 'user', 'Realyn Bercasi', '2024-0364', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6047, '2024-0354', 'maica.bacal@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$7qAogpRgrHBozxy09x20KOQFIwbF1MBLytLsYT4RyEkcxe7jv.QWO', 'user', 'Maica Bacal', '2024-0354', 1, 'active', '2026-03-18 12:52:29', '2026-03-18 12:52:29', NULL),
(6045, '2024-0360', 'rocelliegh.araez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$CkLNVM.nQh6niltb6hx98OlbaiEBbHa51gsqJATj/qoqv2Q.qzVXe', 'user', 'Rocel Liegh Arañez', '2024-0360', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6046, '2024-0372', 'katriceallaine.atienza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$YvzF4ojJ48DBTSqg4vOSq.E1L0B5Gma8T3NXtgtY9gzmJIteJbSzq', 'user', 'Katrice Allaine Atienza', '2024-0372', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6044, '2024-0379', 'crislyn.anyayahan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$x.rzetxg.EmmL7hLEgSXeOfcZpYn.Rkgsd9hOOEfECctVFE7ggHy2', 'user', 'Crislyn Anyayahan', '2024-0379', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6042, '2024-0504', 'lynse.albufera@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$xpmfsH/n314O2E7CRHOS5e81vSrKgEWZOTOLCRho.IAfLEAnJVhfm', 'user', 'Lynse Albufera', '2024-0504', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6043, '2024-0521', 'laramae.altamia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$KB8SPokW/Cv6A7T99zR6c.YwONTSz22gFL.FqrO4ciWUD9fCbnW/C', 'user', 'Lara Mae Altamia', '2024-0521', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6041, '2024-0378', 'benelyn.aguho@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$a/io/9NfXI8p6y/jfUXJsuwjHeeMM8wW7EDgpCZu4KmTsrywIxe/e', 'user', 'Benelyn Aguho', '2024-0378', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6039, '2024-0358', 'ashlynkieth.abanilla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Ll1lDOK8E.dU8smxf8l3Eu5KVeKW0yRBr6qUhrWt8Cf4WTumyNbxy', 'user', 'Ashlyn Kieth Abanilla', '2024-0358', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6040, '2024-0352', 'patriciamae.agoncillo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$fho4PJBwp7KAZRaMgUFDf.CHYZSszwMPWgk4RyR/Tin2D5U0IWWiK', 'user', 'Patricia Mae Agoncillo', '2024-0352', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6036, '2024-0530', 'allan.loto@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$V640yjumpx82wsVSjyBvO.x67B3DkKTzGFVQKo5c/aTmS.JAulAwm', 'user', 'Allan Loto', '2024-0530', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6037, '2024-0401', 'jhonkenneth.obando@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$xeqQ.T7B0EnSlTbQFGwy0uMDh3imx9l1cAUwiKoodx2e2vsoTJoZi', 'user', 'Jhon Kenneth Obando', '2024-0401', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6038, '2024-0462', 'rodel.roldan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zI2kFRqL83AR1D.6z1.TPeWw1NzORm.RjCiGLqGbtHdu4HOrD98kW', 'user', 'Rodel Roldan', '2024-0462', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6035, '2024-0555', 'johnmariol.fransisco@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$YE4Kjsb58M7N.tEyGLHUi.wCBqAvdS59ZK4AylqdlDcTnTbkrQzsS', 'user', 'John Mariol Fransisco', '2024-0555', 1, 'active', '2026-03-18 12:52:28', '2026-03-18 12:52:28', NULL),
(6030, '2024-0444', 'angelaclariss.teves@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Ls.OxeMWxzc5ed268y38F.DrLO30QF/tH/ybfp4tPZQDdPeazKCpG', 'user', 'Angela Clariss Teves', '2024-0444', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6031, '2024-0454', 'zairene.undaloc@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$rJ9faUvS0RWsoelyCDLMj.z7IA.t31UtoK0fKGF366StDNR60bgDa', 'user', 'Zairene Undaloc', '2024-0454', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6032, '2024-0449', 'johnivan.cuasay@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$InYGDKgPFw7XvM1FGvvbqupit9LLSh/mghri39/mnJF0JNb3GNV.S', 'user', 'John Ivan Cuasay', '2024-0449', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6033, '2024-0505', 'bert.ferrera@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ANcxof8l/UQCsLAE658HDueqOFMNUrZuX99KHUE38pkBOMbhJQERO', 'user', 'Bert Ferrera', '2024-0505', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6034, '2024-0450', 'rickson.ferry@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$18qS0PrsCzWGn.8523ByzeF7kzJ2zTsSVllNMiTWupvXKc0VtMZji', 'user', 'Rickson Ferry', '2024-0450', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6028, '2024-0538', 'mariairene.pasado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$abjBoFYC0oBAlGGBjVBZ8Oh4JPGmdQCxOupCtqPjNg2v4rRmgCwWe', 'user', 'Maria Irene Pasado', '2024-0538', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6029, '2024-0563', 'danica.pederio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$aJ54FWU.qWEjd54poWNgSePg8HQyjmm4zHW4V1hfbraCD7wggIGES', 'user', 'Danica Pederio', '2024-0563', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6026, '2024-0458', 'chelorose.marasigan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ydSlwAAVFoxVh4UBAV.hweqRyfRVxweHIUtDvB40yJ5AYCLvZmz92', 'user', 'Chelo Rose Marasigan', '2024-0458', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6027, '2024-0456', 'joanamarie.paala@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$cTzrqqSHrlvOzbXFN6AA4.EClulU4tiFh6Xc3VEdoId9Bi5g6x65.', 'user', 'Joana Marie Paala', '2024-0456', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6025, '2024-0545', 'febelyn.magboo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zjzXQi0pUOD9zldnQxaXBueECCDFy3RKXgexwGgwjyuTKU61IZLwa', 'user', 'Febelyn Magboo', '2024-0545', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6023, '2024-0463', 'angela.lumanglas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$srWOlhLMxVV/n3lWXIMr7.byePvHSqFJvE3TM2GXX.iAU6Dbop1Gm', 'user', 'Angela Lumanglas', '2024-0463', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6024, '2024-0464', 'michellemicah.lumanglas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$3BCNCWzcfdsVODMtOy9sGOh7h92JQo2nPzIEOg7EWgHjDgbyi5oga', 'user', 'Michelle Micah Lumanglas', '2024-0464', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6022, '2024-0440', 'irene.loto@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$7jC6Q76RdpRAE18tuZD0I.dqQm0csrMrEUyoj7kdOFHkC90fC9.m.', 'user', 'Irene Loto', '2024-0440', 1, 'active', '2026-03-18 12:52:27', '2026-03-18 12:52:27', NULL),
(6021, '2024-0554', 'apriljoy.llamoso@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$B0M.imQv9kOwd/WaEYE3Oug5J18ftkSId.fDidf0umnumjpsRziW6', 'user', 'April Joy Llamoso', '2024-0554', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6020, '2024-0476', 'catherine.gomez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$TMjLzOmWJEFE3dYznlesleWMxl2offaQwPppCj7pDWcsLNUuDJsfe', 'user', 'Catherine Gomez', '2024-0476', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6019, '2024-0441', 'janah.glor@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$j0/0AyqZ77xqwoO8UgUyMet4UyVbDruw/CTLooe3TLIZIiHtAYqI.', 'user', 'Janah Glor', '2024-0441', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6013, '2024-0548', 'angel.cason@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$f.FTSEJ0ZokNuU77qcs4n.6ZSCIFVt0SqCDM2NzD3fJdP22df9Aqy', 'user', 'Angel Cason', '2024-0548', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6014, '2024-0461', 'kcmay.deguzman@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$iFxjjsImHhEPcEI44HwNc.tnOK6/WglnE/rYuE6o.VG6wBtlBpUE2', 'user', 'KC May De Guzman', '2024-0461', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6015, '2024-0531', 'francene.delossantos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$AmuRp0Q7gALu9LoB0zK8xewk6/98IkJx.VSthgUZCvxWen9VKImxi', 'user', 'Francene Delos Santos', '2024-0531', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6016, '2024-0470', 'shaneayessa.elio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$.cm8FiqSbJ4OvKgYN0g1LuS.rXThKrK2ab74SkkiDNzZwRJG2ShTO', 'user', 'Shane Ayessa Elio', '2024-0470', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6017, '2024-0502', 'mariaangela.garcia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$b7f3AC4dKCFNr/lNuG0QKuU1YW0TgFtI2oZ95S4bLZvRO3tHNqvDS', 'user', 'Maria Angela Garcia', '2024-0502', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6018, '2024-0466', 'shanemary.gardoce@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$7ah3TjskdZCUswxZ75YDrun.xEE63c.M/kUi0BVYe6KG7r4A.ccei', 'user', 'Shane Mary Gardoce', '2024-0466', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6012, '2024-0503', 'carlaandrea.azucena@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Kf7ddNLlWHjqJa5SficS..PKuKaPaTW4Oqg9FTkiDH/wxvsZSHdF2', 'user', 'Carla Andrea Azucena', '2024-0503', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6011, '2024-0445', 'arhizzasheena.abanilla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Hsf6BE2vCYQxbVGqY1XGnO1ywJVczwZiZS8NFUfenBQeAmFxLYtd.', 'user', 'Arhizza Sheena Abanilla', '2024-0445', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6006, '2024-0490', 'mcryan.masangkay@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$oY4E03WAZS/iZT1c/VX.GO5uXF5hyZCRhB0MyysD3novZeuZ1TjAy', 'user', 'Mc Ryan Masangkay', '2024-0490', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(6007, '2025-0592', 'aaronvincent.manalo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$AANhY/GTaJ8JQj4d8C/VS.JQe0WHxOutHPoZik0dPGO2alYCdxPqG', 'user', 'Aaron Vincent Manalo', '2025-0592', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(6008, '2024-0494', 'great.mendoza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$gsMa9CZC2wV5nhZBuN0Ku.uUmnTGxY.DZLnsk5sHSZu3cyMFtsmSS', 'user', 'Great Mendoza', '2024-0494', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(6010, '2024-0455', 'kevin.rucio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$F1HyBPuW/RdEMYiYYEzeSeBvMrle9DGnjQ.Md9FJfQqUBtbNawp5e', 'user', 'Kevin Rucio', '2024-0455', 1, 'active', '2026-03-18 12:52:26', '2026-03-18 12:52:26', NULL),
(6009, '2024-0497', 'jhonmarc.oliveria@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$pMfHStljtuqmeG2ZFsEG4udS7FZtn9TgR/vU57x3WJirfu42gt7MC', 'user', 'Jhon Marc Oliveria', '2024-0497', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(6004, '2024-0499', 'prince.geneta@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$37GwQ9EqQmzvz.QZfNOpguvxCOdjcYBWr06Xd58OxGVTVG1f00Mrq', 'user', 'Prince Geneta', '2024-0499', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(6005, '2024-0495', 'johnreign.laredo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$16aQrsCl1l73HCoYOyp6yeHCWYZ.4J.A57d7v0Bq04FkNVqn3/myu', 'user', 'John Reign Laredo', '2024-0495', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(6001, '2024-0488', 'johnlester.gaba@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$z/.1CGjy1/arU7Pu3GFuBecfjN/4q0O7JecnFoytOeiKXiwDYAQNC', 'user', 'John Lester Gaba', '2024-0488', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(6002, '2024-0475', 'antoniogabriel.francisco@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Qk0ncwkc3RWe1i/Gj9Xv6upmWamaRWeExgkPB2HaC06cwcoEewjCu', 'user', 'Antonio Gabriel Francisco', '2024-0475', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(6003, '2024-0345', 'karlandrew.hardin@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$HYJZrlCkpYfePJXqeTVUn.K0.lhLRsaSPh.ZkVNaXXLhf6sa40KAC', 'user', 'karl Andrew Hardin', '2024-0345', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(5999, '2024-0489', 'reymar.faeldonia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$OWZcmnB0YzAgLL69M5oNQ.0AGUiI/7E/v2LOCQ4gZQVH.MMOEfQpG', 'user', 'Reymar Faeldonia', '2024-0489', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(6000, '2024-0500', 'johnray.fegidero@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$bJ7BBm.OT8TtCgaQTUE.PeNYawqEh4olYvDd3bkWAfHFeeA6VZDJy', 'user', 'John Ray Fegidero', '2024-0500', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(5998, '2024-0477', 'johnpaul.delemos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Hwh20u6vOJXbsMQmUEwGKe1rgtHFtfuZDxInBGMRMufumgMf/pMYC', 'user', 'John Paul De Lemos', '2024-0477', 1, 'active', '2026-03-18 12:52:25', '2026-03-18 12:52:25', NULL),
(5997, '2024-0485', 'cedrick.cardova@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$VIbhn4PDxkBTWkdKXEw8SOnK3jSS1AJQoGloaQCDA5CQS6.v80ETm', 'user', 'Cedrick Cardova', '2024-0485', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5995, '2024-0539', 'emerson.adarlo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$BftXheQgxCP2dH5KrBpQ2ONwna27mWA/DING2GZ/Ut1l.vgY7s40y', 'user', 'Emerson Adarlo', '2024-0539', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5996, '2024-0491', 'shimandrian.adarlo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$42hrGE3EscFBegimhn94d.La8z.i.kdN6lE1JiC87PlxXAz1F/uM.', 'user', 'Shim Andrian Adarlo', '2024-0491', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5994, '2024-0469', 'mischell.velasquez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6Es4ygNDS44mEc7SWnDFxO4bgDsmFuU6w3z/5JWEegqytb.C7LRIS', 'user', 'Mischell Velasquez', '2024-0469', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5991, '2024-0516', 'kyla.oliveria@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$AxMrudivit7BWSU0ngKCmOf.mno0ZxYakBNPcTz3n8aN8aSlVvaVq', 'user', 'Kyla Oliveria', '2024-0516', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5993, '2024-0442', 'necilyn.ramos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$KOGtcSKx1GOXrP2GIJdLgOvyq5VZ9A67Lxow2lDew/YPXWBzm.0vC', 'user', 'Necilyn Ramos', '2024-0442', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5992, '2024-0457', 'mikayla.paala@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$V4kfqaWiPk4qx0m7hWAyGejTcGeGiHmHFKKdeZJPTb12ClYFrFJJ.', 'user', 'Mikayla Paala', '2024-0457', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5990, '2024-0570', 'carla.nineria@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ppMDCbfyQ.fCy40IyyWPx.JzaiU8LM97AgHGkaAULu5H9YfMtsOVy', 'user', 'Carla Nineria', '2024-0570', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5989, '2024-0535', 'evangeline.mojica@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$KEaUDQos7do217bRmL1lu.UIlGxhpgvsn8c3BFLeDGoIY5EDZHixq', 'user', 'Evangeline Mojica', '2024-0535', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5988, '2024-0487', 'roma.mendoza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$k3boqmEkHgG6g5YED2O0VuTe5YhekPTbi67YarX2B6BQs.F9.axvm', 'user', 'Roma Mendoza', '2024-0487', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5985, '2024-0446', 'rica.glodo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$K8arrtayBzMz4jh4pADSV.16RUdicaC2qbJTg8OzuehmHMfMtTRsO', 'user', 'Rica Glodo', '2024-0446', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5986, '2024-0549', 'danicamae.hornilla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ot2CvwhHoc53RDWZmm8rfOzsx4smBeJZL2M3LTKbcSPzHz/gR7Lye', 'user', 'Danica Mae Hornilla', '2024-0549', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5987, '2024-0473', 'jenny.idea@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$oWKMhAmrCPcL1bKFLeGd6O75OjmIAcIoC4inpsTmku9JRr6uLuNjC', 'user', 'Jenny Idea', '2024-0473', 1, 'active', '2026-03-18 12:52:24', '2026-03-18 12:52:24', NULL),
(5983, '2024-0508', 'laramae.garcia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$B0dbkHmhGa01eyEiZziS3eHEMSu6SEaIukcFz.jvybazndhWGmCLC', 'user', 'Lara Mae Garcia', '2024-0508', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5984, '2024-0459', 'jade.garing@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$P0JR4Jx6pq7dIQDqFxLZ2eqacYG2QSCBAWVgKW.F8DSnyThnSr51e', 'user', 'Jade Garing', '2024-0459', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5982, '2024-0506', 'maecelle.fiedalan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WwpHwcCdHm0y.Cg6TvzHkOD06eGdAOHXBCx7gTagMu60BJAbiIiXe', 'user', 'Maecelle Fiedalan', '2024-0506', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5981, '2024-0546', 'gielysa.concha@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$2nDIXzwowp5jSMxd8A3zFufBoWmdk52CSdQVIjz6sgydLVF8D0BpO', 'user', 'Gielysa Concha', '2024-0546', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5980, '2024-0550', 'juneth.baliday@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$kvWrpu4qMYVgu/FixrOFS.ZPgmlblb9aRHDXcFvG70hbQCcGAu.PS', 'user', 'Juneth Baliday', '2024-0550', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5975, '2025-0597', 'ivanlester.ylagan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$4xiWxxlRFGyx0F/4yxjfh.O4y6fZtqDQ7jdXtEJ0nKWiepqSvgQcy', 'user', 'Ivan Lester Ylagan', '2025-0597', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5976, '2024-0513', 'kianajane.aonuevo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$YwS2ogZPvj3/UygTRNBR9OIj3/c.ZUgi99.3FyOXNsLfyt.Xi.HUe', 'user', 'Kiana Jane Añonuevo', '2024-0513', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5977, '2024-0514', 'kyla.anonuevo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$KAflRfly4URNxssc5ENh.uZb6g5mR2x6sAZz2LbRdjdO.oz638K0y', 'user', 'Kyla Anonuevo', '2024-0514', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5979, '2024-0591', 'regine.antipasado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$/Wq3E3vw5FnJ/mIgzms1YOu0YkpnF2z6t9.VE1JuT.TZaLP55jVYC', 'user', 'Regine Antipasado', '2024-0591', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5978, '2024-0569', 'katrice.antipasado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$EPKmZ3of/SBlaipGVCI8e.5ZUKw4KTNvTz6QV9aN3u/fiofmx2xTq', 'user', 'Katrice Antipasado', '2024-0569', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5973, '2025-0776', 'judemichael.somera@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$FQdpo290enYD00.GnYcpiO6FFkbm6V.VD8gcomc5XvsQrEKoEkXFG', 'user', 'Jude Michael Somera', '2025-0776', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5974, '2025-0695', 'philipjhon.tabor@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$fCs4WJgD/1W84mqGIIkhKOgJiO.Aj9klPx6Qiuh1mhJ0JnzibX3V2', 'user', 'Philip Jhon Tabor', '2025-0695', 1, 'active', '2026-03-18 12:52:23', '2026-03-18 12:52:23', NULL),
(5972, '2025-0764', 'tristanjay.plata@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$iSimGebhJqnZ135kx1OSL.WXLGWyrulrs0bU2.NlW.4TzOKG/q0le', 'user', 'Tristan Jay Plata', '2025-0764', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5971, '2025-0622', 'markjustin.pecolados@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$LGJw826NkM7HT7ejKahQ3.m94QQdKIklOURM3o1xh.1bpGxkYpZi2', 'user', 'Mark Justin Pecolados', '2025-0622', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5970, '2025-0600', 'patricklanz.paz@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$snt78ZBXE.Z.j.FAzEXxOuA10OPzecscp4H/R2qOt3dbKR2PC5j0K', 'user', 'Patrick Lanz Paz', '2025-0600', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5967, '2025-0651', 'jm.nas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$I2h9aK6lr9u7MfziDtGXpOnsV9ihpoYb.iQ7ENDpgb6YlmMFPoWLC', 'user', 'JM Nas', '2025-0651', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5968, '2025-0725', 'vhonjericko.ornos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Z08VX7eJk8HPTSevooJLveQ.ysootaagCaedQsdDXgb.7efWCPpH6', 'user', 'Vhon Jerick O Ornos', '2025-0725', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5969, '2025-0659', 'carljustine.padua@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$BFh3XZXV0XIIHnjsNd6wz.ufmCpikyoKHZL6COzyRK2GQYrTIcK9q', 'user', 'Carl Justine Padua', '2025-0659', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5966, '2025-0625', 'markangelo.montevirgen@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WrgkShZ/UzcAcePayTWnFegdfLfAdE.BHkwJRp./Kh9pjrt0H8qMK', 'user', 'Mark Angelo Montevirgen', '2025-0625', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5965, '2025-0624', 'hedyen.mendoza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qHzpbyr89FtgdW6FqNgtX.BNIwV5JEipVJ1aAnB1zfZhh5KPltKqe', 'user', 'Hedyen Mendoza', '2025-0624', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5963, '2025-0650', 'ericjohn.marinduque@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$k4KFoueKJUHMy5qr9HbLku97SyQN2xD8i.7X1UPu9ZMQ2w3HIwP7e', 'user', 'Eric John Marinduque', '2025-0650', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5964, '2025-0730', 'jimrex.mayano@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$oWxkSie/cJyWqRWfM5GJb.kOw3lHOfXSmfJWDW0idL1VgUAONT0ya', 'user', 'Jimrex Mayano', '2025-0730', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5961, '2025-0781', 'jandy.macapuno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$D5.xDaE.QadTm5.cjTM4Du70KzPSnRXKcsnzf0LBaIF7gj5V/Lqju', 'user', 'Jandy Macapuno', '2025-0781', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5962, '2025-0693', 'cedrick.mandia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$BqTCTTDZAkklh3v4YLQHfOnn6z/Tn1RUQulIEI74QqnnFcFFXNkH6', 'user', 'Cedrick Mandia', '2025-0693', 1, 'active', '2026-03-18 12:52:22', '2026-03-18 12:52:22', NULL),
(5960, '2025-0596', 'johnlemuel.macalindol@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$aC9gYt4OEoYPu/GGBT0laOQ6MNr5vRIm3uONW8J82klrpgGymBxWi', 'user', 'John Lemuel Macalindol', '2025-0596', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5959, '2025-0639', 'luigi.lomio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$5s1I57TiG4lXTCiEolx0muA5UA1NsjLKh0vsDY6yl1g1muOFNSqqe', 'user', 'Luigi Lomio', '2025-0639', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5958, '2025-0735', 'bricks.lindero@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$5ojxKygxL37OOJkMVx9RdeknhzFk4ZdbrZyrghIXA3XPcE7OlWKNu', 'user', 'Bricks Lindero', '2025-0735', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5957, '2025-0663', 'janryx.laspinas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$26T.wDXIHU4v0EKvkMWtWuiZF/X.DxcxvZN6RMC4cLeGC.tOwgnb2', 'user', 'Janryx Las Pinas', '2025-0663', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5956, '2025-0598', 'andrew.laredo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$at7vnPL5LPz54nBCBzakoeYCzWSHwf1PNyo8E8BADYuLtenljidG6', 'user', 'Andrew Laredo', '2025-0598', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5952, '2025-0716', 'dankian.hatulan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$jNeR0A5qEtGYzeTn.MrnmeimkUaY00yVchkL4ZRYw7p1NfM7lEiNG', 'user', 'Dan Kian Hatulan', '2025-0716', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5955, '2025-0662', 'ralphadriane.javier@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$cOdJArBV2l5T.fTvz6wvj.nASYOQuWU4Jv14fDtwCFjk9uCy4j4La', 'user', 'Ralph Adriane Javier', '2025-0662', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5954, '2025-0753', 'renz.hernandez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$BFNGTMKGb8iuu.Lb1yVDleBPBCr3BlgjoL0ufHTL.R78qs6U4WTYC', 'user', 'Renz Hernandez', '2025-0753', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5953, '2025-0803', 'benjaminjrd.hernandez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$JqIcWHrR81kzNSSb6sObH.QgWNpXsviPSEgbARZcBj8JdlOjPCqve', 'user', 'Benjamin Jr. D Hernandez', '2025-0803', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5949, '2025-0697', 'joshua.gabon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Ub0qnfweJZ8FdMwyD2JhNOz8YLNsBk/COJwFbCUGZ5CUJVEf7o1jq', 'user', 'Joshua Gabon', '2025-0697', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5950, '2025-0681', 'johnandrew.gavilan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$CRPAeGrVaSkpFEF5ZRj6jePeQkO08FYt59V9R8B4tubRPWVa1Fgju', 'user', 'John Andrew Gavilan', '2025-0681', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5951, '2025-0715', 'mclenard.gibo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$0RpGcBYgHkBiWqBGQqfXEO1djcTrxfVsV6JoKF1VlgGO6r28eaRqy', 'user', 'Mc Lenard Gibo', '2025-0715', 1, 'active', '2026-03-18 12:52:21', '2026-03-18 12:52:21', NULL),
(5948, '2025-0595', 'uranus.evangelista@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$OKb4ytZAlOY61OPCuflkqeYWllwJ.MpoT6zm5kpl.WSpojWezLOF6', 'user', 'Uranus Evangelista', '2025-0595', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5947, '2025-0696', 'alexander.ducado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$5MF1OwwCOrVXKBq7mWo85u79IZwGeGoBw4YA6ON1Ax4WByTwEkiU2', 'user', 'Alexander Ducado', '2025-0696', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5946, '2025-0782', 'daveruzzele.despa@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$YVzWOZ0FcBlE80arWT2b/enpkGdtqQEKzls4nOyGTUG5DwFJjYQ5y', 'user', 'Dave Ruzzele Despa', '2025-0782', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5942, '2025-0791', 'ramfel.azucena@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$s1g8nS/8xKRdeQ64/eGeoemCUBq6NJI1T6a2HFvsvbeJlyTrTuQ3y', 'user', 'Ramfel Azucena', '2025-0791', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5943, '2025-0632', 'jeverson.bersoto@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$YPU.9IQVgP.yQkOetc.Z1.PBbQ2QAu.ibDXHfw786egSMI5Q1yAUu', 'user', 'Jeverson Bersoto', '2025-0632', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5945, '2025-0652', 'daniel.deade@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$8bb36osShAQZnb5Kszu3.OhWkTKLNvl3bpfiVaGzUbdyT68xPb4JC', 'user', 'Daniel De Ade', '2025-0652', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5944, '2025-0626', 'shervinjeral.castro@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$5rsTQISHRaFs63Gkzls1LutgLeuSPUZZaoh0U9Q/Qja5f6ghlQvUm', 'user', 'Shervin Jeral Castro', '2025-0626', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5941, '2025-0620', 'rexon.abanilla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$jA1B4RMH8zuyts8bVGR0fe6j/sPU254HPKX/fdGEX.j6NRgr6hlPe', 'user', 'Rexon Abanilla', '2025-0620', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5940, '2025-0814', 'lovely.torres@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qY0aLvaqqyjtNcp/rNFR7OHDsn09ORcWDyLwlZ195TWv4Y4avfboq', 'user', 'Lovely Torres', '2025-0814', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5939, '2025-0634', 'marbhel.rucio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$IRJn0JpvUH.3YPiu/cIRKuCZpBWnUn020zwNz2hmGxYc.ToQ/3TfG', 'user', 'Marbhel Rucio', '2025-0634', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5937, '2025-0628', 'alyssamae.quintia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Q8rMolW6nbP/1u0pRX6pFORfuqqiuAZoaUXSLluaSlIV5xP2Cgy62', 'user', 'Alyssa Mae Quintia', '2025-0628', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5938, '2025-0774', 'jonamarie.romero@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$yeMjsOvP.PfpBhvYDqTA7.lAXrAAxiV5arghdbMcURXezH.qXKmFW', 'user', 'Jona Marie Romero', '2025-0774', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5934, '2025-0748', 'arien.montesa@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$sxrU7YubnlYGM4jXIiiOzuWRJ/M3AMUwTKkWc9TN7Atvdz7iv9YhG', 'user', 'Arien Montesa', '2025-0748', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL);
INSERT INTO `users` (`id`, `username`, `email`, `google_id`, `facebook_id`, `profile_picture`, `password`, `role`, `full_name`, `student_id`, `is_active`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(5935, '2025-0653', 'jasmine.nuestro@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$aEwcymkNB5appIuEYlgUMOGDSMz4Fhuu3VIJgIJYbZFcAzwRuy/9y', 'user', 'Jasmine Nuestro', '2025-0653', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5936, '2025-0738', 'nicole.ola@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$P46I7P4Hjih9ibglwVZS/ecg1TfenY7WXo0366rVGwSde2ImPNPXK', 'user', 'Nicole Ola', '2025-0738', 1, 'active', '2026-03-18 12:52:20', '2026-03-18 12:52:20', NULL),
(5933, '2025-0708', 'ericca.marquez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$x0zCYT.6tYcwwnXTIkjeM.hfFiwyEJbNm6AZDjrAVinGIuKwlRgve', 'user', 'Ericca Marquez', '2025-0708', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5930, '2025-0720', 'charese.jolo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$DXJaqjKUu5E2O/v9akz3De87v6.bJ0ecJJs.G9pdbecPjsWOcL9WK', 'user', 'Charese Jolo', '2025-0720', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5932, '2025-0739', 'abegail.malogueo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$F8Hbn13teRuebCuf9RO7U.GmoSGM7uxBcWMVD/mS5aN3mSZrWJQSy', 'user', 'Abegail Malogueño', '2025-0739', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5931, '2025-0682', 'janice.lugatic@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$JEvfsgjinGKovdrggPPLgOdlssHhy3SQwjCj2HZbNLdAOE.QBN/x.', 'user', 'Janice Lugatic', '2025-0682', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5929, '2025-0664', 'aleyahjanelle.jara@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$jhivK1mSbxrV6ATw6973COB90tHPV3c.zEljxetuZApSwHn5FzHoi', 'user', 'Aleyah Janelle Jara', '2025-0664', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5927, '2025-0719', 'deahangellas.carpo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$RFxe6mszrdCBigAjTuA4TOjnpNuq4LFejohjwlAfEnuUr5kl2hLOW', 'user', 'Deah Angella S Carpo', '2025-0719', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5928, '2025-0802', 'jedidiah.gelena@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$w.beDZmr0Kpj5l8XlSWy4uPNAtfYrN4rOsar7gSz/3Ut7tO0ogHe6', 'user', 'Jedidiah Gelena', '2025-0802', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5925, '2025-0669', 'danielafaye.cabiles@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$5HH1sS7/qovLVVSLvNMYgumPdA2NvGWWH2YFvyq6qC3Jl3H3cEOmW', 'user', 'Daniela Faye Cabiles', '2025-0669', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5926, '2025-0599', 'prinsesgabriela.calaolao@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$OLpg/hwB.xmhSCsGMzm01eFhqn97o3jvM8YjoCONYeDX8V5KNuk2q', 'user', 'Prinses Gabriela Calaolao', '2025-0599', 1, 'active', '2026-03-18 12:52:19', '2026-03-18 12:52:19', NULL),
(5923, '2025-0752', 'sherilyn.anyayahan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$cvt52PDQgv5RMXyDsccNH.vSxVudp3WV.93ie5fCc9r2y2qwUMWjC', 'user', 'Sherilyn Anyayahan', '2025-0752', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5924, '2025-0623', 'mikadean.buadilla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WPDwghTa4ZUipAq1804UyOnNevVjeDFO2/Z0fagjc/69K25bQi2QW', 'user', 'Mika Dean Buadilla', '2025-0623', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5922, '2025-0661', 'aizel.alvarez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6p4/SKBJ4lx6PYNmzEsGtOmnALmK31Kts.FsAHW71BcBv.tksyJEu', 'user', 'Aizel Alvarez', '2025-0661', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5920, '2025-0775', 'angela.aldea@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$IQq.MDCE9v7HE9G.AvaIJ.O4Byd8WTlbERIGcSiS4DZAqCkFppcA2', 'user', 'Angela Aldea', '2025-0775', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5921, '2025-0601', 'mariafe.aldovino@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$PRW3pN72etPnSE3tidWZ/OVfHwSe.n6v67GlX4OabAx0rgg/yVUea', 'user', 'Maria Fe Aldovino', '2025-0601', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5919, '2025-0621', 'novelyn.albufera@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$vwaV9UjQfbdeOl4adrsjE.C9S7UQra6c.989Yz6F09MyR7zMHqQlW', 'user', 'Novelyn Albufera', '2025-0621', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5913, '2025-0627', 'kervin.garachico@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$.up/bkwKFH5BV.Mxpx7lEueVpgUqqvKuyRAVL1Fw.hyXLCJORzSf2', 'user', 'Kervin Garachico', '2025-0627', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5914, '2025-0865', 'zyris.guavez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$aPXL7m/cBI2dxaZLaDl9l.Rc.nAO4rVfJQTKN1LQARt.f5mn5AU4a', 'user', 'Zyris Guavez', '2025-0865', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5915, '2025-0740', 'marjuna.linayao@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WZLpmxq3r0PE86C3SjWEkejA8GrbX1/YvBSQpxm1qfYMfOq4K5DIO', 'user', 'Marjun A Linayao', '2025-0740', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5916, '2025-0660', 'johnlloyd.macapuno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$mfxroI3QXI8joTBVhhcCeODQbpFSPiRTa4ikT2rheUmfq8Iyinaey', 'user', 'John Lloyd Macapuno', '2025-0660', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5918, '2025-0645', 'dindo.tolentino@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$RSSMnMO.mhcTxXBXGBd.MuHg1l9uLLNhQcYmQFMRldtX..ovb6qiq', 'user', 'Dindo Tolentino', '2025-0645', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5910, '2025-0684', 'rodel.arenas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$cfZqGKzVVNQAhb/mwSsSYeBfVHig8.9.O1UYNO6TM8WHegNS/CYQW', 'user', 'Rodel Arenas', '2025-0684', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5911, '2025-0690', 'rexner.eguillon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WGKjp4HI2nXOsOOafbgkbes6KfmKK5mEkh3eEGAEB.U78mbvySxZ2', 'user', 'Rexner Eguillon', '2025-0690', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(2574, '1', 'admin@gmail.com', NULL, NULL, NULL, '$2y$10$hBNUdr9u8zu.f7zVAUNYG.l7a8lKrpIZeEBZsEjkWYOYNEmDjARDe', 'admin', 'test', '1111', 0, 'archived', '2026-02-24 01:05:02', '2026-02-24 14:09:45', '2026-02-24 06:09:45'),
(5912, '2025-0815', 'reymart.elmido@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$yV5arnEPmt7Y9wiVe6UMLen0zsMTHyI0CBc7gC8GAB.AAczxB9m06', 'user', 'Reymart Elmido', '2025-0815', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5909, '2025-0806', 'meganmichaela.visaya@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$JJf4lvqU78PHJllbg8icqOIc61dsFMqjoBJKO7gHDfTkTUMAlwQRO', 'user', 'Megan Michaela Visaya', '2025-0806', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5908, '2025-0723', 'pauleen.villaruel@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$poXJavGOadhFqKJz3thU1.VkYSL5wEI7G7ttMINXEP10XYBC7/H1q', 'user', 'Pauleen Villaruel', '2025-0723', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5906, '2025-0777', 'nicole.silva@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$8LYUPQhOSPgQbvEZMJiiZu0Iq.a46FuWrG1bJbp0KMEV3g9wJusOC', 'user', 'Nicole Silva', '2025-0777', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5907, '2025-0731', 'jeane.sulit@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$/EghnaVo9fPNixsWA3prBu0Xgtf9OoEAAfUJhGbMlyXjwfCNc68IW', 'user', 'Jeane Sulit', '2025-0731', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5905, '2025-0734', 'rhenelyn.sandoval@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$cbgLuYFec1KNwFMYg4EiUOZUXQ63FM7UvtYG1RV5aepFiZ1tvIpLe', 'user', 'Rhenelyn Sandoval', '2025-0734', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5902, '2025-0779', 'jeafrancine.rivera@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$y3XPP1tShZUdbIyGAO47huGMbRRgE1vSy424FAu/9UEy.sz507bHm', 'user', 'Jea Francine Rivera', '2025-0779', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5903, '2025-0788', 'ashlynicole.rana@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$JZEsBkaa56SwvUzMb.aYHOpPQadUhil9S07YqKr8jGhFQBjoqQ.g2', 'user', 'Ashly Nicole Rana', '2025-0788', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5904, '2025-0741', 'aimiejane.reyes@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$kiqM3tJYJigvdgIWU5XBjuw0zDxZslPdSvI141OwdYKqvT2ehRDS6', 'user', 'Aimie Jane Reyes', '2025-0741', 1, 'active', '2026-03-18 12:52:17', '2026-03-18 12:52:17', NULL),
(5900, '2025-0728', 'materesa.obando@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$U04r7MLBK1uH.E0wR4YVeub0DLTpnMiOgpXrb4Bszx/TEAZNiyoYG', 'user', 'Ma. Teresa Obando', '2025-0728', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5901, '2025-0647', 'argel.ocampo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$DCsqTw6jLgjXdCU/hr54S.IkuQQuHCmfEJw/kKc8p5WBWs4msb69m', 'user', 'Argel Ocampo', '2025-0647', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5899, '2025-0710', 'ericamae.motol@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$LIHd/3QjEVxQnHSKdTiK4uNEowv6HW5YHSA5AZwNQ5QGA7wpCUEXu', 'user', 'Erica Mae Motol', '2025-0710', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5898, '2025-0729', 'camille.milambiling@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$uEJRqVho7JzzgIK1Xs1GzuTbvzrEOvut85mCL0l27QfiVrtLWSU1m', 'user', 'Camille Milambiling', '2025-0729', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5897, '2025-0609', 'leslie.melgar@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$nPYY8Ywe/EsVbAk.n/naXOtuV122i/y/h9Xwmr5jT9wzt9nzbY.S2', 'user', 'Leslie Melgar', '2025-0609', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5894, '2025-0655', 'edlyn.hernandez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$H5xHjrOR4MlRxIzDEYZtO.t80mq.ceR4tajuM/mkFZEHOmb7OEkKy', 'user', 'Edlyn Hernandez', '2025-0655', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5895, '2025-0633', 'angela.lotho@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$siUtmnZgA9j/rc6wj36P8ORsSpB2snp08nTwHsYVKK.Efgiug8TAS', 'user', 'Angela Lotho', '2025-0633', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5896, '2025-0808', 'remzannescarlet.macapuno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$jt.s9rfwJTzJssKYUx3OXuowoFuD0/r6gbpxXmws6ek4wpo2.qwBS', 'user', 'Remz Ann Escarlet Macapuno', '2025-0808', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5893, '2025-0737', 'shalemar.geroleo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$XcnvX9g8yi8Mu93lGE7uke0HpE.7R3YArFWYJn9tATiCik2jsXOBy', 'user', 'Shalemar Geroleo', '2025-0737', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5892, '2025-0713', 'katrice.garcia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ptepCCsX6.mdqh1i1gMxfufkG0fuDdZQIhRgqwxwyyl1dNpp5NF7.', 'user', 'Katrice Garcia', '2025-0713', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5891, '2025-0654', 'jenelyn.fonte@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$2nzjar6z.p1NUvFU7RuxlOW9bpn8I5xRICd0vEI413nDrMZ9T0Zpe', 'user', 'Jenelyn Fonte', '2025-0654', 1, 'active', '2026-03-18 12:52:16', '2026-03-18 12:52:16', NULL),
(5890, '2025-0618', 'judith.fallarna@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$mqNTHW5VefQh2xgtsKjvhOg3v3mYIX1cBLdRURWG4jyS6RhZVnrZK', 'user', 'Judith Fallarna', '2025-0618', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5889, '2025-0657', 'ailla.fajura@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Uw/FTW0Dwc/4eUbILw5CXuw8P7WCKF5Tqabkq.n26GKkNG1awaljG', 'user', 'Ailla Fajura', '2025-0657', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5888, '2025-0688', 'elaycamae.fajardo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$8jXLHexoMYEpQfm98CDpKeD2wAQDZZQ3xU110t3EpyOUeWLac5whW', 'user', 'Elayca Mae Fajardo', '2025-0688', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5887, '2025-0611', 'christinasofialie.enriquez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$u1..hyFSssB7XtJY60EZe.elkBo/8VEaSHBQDbO4vo.l0CnM1e7gS', 'user', 'Christina Sofia Lie Enriquez', '2025-0611', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5884, '2025-0673', 'nicole.defeo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$o0OfTE688qzTlVgfr4B33OR06c8cbuyvKMqK8OBu8BP/7ofTVhJsK', 'user', 'Nicole Defeo', '2025-0673', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5885, '2025-0722', 'sophiaangela.delosreyes@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$xbsAHV62PBg.Q1eB/Xo2pu1eVRdDKKy2TVRwXSWQxQPqUv8wkJVB6', 'user', 'Sophia Angela Delos Reyes', '2025-0722', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5886, '2025-0612', 'romelyn.elida@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$aWqchdLqtQq2eP..sgqeGuHb7bJIwCLbvYZDfPfLOX7JX9fuGhNMa', 'user', 'Romelyn Elida', '2025-0612', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5882, '2025-0727', 'prencesangel.consigo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$OLBVu43cH4geDGebXbns3ejN3AuClQMRYv90JPurhYvFZxl7hkL/e', 'user', 'Prences Angel Consigo', '2025-0727', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5883, '2025-0742', 'jamhyca.dechavez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$krzz4gLonGaNeaxuTYPhLuCtLhCAgCImWD4JQbSSTiSSz.Bn1MJOS', 'user', 'Jamhyca De Chavez', '2025-0742', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5881, '2025-0711', 'claren.carable@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$u2dmgecLKa3xa8gEKMgL/ug.4j5/AgFPUfxxppRgm31qysallAt/y', 'user', 'Claren Carable', '2025-0711', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5880, '2025-0638', 'shiellamae.bonifacio@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$FGqKZVX3g7FnVg8DPj6uRe5hdVQ2OWYcmYVxGT84eRxxhm2BSnMPS', 'user', 'Shiella Mae Bonifacio', '2025-0638', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5879, '2025-0783', 'lorraine.bonado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$/g8/ogKarVsTWUkXuCJwd.ylwOxtsKqlReOSnOM1TDjynvOc7eNpS', 'user', 'Lorraine Bonado', '2025-0783', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5878, '2025-0679', 'alexajane.bon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$6sx4BN78kd5drTDKnBDP5.Eb5NM4lB01Gk5lwPHthf1WCPU9np3OK', 'user', 'Alexa Jane Bon', '2025-0679', 1, 'active', '2026-03-18 12:52:15', '2026-03-18 12:52:15', NULL),
(5877, '2025-0646', 'jhovelyn.bacay@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$/w49Lm6FDoiZXXb9bCuMIu.O1Pie7WG6yozffTcIY6ZIMz4YbTzu6', 'user', 'Jhovelyn Bacay', '2025-0646', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5876, '2025-0680', 'jonahtrisha.asi@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$v5/Y13DKKk8oD7Zonm4IdOoKsibc6YaVhH9me1Of168okmTuypeEe', 'user', 'Jonah Trisha Asi', '2025-0680', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5873, '2025-0619', 'hanna.aborde@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$os78GSXRJ.Wc9xI96.tvg.fM5SDX/Rlg4esijXSzyDbQb3bENB/sO', 'user', 'Hanna Aborde', '2025-0619', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5874, '2025-0765', 'rysamae.alfante@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$b/ag/MdDF8NAqdyFtyebT.l3feYYdkBzUwHpuC01vx7XFpuVRwPcW', 'user', 'Rysa Mae Alfante', '2025-0765', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5875, '2025-0809', 'jeny.amado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$SdDXNIXGClXKp9eqUvIyRuepuhqrMHfPx9CwnIuVOZC3e1DlIF8/m', 'user', 'Jeny Amado', '2025-0809', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5871, '2025-0617', 'kann.abela@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$oMSaunKw8fjchN.jUn/H8u601mc11JeAOZEPlaWYCn9.NV.FyNqXG', 'user', 'K-Ann Abela', '2025-0617', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5872, '2025-0733', 'shaneashley.abendan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Ugb7x4P7TmBVVmBc9MvpbeWgP0rp3vEEM0YP8jpTJfAag3mY956iu', 'user', 'Shane Ashley Abendan', '2025-0733', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5869, '2025-0762', 'erwin.tejedor@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$3RY6DDXtRsjVmeh7zCP5BuGTuYXsJmr4fjl.s4yNsf0gCDpKoaKj6', 'user', 'Erwin Tejedor', '2025-0762', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5870, '2025-0747', 'brixmatthew.velasco@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$7gTEbL/yP3d7W5HKYfERHeK6X8bT6hl8g0OO/XMOyWhJiFnOn8Afi', 'user', 'Brix Matthew Velasco', '2025-0747', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5868, '2025-0801', 'melgabriel.magat@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$JAtECLMVxsjAqJRPGaL0leYwFgRN/h344XyipmTx0bZ8xl4ZWSKlW', 'user', 'Mel Gabriel Magat', '2025-0801', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5867, '2025-0785', 'jairus.macuha@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Rc5D5gfC9sCoidHtHHp1luiQvs928DXyF2O7ywP20rVQ9luYxhjUu', 'user', 'Jairus Macuha', '2025-0785', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5866, '2025-0636', 'jarred.gomez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$.eqaZpmKY6G1EscJ/OQZCuTroIh6uDTAlBJNRGCl.z2/0yolINEU6', 'user', 'Jarred Gomez', '2025-0636', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5864, '2025-0726', 'aldrin.carable@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$aCNE5UeYmN5cdZO8iRju0.ScdOPdT/m8UcTLR5aIRXlSluyKoKGSG', 'user', 'Aldrin Carable', '2025-0726', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5865, '2025-0743', 'daniel.franco@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$4qCWBoaaqDj92BLg71s4XuO57rrXRM0XLKdTbI97isQ3uhHVyfE5S', 'user', 'Daniel Franco', '2025-0743', 1, 'active', '2026-03-18 12:52:14', '2026-03-18 12:52:14', NULL),
(5863, '2025-0705', 'danilorjr.cabiles@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$tn3qupDqln6ljOQjNkBl6ulFy/QLQDaJjkoTcbeM6cOHkEAWGsvSy', 'user', 'Danilo R. Jr Cabiles', '2025-0705', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5862, '2025-0629', 'felicity.villegas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$izkDqqKinqZJfxLQs1YcbOEUzf9EqEzH/PgnLZxGEemPXqbuBlcBe', 'user', 'Felicity Villegas', '2025-0629', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5861, '2025-0643', 'wyncel.tolentino@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Q0/WZ/7kn3MYZvnOGOylIecg0n6n02VwwvIMzoU7C84po2lvkKrRq', 'user', 'Wyncel Tolentino', '2025-0643', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5859, '2025-0796', 'rubilyn.roxas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$/N6iuS/XjKl3swvpVZvaNecmNT8fYojDjE/nDh8pRJOqkUcGfiM9i', 'user', 'Rubilyn Roxas', '2025-0796', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5860, '2025-0718', 'mariebernadette.tolentino@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$u6erVlp7GfdzH2DgokQWhO.UJ6KEFwtP0sn1oyfrNuXCz3chRwG3y', 'user', 'Marie Bernadette Tolentino', '2025-0718', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(3116, 'adminOsas@colegio.edu', 'adminOsas@colegio.edu', NULL, NULL, NULL, '$2y$10$18hPsHdTOOqn8S0jcVE8Je8URHOsCgj6QUzuYFPCqxrrhri0TN2T6', 'admin', 'Cedrick H. Almarez', '2020', 1, 'active', '2026-03-12 02:42:40', '2026-03-15 13:41:23', NULL),
(5917, '2025-0732', 'helbert.maulion@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$uqlC/Np.th6lLX703sHS5uN.7GesNGNgMwBBLLTDyI8pef7pu.6am', 'user', 'Helbert Maulion', '2025-0732', 1, 'active', '2026-03-18 12:52:18', '2026-03-18 12:52:18', NULL),
(5858, '2025-0789', 'irishcatherine.ramos@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$AUJ.oCdP/lcrEQ9SCt2bxe5NLsImxV1GSS6Lyidzc5GxkzYYpIEIe', 'user', 'Irish Catherine Ramos', '2025-0789', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5857, '2025-0770', 'ivykristine.petilo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$fxmPv8G9cH81YpP3gdcZsOuaKvaeLL12jHWN9K0Ga36iI9Y1bDR4C', 'user', 'Ivy Kristine Petilo', '2025-0770', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5856, '2025-0766', 'althea.paala@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$0QsbnmBUoTmRP0JVpG8g5O2kC8gOERYFWcme9B6OPHT/i70NV5XhG', 'user', 'Althea Paala', '2025-0766', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5855, '2025-0699', 'lleynangela.olympia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$FWsykC/QExcpAXicw87fHO/7iHKR4G5grQS2HIXlndBIGeJ7CvxFe', 'user', 'Lleyn Angela Olympia', '2025-0699', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5854, '2025-0772', 'romelyn.mongcog@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$P0cjH4snQpN28SkOokWSw.325f.GRId5..bccd7GAUsAuKPoTxdf6', 'user', 'Romelyn Mongcog', '2025-0772', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5853, '2025-0767', 'lovelyjoy.mercado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ZWALQZ73UEqgM06jQOYjTOGtgFmZgi69wOXpwW8r0EVoM8BzGDHSW', 'user', 'Lovely Joy Mercado', '2025-0767', 1, 'active', '2026-03-18 12:52:13', '2026-03-18 12:52:13', NULL),
(5852, '2025-0763', 'lorainb.medina@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Hn39YZQEFDawFz911qXb6OAxK/4fqhVmrF4hYa7JBQfeapyWFfBeK', 'user', 'Lorain B Medina', '2025-0763', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5851, '2025-0771', 'mikee.manay@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$OHIui2MNIVnesMTkLgK1uu1laRfoOluwCu/BZ5/SfEEetJnJggwy2', 'user', 'Mikee Manay', '2025-0771', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5849, '2025-0805', 'mae.hernandez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Gq17HNMWxZFC4pZ5WdsmFe5RxhpQUGaL3ozNxkpqIagU8Dd3aYO9y', 'user', 'Mae Hernandez', '2025-0805', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5850, '2025-0656', 'arianbello.maculit@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$tjUtQQeWB1NCMEOV8NkWo.p7GX8DK15ziWpkZy6/Gyse4Xqpon942', 'user', 'Arian Bello Maculit', '2025-0656', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5848, '2025-0786', 'bheajane.gillado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$H5t/j.c1ODBHq6wpct8uUuNf7PO29h7sD9Ma80AcQkMvYmsNkGFoC', 'user', 'Bhea Jane Gillado', '2025-0786', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5847, '2025-0800', 'aleah.gida@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$0rlTozmoGMKS4gKzxBkn../XqwOg/Axw3tZuX44M.1/Av4hVCsEYu', 'user', 'Aleah Gida', '2025-0800', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5846, '2025-0667', 'janel.garcia@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$VEW/rNeQizDP48gSrwYUceIUvd.BaB/Wj7IJ0VqYQUg4h89wv.qjm', 'user', 'Janel Garcia', '2025-0667', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5845, '2025-0756', 'crystal.gagote@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$IfGrR/lhDxIAMKd0jBlKau4fn/hQ3rI0969KIZVAQaBydJz1bGega', 'user', 'Crystal Gagote', '2025-0756', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5843, '2025-0668', 'zeandane.falcutila@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$g2BhGU7m5f2eMXzFdxEKP.e91wJB4EEPV7UwBfH0zPlxmNWU1NVkC', 'user', 'Zean Dane Falcutila', '2025-0668', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5844, '2025-0755', 'sharmaine.fonte@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$kPuVBQ7y..ZrEvsX/uvYDeEaNo1PG0fkrSvF.NCdrg7ZULPdjetYu', 'user', 'Sharmaine Fonte', '2025-0755', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5842, '2025-0754', 'analyn.fajardo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Dykfb2B3QmgiAsh2gZ4ybeA73mVoFjczqft8lKt.TkptX2CpIJkA2', 'user', 'Analyn Fajardo', '2025-0754', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5840, '2025-0790', 'annanicole.deleon@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$P9UdnY/RHC.e8Wp1Y2Ms1eF5InU366e58t7aoFAc3/qH3MlOjs8TS', 'user', 'Anna Nicole De Leon', '2025-0790', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5841, '2025-0778', 'shane.dudas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$pPoJE4WzGRj00LgfU65hh./RM9fg7U64k8IX3xlXLKZTxEI1DW8UW', 'user', 'Shane Dudas', '2025-0778', 1, 'active', '2026-03-18 12:52:12', '2026-03-18 12:52:12', NULL),
(5838, '2025-0793', 'marrajane.cleofe@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Uk/tNpD8VVR7jw8J7tZbL.TY1My3mWgtlSbKq0oENgRGT3G1nguzG', 'user', 'Marra Jane Cleofe', '2025-0793', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5839, '2025-0637', 'jocelyn.deguzman@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$xnk.CmSS7b6fxq902wawjufcCk4.HH2wkE3y.SFjJV1nHqTPFtXw6', 'user', 'Jocelyn De Guzman', '2025-0637', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5837, '2025-0758', 'danicabea.castillo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$X4PGR9HdcbuZ5JYTNFx5leWJ04l.i5onR87A4EQVl.s7Hqw72x646', 'user', 'Danica Bea Castillo', '2025-0758', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5836, '2025-0676', 'rhealyne.cardona@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$V7s875TpA5H5fZQsF2jgBuTSsKjO8h8WemkpxSp9px16d2rZAEskS', 'user', 'Rhealyne Cardona', '2025-0676', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5835, '2025-0658', 'myka.braza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qOhR1jc4BpFSQ0GsYh/bIOe1Lzd9MkBi4uHbwN523cq6k3YA68hfm', 'user', 'Myka Braza', '2025-0658', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5834, '2025-0745', 'charisma.banila@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WjNu3v4rUJnOj6GZ75YgkeawTCYOMP47VC9jDLZUbAmngQTgXe3Ry', 'user', 'Charisma Banila', '2025-0745', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5833, '2025-0797', 'marydith.atienza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qEKOX5UuE3MYk0ikpYj5B.gDrFiqL9fXo7MRq0ORANN2R0mzlnN1S', 'user', 'Marydith Atienza', '2025-0797', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5831, '2025-0534', 'khim.tejada@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$HRjLy.j0CIvrfMMWTTY8aeatH7vN4FhAopH7P604np6cQNW1WFNMO', 'user', 'Khim Tejada', '2025-0534', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5832, '2025-0784', 'maryann.asi@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$JE7vsgmq6IcsEhY/DeMiCuoF9jUWfYebVolS/VGC8sr.D4VSLr24a', 'user', 'Mary Ann Asi', '2025-0784', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5829, '2025-0606', 'jhonjake.perez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$BbC/dJm7pHk/7jE9Jy.Rj.oTCnHfrqkX4tJvlSA/jw1bLEWEmJGK2', 'user', 'Jhon Jake Perez', '2025-0606', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5830, '2025-0692', 'johnkenneth.perez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$P5aIu20ERstl7LEhd.uiCesyiSuMPsAe2tnKXrw0X.xH6dOG870PW', 'user', 'John Kenneth Perez', '2025-0692', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5828, '2025-0686', 'johnwin.pastor@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$IKZCFLtYtS6R6dD66gme5OgOObfPZradS1y4VQtbEhldjYYc/wzEa', 'user', 'Johnwin Pastor', '2025-0686', 1, 'active', '2026-03-18 12:52:11', '2026-03-18 12:52:11', NULL),
(5827, '2025-0757', 'johnlord.moreno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$qksAd.hbQCT.zu9bpFKMgen3yNpSJrxnKMnFNm.1ctEEvQZBYgCl2', 'user', 'John Lord Moreno', '2025-0757', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5826, '2025-0649', 'ronron.montero@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$2S95oh/aSqztywsfPoVjjuQODOfLDLnT0X7A6euyU8K7yFF49qQc2', 'user', 'Ron-Ron Montero', '2025-0649', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5823, '2025-0746', 'jhonloyd.macapuno@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$M.6phouzMV7VvkQuMou6jePYZY/iIeOysaNpQmpMZZruP.XqwPgrq', 'user', 'Jhon Loyd Macapuno', '2025-0746', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5824, '2025-0672', 'paultristan.madla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$WT1wvLKJzlV6WsJMpz5kluGKaquRZNCVF56wjaiKM26G2fkm7XCsC', 'user', 'Paul Tristan Madla', '2025-0672', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5825, '2025-0594', 'marlex.mendoza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zyAqtAqdcTSQMbVIfyYG/.vTK.Wot7KqPOLrRfoN3Z4VBcu.9p9pi', 'user', 'Marlex Mendoza', '2025-0594', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5822, '2025-0794', 'jaypee.jacob@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$N9Aws7q1cE3GoajZjiMI.uYnDzSZeQJRxsvugmXEFejhITzBkCuqG', 'user', 'Jaypee Jacob', '2025-0794', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5820, '2025-0603', 'bobbyjr.godoy@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ZYoI.ji4CaVhjxAsWemdwOBSGdSqIBcBWXUODNmLU/cY62xPvWQYO', 'user', 'Bobby Jr. Godoy', '2025-0603', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5821, '2025-0795', 'edwardjohn.holgado@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$/6o9QoTJWaBjbllieAAzMO3c9a/CZsojkd4GGNPVZqgl3.xIlnpnO', 'user', 'Edward John Holgado', '2025-0795', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5819, '2025-0593', 'jared.gasic@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$l0ZWuRhmiLkxpZMkuAqdIuntQZwKINvZdkudzPgUNeRfDalU.M8Sq', 'user', 'Jared Gasic', '2025-0593', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5815, '2025-0604', 'giandominicriza.dudas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$PFT9x1LwM6NklhNyxAfCgOKfAbJnQCdsVTMgyxfmNeIUb/mQ5/8yW', 'user', 'Gian Dominic Riza Dudas', '2025-0604', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5816, '2025-0703', 'markneil.fajil@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$DR664hrksipgc5/R2b.1SuwpEWSxAjhWQt/rcwRcTz05lSSQyt.ky', 'user', 'Mark Neil Fajil', '2025-0703', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5818, '2025-0363', 'jhakeperillo.garan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$MnLdSX2P4e5Vw8ai707/xuvIjGeOAOx.sTUG.dN2Hz0ut0J5UQw2i', 'user', 'Jhake Perillo Garan', '2025-0363', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5817, '2025-0602', 'markangeloriza.francisco@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$vrSFIJ8hr0NdZ7IuXpxKn.76BLrjGdc3iy0xzOJnvKGsBd92hJGv2', 'user', 'Mark Angelo Riza Francisco', '2025-0602', 1, 'active', '2026-03-18 12:52:10', '2026-03-18 12:52:10', NULL),
(5814, '2025-0799', 'khyn.delosreyes@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Nmn4tKGRKENBQbH3f6ykO.pgYUFZlJG/TSMb4T4V9BCkySXxv/1hu', 'user', 'Khyn Delos Reyes', '2025-0799', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5812, '2025-0773', 'johnlloyd.castillo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$CY0yw8WbQeWDgoQ6CQsWUO4AfA05qoP1QTrqYwVC3pQOWcifAQ5Ni', 'user', 'John Lloyd Castillo', '2025-0773', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5813, '2025-0616', 'jericho.delmundo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$3XLOunr2tc/G71.9oOgxoeXmc/8OUxPKXqtTJzSA/TkclLShRu1mO', 'user', 'Jericho Del Mundo', '2025-0616', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5810, '2025-0687', 'johnphilipmontillana.batarlo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$CUWd0ufKjq130V17XBBguu3hNIhEWawKpw5jkEbBcPmseTug8Sj2u', 'user', 'John Philip Montillana Batarlo', '2025-0687', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5811, '2025-0807', 'aceromar.castillo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$gU/ZYigJ/LBJ8K.llXjLmemJZgUd28NO1G40.hvqVm/fb.ylKhTJ6', 'user', 'Ace Romar Castillo', '2025-0807', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5809, '2025-0608', 'rhaizza.villanueva@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ZGsertOkaX5E1gjI8daio.LLHaq3cgoyJ4AGOuOGZ6Jo2VO7.nhzW', 'user', 'Rhaizza Villanueva', '2025-0608', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5808, '2025-0810', 'lyramae.villanueva@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$mgbHTM7ttG9qwmDmDfgXzuPmPf8Mlf/uYnoLzOdg0Zh60t/cUfAsi', 'user', 'Lyra Mae Villanueva', '2025-0810', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5807, '2025-0630', 'jonalyn.untalan@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$HJvACOP5t.Ge7.fMySZZHOcMw/AKs0Fz/7bFzR96wYsHsnlwBiK6K', 'user', 'Jonalyn Untalan', '2025-0630', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5806, '2025-0707', 'camille.tordecilla@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$bdnTFt8Vt9orE7JqikYgCuonQbkuUTU.0per8plfLGqUitIzX5yjy', 'user', 'Camille Tordecilla', '2025-0707', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5804, '2025-0792', 'ashley.mendoza@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$7AeHqsrmi4hRIG9cGt0N3OI0dEbjuXFnlYDx4npRryoNIAP1i5ZHa', 'user', 'Ashley Mendoza', '2025-0792', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5805, '2025-0761', 'anamarie.quimora@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$kU2hjqcXBcHycaqpCJ6hNOwoktrPmbhdUCrW3A1LwmogVj8hLxxfW', 'user', 'Ana Marie Quimora', '2025-0761', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5803, '2025-0704', 'keana.marquinez@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$Ub0dlWEnOPSdRiYnaba6pOeVPoMnzjbCBEE1xciahCPhdxp3q0JLe', 'user', 'Keana Marquinez', '2025-0704', 1, 'active', '2026-03-18 12:52:09', '2026-03-18 12:52:09', NULL),
(5802, '2025-0607', 'amaya.maibo@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$FKczvRPnQvheDJDaXO63wuNpT2gK4ni4/djIB/usXdZsczPn4C7Gm', 'user', 'Amaya Mañibo', '2025-0607', 1, 'active', '2026-03-18 12:52:08', '2026-03-18 12:52:08', NULL),
(5800, '2025-0714', 'kyla.jacob@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$yGQl6bU.lSQm1bC8mOv/p./1n.WcddjTXaKnZ8UchxyiYV2slyMvq', 'user', 'Kyla Jacob', '2025-0714', 1, 'active', '2026-03-18 12:52:08', '2026-03-18 12:52:08', NULL),
(5801, '2025-0706', 'kylyn.jacob@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$ZGYtHXgZYPqnUW4pEPoBee81lC4iRuPep2xXCigO3U7Wa8JjWgcbu', 'user', 'Kylyn Jacob', '2025-0706', 1, 'active', '2026-03-18 12:52:08', '2026-03-18 12:52:08', NULL),
(5798, '2025-0812', 'altheanicoleshane.dudas@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$zbS67SAJ5Oa4S/c0.QdFUekG9rpnKLSOftPg7/tFafyp7ccfwUlo.', 'user', 'Althea Nicole Shane Dudas', '2025-0812', 1, 'active', '2026-03-18 12:52:08', '2026-03-18 12:52:08', NULL),
(5799, '2025-0631', 'jasmine.gelena@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$u/4chqKvo.126YJqHANMNuquJsvpEwMaNqAA3wyyHXwKDmCrwmRx2', 'user', 'Jasmine Gelena', '2025-0631', 1, 'active', '2026-03-18 12:52:08', '2026-03-18 12:52:08', NULL),
(5797, '2025-0760', 'jerlyn.aday@colegiodenaujan.edu.ph', NULL, NULL, NULL, '$2y$10$.5N1h4RK4sYAJdDt7ruGce9qPS9Wff3k29fUtBfmTEm/H51t/hQYy', 'user', 'Jerlyn Aday', '2025-0760', 1, 'active', '2026-03-18 12:52:08', '2026-03-18 12:52:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

DROP TABLE IF EXISTS `violations`;
CREATE TABLE IF NOT EXISTS `violations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `violation_type_id` int NOT NULL,
  `violation_level_id` int NOT NULL,
  `department` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `violation_date` date NOT NULL,
  `violation_time` time NOT NULL,
  `location` enum('gate_1','gate_2','classroom','library','cafeteria','gym','others') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reported_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('permitted','warning','disciplinary','resolved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `attachments` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT '0',
  `is_read` tinyint(1) DEFAULT '0',
  `slip_requested` tinyint(1) DEFAULT '0',
  `slip_requested_at` datetime DEFAULT NULL,
  `slip_permitted` tinyint(1) DEFAULT '0',
  `slip_permitted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `case_id` (`case_id`),
  KEY `idx_case_id` (`case_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_department` (`department`),
  KEY `idx_status` (`status`),
  KEY `idx_violation_date` (`violation_date`),
  KEY `idx_violation_type` (`violation_type_id`),
  KEY `idx_violation_level` (`violation_level_id`),
  KEY `idx_is_archived` (`is_archived`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_slip_requested` (`slip_requested`),
  KEY `idx_slip_permitted` (`slip_permitted`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `violation_levels`
--

DROP TABLE IF EXISTS `violation_levels`;
CREATE TABLE IF NOT EXISTS `violation_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `violation_type_id` int NOT NULL,
  `level_order` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `violation_type_id` (`violation_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `violation_levels`
--

INSERT INTO `violation_levels` (`id`, `violation_type_id`, `level_order`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Permitted 1', 'First permitted instance', '2026-02-04 21:42:49', NULL),
(2, 1, 2, 'Permitted 2', 'Second permitted instance', '2026-02-04 21:42:49', NULL),
(3, 1, 3, 'Warning 1', 'First warning', '2026-02-04 21:42:49', NULL),
(4, 1, 4, 'Warning 2', 'Second warning', '2026-02-04 21:42:49', NULL),
(5, 1, 5, 'Warning 3', 'Final warning', '2026-02-04 21:42:49', NULL),
(6, 1, 6, 'Disciplinary Action', 'Referral to discipline office', '2026-02-04 21:42:49', NULL),
(7, 2, 1, 'Permitted 1', 'First permitted instance', '2026-02-04 21:42:49', NULL),
(8, 2, 2, 'Permitted 2', 'Second permitted instance', '2026-02-04 21:42:49', NULL),
(9, 2, 3, 'Warning 1', 'First warning', '2026-02-04 21:42:49', NULL),
(10, 2, 4, 'Warning 2', 'Second warning', '2026-02-04 21:42:49', NULL),
(11, 2, 5, 'Warning 3', 'Final warning', '2026-02-04 21:42:49', NULL),
(12, 2, 6, 'Disciplinary Action', 'Referral to discipline office', '2026-02-04 21:42:49', NULL),
(13, 3, 1, 'Permitted 1', 'First permitted instance', '2026-02-04 21:42:49', NULL),
(14, 3, 2, 'Permitted 2', 'Second permitted instance', '2026-02-04 21:42:49', NULL),
(15, 3, 3, 'Warning 1', 'First warning', '2026-02-04 21:42:49', NULL),
(16, 3, 4, 'Warning 2', 'Second warning', '2026-02-04 21:42:49', NULL),
(17, 3, 5, 'Warning 3', 'Final warning', '2026-02-04 21:42:49', NULL),
(18, 3, 6, 'Disciplinary Action', 'Referral to discipline office', '2026-02-04 21:42:49', NULL),
(19, 4, 1, 'Permitted 1', 'First permitted instance', '2026-02-04 21:42:49', NULL),
(20, 4, 2, 'Permitted 2', 'Second permitted instance', '2026-02-04 21:42:49', NULL),
(21, 4, 3, 'Warning 1', 'First warning', '2026-02-04 21:42:49', NULL),
(22, 4, 4, 'Warning 2', 'Second warning', '2026-02-04 21:42:49', NULL),
(23, 4, 5, 'Warning 3', 'Final warning', '2026-02-04 21:42:49', NULL),
(24, 4, 6, 'Disciplinary Action', 'Referral to discipline office', '2026-02-04 21:42:49', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `violation_types`
--

DROP TABLE IF EXISTS `violation_types`;
CREATE TABLE IF NOT EXISTS `violation_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `violation_types`
--

INSERT INTO `violation_types` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Improper Uniform', 'Wearing colored undershirt, improper pants, etc.', '2026-02-04 21:42:49', NULL),
(2, 'No ID', 'Failure to wear or bring student ID', '2026-02-04 21:42:49', NULL),
(3, 'Improper Footwear', 'Wearing slippers, open-toed shoes, etc.', '2026-02-04 21:42:49', NULL),
(4, 'Misconduct', 'Behavioral violations', '2026-02-04 21:42:49', NULL);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_recommendations`
--
ALTER TABLE `report_recommendations`
  ADD CONSTRAINT `fk_report_recommendations_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_violations`
--
ALTER TABLE `report_violations`
  ADD CONSTRAINT `fk_report_violations_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `violations`
--
ALTER TABLE `violations`
  ADD CONSTRAINT `fk_violations_level` FOREIGN KEY (`violation_level_id`) REFERENCES `violation_levels` (`id`),
  ADD CONSTRAINT `fk_violations_type` FOREIGN KEY (`violation_type_id`) REFERENCES `violation_types` (`id`);

--
-- Constraints for table `violation_levels`
--
ALTER TABLE `violation_levels`
  ADD CONSTRAINT `fk_violation_levels_type` FOREIGN KEY (`violation_type_id`) REFERENCES `violation_types` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

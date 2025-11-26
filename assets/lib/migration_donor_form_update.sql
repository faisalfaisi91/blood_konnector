-- =====================================================
-- Database Migration: Donor Form Update v3.0
-- =====================================================
-- This migration updates the database schema to support
-- the new comprehensive donor registration form
-- =====================================================

-- Backup recommendation: Please backup your database before running this migration!

-- =====================================================
-- 1. Update DONORS table with new fields
-- =====================================================

-- Add full_name column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'full_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN full_name VARCHAR(255) NULL AFTER user_id',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add father_name column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'father_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN father_name VARCHAR(255) NULL AFTER full_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add emergency_contacts column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'emergency_contacts');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN emergency_contacts TEXT NULL AFTER whatsapp_number',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add occupation column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'occupation');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN occupation VARCHAR(255) NULL AFTER cnic',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Handle emergency_availability column
-- Check if emergency_availability already exists
SET @exist_availability := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'emergency_availability');
-- Check if emergency_contact exists
SET @exist_contact := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'emergency_contact');

-- Only proceed if emergency_availability doesn't exist
SET @sqlstmt := IF(@exist_availability = 0 AND @exist_contact > 0, 
    'ALTER TABLE donors CHANGE COLUMN emergency_contact emergency_availability ENUM("yes", "no") DEFAULT "no"',
    IF(@exist_availability = 0 AND @exist_contact = 0,
        'ALTER TABLE donors ADD COLUMN emergency_availability ENUM("yes", "no") DEFAULT "no" AFTER contact_method',
        'SELECT 1'));
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add chronic diseases fields (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'chronic_diseases');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN chronic_diseases ENUM("yes", "no") DEFAULT "no" AFTER last_donation_date',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'chronic_diseases_details');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN chronic_diseases_details TEXT NULL AFTER chronic_diseases',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add rejected donation fields (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'rejected_donation');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN rejected_donation ENUM("yes", "no") DEFAULT "no" AFTER chronic_diseases_details',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'rejected_donation_details');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN rejected_donation_details TEXT NULL AFTER rejected_donation',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add hepatitis history fields (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'hepatitis_history');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN hepatitis_history ENUM("yes", "no") DEFAULT "no" AFTER rejected_donation_details',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'hepatitis_history_details');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN hepatitis_history_details TEXT NULL AFTER hepatitis_history',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add file upload fields (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'blood_test_report');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN blood_test_report VARCHAR(500) NULL AFTER hepatitis_history_details',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'medical_reports');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN medical_reports VARCHAR(500) NULL AFTER blood_test_report',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing records: populate full_name from first_name and last_name
UPDATE `donors` 
SET `full_name` = CONCAT(COALESCE(`first_name`, ''), ' ', COALESCE(`last_name`, ''))
WHERE `full_name` IS NULL OR `full_name` = '';

-- Migrate emergency_contact to emergency_availability if the column exists
SET @exist_emergency_contact := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'emergency_contact');
SET @sqlstmt_migrate := IF(@exist_emergency_contact > 0, 
    'UPDATE donors SET emergency_availability = CASE WHEN emergency_contact = "yes" THEN "yes" ELSE "no" END WHERE emergency_contact IS NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt_migrate;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2. Create SETTINGS table for admin configuration
-- =====================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `setting_description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default tutorial video link (admin can update this later)
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_description`) 
VALUES ('donor_tutorial_video', '#', 'YouTube video link for donor registration tutorial')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- =====================================================
-- 3. Create upload directories (Note: This is a reminder, directories should be created via PHP)
-- =====================================================
-- The following directories need to be created manually or via PHP:
-- - assets/uploads/blood_reports/
-- - assets/uploads/medical_reports/
-- These will be created automatically by the PHP code if they don't exist

-- =====================================================
-- Migration Complete
-- =====================================================

-- Verification queries (uncomment to run):
-- SHOW COLUMNS FROM donors;
-- SELECT * FROM settings WHERE setting_key = 'donor_tutorial_video';
-- SELECT COUNT(*) as total_donors FROM donors;
-- SELECT full_name, father_name, occupation, emergency_availability FROM donors LIMIT 5;


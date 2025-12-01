-- =====================================================
-- Database Migration: Recipient Form Update v2.0
-- =====================================================
-- This migration updates the database schema to support
-- the new comprehensive recipient registration form
-- =====================================================

-- Backup recommendation: Please backup your database before running this migration!

-- =====================================================
-- 1. Update RECIPIENTS table with new fields
-- =====================================================

-- Add recipient_type column (I'm recipient / I'm getting for someone else)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'recipient_type');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN recipient_type ENUM("self", "other") DEFAULT "self" AFTER user_id',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add full_name column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'full_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN full_name VARCHAR(255) NULL AFTER recipient_type',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add age column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'age');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN age INT(3) NULL AFTER full_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add gender column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'gender');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN gender ENUM("male", "female", "other") NULL AFTER age',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add whatsapp_number column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'whatsapp_number');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN whatsapp_number VARCHAR(20) NULL AFTER contact_number',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add emergency_contact column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'emergency_contact');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN emergency_contact VARCHAR(20) NULL AFTER whatsapp_number',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add home_address column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'home_address');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN home_address TEXT NULL AFTER location',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add relation_with_patient column (if not exists) - for "I'm getting for someone else"
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'relation_with_patient');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN relation_with_patient VARCHAR(100) NULL AFTER gender',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add clinic_name column (if not exists) - Hospital/Clinic Name
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'clinic_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN clinic_name VARCHAR(255) NULL AFTER hospital_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add ward_name column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'ward_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN ward_name VARCHAR(100) NULL AFTER clinic_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add ward_no column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'ward_no');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN ward_no VARCHAR(50) NULL AFTER ward_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add bed_no column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'bed_no');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN bed_no VARCHAR(50) NULL AFTER ward_no',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add hospital_phone_no column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'hospital_phone_no');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN hospital_phone_no VARCHAR(20) NULL AFTER bed_no',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add doctors_name column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'doctors_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN doctors_name VARCHAR(255) NULL AFTER hospital_phone_no',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add cause_of_blood_requirement column (if not exists) - Cause of Blood requirement
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'cause_of_blood_requirement');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN cause_of_blood_requirement TEXT NULL AFTER doctors_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update urgency_level to match new options (High within 06 hours / Normal 24 to 36 hours)
-- First check if column exists and what values it has
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'urgency_level');
SET @sqlstmt := IF(@exist > 0, 
    'ALTER TABLE recipients MODIFY COLUMN urgency_level ENUM("high", "normal") DEFAULT "normal"',
    'ALTER TABLE recipients ADD COLUMN urgency_level ENUM("high", "normal") DEFAULT "normal"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add dr_prescription column (if not exists) - Dr's Prescription file upload
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'dr_prescription');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN dr_prescription VARCHAR(500) NULL AFTER profile_pic',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add terms_accepted column (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'terms_accepted');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN terms_accepted TINYINT(1) DEFAULT 0 AFTER dr_prescription',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add info_correct column (if not exists) - I agree all information is correct
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'info_correct');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE recipients ADD COLUMN info_correct TINYINT(1) DEFAULT 0 AFTER terms_accepted',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing records: populate full_name from first_name and last_name
UPDATE `recipients` 
SET `full_name` = CONCAT(COALESCE(`first_name`, ''), ' ', COALESCE(`last_name`, ''))
WHERE `full_name` IS NULL OR `full_name` = '';

-- =====================================================
-- 2. Add recipient tutorial video link to SETTINGS table
-- =====================================================

-- Insert default recipient tutorial video link (admin can update this later)
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_description`) 
VALUES ('recipient_tutorial_video', '#', 'YouTube video link for recipient registration tutorial')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- =====================================================
-- Migration Complete
-- =====================================================

-- Verification queries (uncomment to run):
-- SHOW COLUMNS FROM recipients;
-- SELECT * FROM settings WHERE setting_key = 'recipient_tutorial_video';
-- SELECT COUNT(*) as total_recipients FROM recipients;
-- SELECT full_name, age, gender, recipient_type, relation_with_patient FROM recipients LIMIT 5;


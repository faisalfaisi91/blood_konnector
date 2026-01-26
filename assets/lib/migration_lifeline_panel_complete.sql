-- =====================================================
-- DATABASE MIGRATION: LIFELINE PANEL COMPLETE SYSTEM
-- =====================================================
-- Purpose: Create/Update database tables for LifeLine Panel system
--          with comprehensive recipient profile information
-- 
-- Date: 2026-01-09
-- =====================================================

-- =====================================================
-- 1. DROP EXISTING TABLE IF NEEDED (for fresh install)
-- =====================================================
-- Uncomment the line below if you want to drop and recreate
-- DROP TABLE IF EXISTS lifeline_profiles;

-- =====================================================
-- 2. CREATE/ALTER LIFELINE PROFILES TABLE
-- =====================================================
-- Stores comprehensive recipient profile information (filled once)

-- Check if table exists and alter it, or create new
CREATE TABLE IF NOT EXISTS lifeline_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id VARCHAR(64) NOT NULL,
    
    -- Personal Information
    full_name VARCHAR(255) NOT NULL,
    cnic_national_id VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    blood_type VARCHAR(8) NOT NULL,
    contact_number_primary VARCHAR(20) NOT NULL,
    contact_number_alternate VARCHAR(20) DEFAULT NULL,
    email_address VARCHAR(255) DEFAULT NULL,
    residential_address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    province_state VARCHAR(100) NOT NULL,
    
    -- Medical Information
    hospital_clinic_name VARCHAR(255) DEFAULT NULL,
    doctor_consultant_name VARCHAR(255) DEFAULT NULL,
    hospital_contact_number VARCHAR(20) DEFAULT NULL,
    health_condition VARCHAR(255) DEFAULT NULL,
    frequency_of_requirement ENUM('weekly', 'monthly', 'occasionally', 'on-demand') DEFAULT 'on-demand',
    average_units_per_session INT DEFAULT 1,
    preferred_donor_type ENUM('regular', 'emergency', 'any') DEFAULT 'any',
    special_instructions TEXT DEFAULT NULL,
    
    -- Emergency & Verification Details
    emergency_contact_name VARCHAR(255) NOT NULL,
    emergency_contact_relation VARCHAR(100) NOT NULL,
    emergency_contact_number VARCHAR(20) NOT NULL,
    verification_letter_path VARCHAR(255) NOT NULL,
    cnic_copy_path VARCHAR(255) NOT NULL,
    medical_proof_path VARCHAR(255) DEFAULT NULL,
    
    -- Consent & Declaration
    consent_declaration TINYINT(1) DEFAULT 0,
    signature_name VARCHAR(255) DEFAULT NULL,
    declaration_date DATE DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_recipient (recipient_id),
    UNIQUE KEY unique_cnic (cnic_national_id),
    CONSTRAINT fk_lifeline_profiles_recipient FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 3. ALTER EXISTING TABLE (if it already exists)
-- =====================================================
-- Run these ALTER statements if the table already exists with old structure

-- Add new columns if they don't exist
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'full_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN full_name VARCHAR(255) NOT NULL AFTER recipient_id',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'cnic_national_id');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN cnic_national_id VARCHAR(50) NOT NULL AFTER full_name, ADD UNIQUE KEY unique_cnic (cnic_national_id)',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'date_of_birth');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN date_of_birth DATE NOT NULL AFTER cnic_national_id',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'gender');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN gender ENUM("male", "female", "other") NOT NULL AFTER date_of_birth',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'contact_number_primary');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN contact_number_primary VARCHAR(20) NOT NULL AFTER blood_type',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'contact_number_alternate');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN contact_number_alternate VARCHAR(20) DEFAULT NULL AFTER contact_number_primary',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'email_address');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN email_address VARCHAR(255) DEFAULT NULL AFTER contact_number_alternate',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'residential_address');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN residential_address TEXT NOT NULL AFTER email_address',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'province_state');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN province_state VARCHAR(100) NOT NULL AFTER city',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Medical Information columns
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'hospital_clinic_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN hospital_clinic_name VARCHAR(255) DEFAULT NULL AFTER province_state',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'doctor_consultant_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN doctor_consultant_name VARCHAR(255) DEFAULT NULL AFTER hospital_clinic_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'hospital_contact_number');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN hospital_contact_number VARCHAR(20) DEFAULT NULL AFTER doctor_consultant_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'health_condition');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN health_condition VARCHAR(255) DEFAULT NULL AFTER hospital_contact_number',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'frequency_of_requirement');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN frequency_of_requirement ENUM("weekly", "monthly", "occasionally", "on-demand") DEFAULT "on-demand" AFTER health_condition',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'average_units_per_session');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN average_units_per_session INT DEFAULT 1 AFTER frequency_of_requirement',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'preferred_donor_type');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN preferred_donor_type ENUM("regular", "emergency", "any") DEFAULT "any" AFTER average_units_per_session',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'special_instructions');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN special_instructions TEXT DEFAULT NULL AFTER preferred_donor_type',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Emergency & Verification columns
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'emergency_contact_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN emergency_contact_name VARCHAR(255) NOT NULL AFTER special_instructions',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'emergency_contact_relation');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN emergency_contact_relation VARCHAR(100) NOT NULL AFTER emergency_contact_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'emergency_contact_number');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN emergency_contact_number VARCHAR(20) NOT NULL AFTER emergency_contact_relation',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'verification_letter_path');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN verification_letter_path VARCHAR(255) NOT NULL AFTER emergency_contact_number',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'cnic_copy_path');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN cnic_copy_path VARCHAR(255) NOT NULL AFTER verification_letter_path',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'medical_proof_path');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN medical_proof_path VARCHAR(255) DEFAULT NULL AFTER cnic_copy_path',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Consent & Declaration columns
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'consent_declaration');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN consent_declaration TINYINT(1) DEFAULT 0 AFTER medical_proof_path',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'signature_name');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN signature_name VARCHAR(255) DEFAULT NULL AFTER consent_declaration',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- If signature_path exists, migrate data and rename column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'signature_path');
SET @sqlstmt := IF(@exist > 0, 
    'ALTER TABLE lifeline_profiles DROP COLUMN signature_path',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_profiles' 
    AND COLUMN_NAME = 'declaration_date');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_profiles ADD COLUMN declaration_date DATE DEFAULT NULL AFTER signature_name',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 4. REMOVE OLD COLUMNS (if migrating from old structure)
-- =====================================================
-- Uncomment these if you had the old structure and want to remove old columns
-- ALTER TABLE lifeline_profiles DROP COLUMN IF EXISTS medical_documents_path;
-- ALTER TABLE lifeline_profiles DROP COLUMN IF EXISTS additional_info;

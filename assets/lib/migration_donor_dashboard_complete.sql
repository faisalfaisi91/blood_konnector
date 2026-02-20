-- =====================================================
-- Migration: Donor Dashboard Complete
-- Adds donor availability toggle, blood donations tracking,
-- scheduling completion flow, and related fields
-- =====================================================

-- 1. Add is_available to donors (profile visibility toggle)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'is_available');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE donors ADD COLUMN is_available TINYINT(1) DEFAULT 1 COMMENT "1=visible in list, 0=deactivated"',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. blood_donations table - tracks all donations (successful, failed, rescheduled)
CREATE TABLE IF NOT EXISTS `blood_donations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `donor_id` VARCHAR(64) NOT NULL,
    `recipient_id` VARCHAR(64) NOT NULL,
    `request_id` BIGINT UNSIGNED NULL,
    `donation_date` DATE NULL,
    `donation_time` TIME NULL,
    `location` VARCHAR(255) NULL,
    `status` ENUM('scheduled','completed','failed','rescheduled') DEFAULT 'scheduled',
    `urgency` ENUM('normal','urgent') DEFAULT 'normal',
    `donor_remarks` TEXT NULL,
    `recipient_remarks` TEXT NULL,
    `confirmed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_donor` (`donor_id`),
    INDEX `idx_recipient` (`recipient_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_donation_date` (`donation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Extend emergency_confirmations for completion flow
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'emergency_confirmations' 
    AND COLUMN_NAME = 'completion_status');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE emergency_confirmations ADD COLUMN completion_status ENUM("pending","completed","failed","rescheduled") DEFAULT "pending" AFTER countdown_start_at',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'emergency_confirmations' 
    AND COLUMN_NAME = 'donor_remarks');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE emergency_confirmations ADD COLUMN donor_remarks TEXT NULL',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'emergency_confirmations' 
    AND COLUMN_NAME = 'recipient_remarks');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE emergency_confirmations ADD COLUMN recipient_remarks TEXT NULL',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'emergency_confirmations' 
    AND COLUMN_NAME = 'completion_asked_at');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE emergency_confirmations ADD COLUMN completion_asked_at DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Ensure emergency_requests has blood_type and city for filtering
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'emergency_requests' 
    AND COLUMN_NAME = 'blood_type');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE emergency_requests ADD COLUMN blood_type VARCHAR(8) NULL AFTER recipient_id',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'emergency_requests' 
    AND COLUMN_NAME = 'city');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE emergency_requests ADD COLUMN city VARCHAR(255) NULL AFTER location',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. blood_donations: donor confirmation and completion flow
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'blood_donations' 
    AND COLUMN_NAME = 'donor_confirmed');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE blood_donations ADD COLUMN donor_confirmed TINYINT(1) DEFAULT 0 AFTER confirmed_at',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'blood_donations' 
    AND COLUMN_NAME = 'donor_confirmed_at');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE blood_donations ADD COLUMN donor_confirmed_at DATETIME NULL AFTER donor_confirmed',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'blood_donations' 
    AND COLUMN_NAME = 'completion_asked_at');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE blood_donations ADD COLUMN completion_asked_at DATETIME NULL AFTER donor_confirmed_at',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. scheduling_reminders for 1 day before, day of, 5h after completion
CREATE TABLE IF NOT EXISTS `scheduling_reminders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `blood_donation_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('1day','day_of','completion_ask') NOT NULL,
    `scheduled_for` DATETIME NOT NULL,
    `sent_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_blood_donation` (`blood_donation_id`),
    INDEX `idx_scheduled` (`scheduled_for`, `sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

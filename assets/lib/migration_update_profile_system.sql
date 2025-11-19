-- =====================================================
-- Database Migration: Profile System Update v2.0
-- =====================================================
-- This migration updates the database schema to support
-- the new profile management system with proper status fields
-- =====================================================

-- Backup recommendation: Please backup your database before running this migration!

-- =====================================================
-- 1. Update DONORS table
-- =====================================================

-- Check if 'status' column exists and rename it to avoid confusion
-- The old 'status' was used for profile switching (active/inactive)
-- We're replacing it with 'availability_status' for actual donor availability

-- Add new availability_status column if it doesn't exist
ALTER TABLE `donors` 
ADD COLUMN IF NOT EXISTS `availability_status` ENUM('available', 'busy', 'inactive') DEFAULT 'available' AFTER `about`;

-- If old 'status' column exists, migrate data and remove it
-- Note: This is safe because old 'status' was just for UI state
UPDATE `donors` SET `availability_status` = 'available' WHERE 1=1;

-- Try to drop old status column if it exists (ignore error if doesn't exist)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'donors' 
    AND COLUMN_NAME = 'status');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE donors DROP COLUMN status', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for better query performance
ALTER TABLE `donors` 
ADD INDEX IF NOT EXISTS `idx_availability_status` (`availability_status`);

-- =====================================================
-- 2. Update RECIPIENTS table
-- =====================================================

-- Rename 'status' to 'request_status' for clarity
-- This represents the status of their blood request (active/fulfilled/cancelled)

-- Add new request_status column if it doesn't exist
ALTER TABLE `recipients` 
ADD COLUMN IF NOT EXISTS `request_status` ENUM('active', 'fulfilled', 'cancelled') DEFAULT 'active' AFTER `profile_pic`;

-- Migrate data from old 'status' column if it exists
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'recipients' 
    AND COLUMN_NAME = 'status');

-- Only migrate if old status column exists
SET @sqlstmt := IF(@exist > 0, 
    "UPDATE recipients SET request_status = CASE 
        WHEN status = 'active' THEN 'active' 
        WHEN status = 'inactive' THEN 'cancelled' 
        ELSE 'active' 
    END", 
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop old status column if it exists
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE recipients DROP COLUMN status', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for better query performance
ALTER TABLE `recipients` 
ADD INDEX IF NOT EXISTS `idx_request_status` (`request_status`);

-- =====================================================
-- 3. Ensure proper indexes for user_id lookups
-- =====================================================

ALTER TABLE `donors` 
ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`);

ALTER TABLE `recipients` 
ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`);

-- =====================================================
-- 4. Update USERS table (ensure last_activity exists)
-- =====================================================

ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `users` 
ADD INDEX IF NOT EXISTS `idx_last_activity` (`last_activity`);

-- =====================================================
-- Migration Complete
-- =====================================================

-- Verification queries (uncomment to run):
-- SELECT COUNT(*) as donor_count, availability_status FROM donors GROUP BY availability_status;
-- SELECT COUNT(*) as recipient_count, request_status FROM recipients GROUP BY request_status;
-- SHOW COLUMNS FROM donors LIKE '%status%';
-- SHOW COLUMNS FROM recipients LIKE '%status%';


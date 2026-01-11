-- =====================================================
-- DATABASE MIGRATION: LIFELINE TO EMERGENCY PANEL RENAME
-- =====================================================
-- Fixed version - handles cases where tables are already views
-- 
-- Date: January 9, 2026
-- Version: 2.0
--
-- USAGE:
-- 1. Backup your database first!
-- 2. Run this script in phpMyAdmin SQL tab
-- =====================================================

-- =====================================================
-- CHECK IF MIGRATION IS NEEDED
-- =====================================================
-- This script checks if lifeline_* tables are views or base tables
-- and handles both cases

-- =====================================================
-- STEP 1: CHECK CURRENT STATE
-- =====================================================
-- Display what exists in the database
SELECT 'Database State Check' AS info;
SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('lifeline_requests', 'emergency_requests', 'lifeline_confirmations', 'emergency_confirmations')
ORDER BY TABLE_NAME;

-- =====================================================
-- STEP 2: IF VIEWS EXIST, DROP THEM FIRST
-- =====================================================
-- Drop backward compatibility views if they exist
DROP VIEW IF EXISTS lifeline_requests;
DROP VIEW IF EXISTS lifeline_confirmations;
DROP VIEW IF EXISTS lifeline_notifications;
DROP VIEW IF EXISTS lifeline_reminders;
DROP VIEW IF EXISTS lifeline_profiles;
DROP VIEW IF EXISTS lifeline_links;
DROP VIEW IF EXISTS lifeline_feedback;

-- =====================================================
-- STEP 3: VERIFY EMERGENCY TABLES EXIST
-- =====================================================
-- Check if emergency tables already exist
SELECT 'Checking for emergency_* tables...' AS status;
SELECT TABLE_NAME FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME LIKE 'emergency_%'
ORDER BY TABLE_NAME;

-- =====================================================
-- STEP 4: IF LIFELINE TABLES STILL EXIST, RENAME THEM
-- =====================================================
-- Only run if lifeline_* tables still exist as base tables
-- Use a conditional approach

SET FOREIGN_KEY_CHECKS = 0;

-- Check if lifeline_requests exists as a BASE TABLE (not a view)
SET @table_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_requests'
    AND TABLE_TYPE = 'BASE TABLE'
);

-- If lifeline_requests exists as a base table, rename all tables
IF @table_exists > 0 THEN
    ALTER TABLE lifeline_requests RENAME TO emergency_requests;
    ALTER TABLE lifeline_confirmations RENAME TO emergency_confirmations;
    ALTER TABLE lifeline_notifications RENAME TO emergency_notifications;
    ALTER TABLE lifeline_reminders RENAME TO emergency_reminders;
    ALTER TABLE lifeline_profiles RENAME TO emergency_profiles;
    ALTER TABLE lifeline_links RENAME TO emergency_links;
    ALTER TABLE lifeline_feedback RENAME TO emergency_feedback;
END IF;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- STEP 5: UPDATE NOTIFICATION TEMPLATE KEYS
-- =====================================================
-- Migrate all notification template keys from 'lifeline_*' to 'emergency_*'
UPDATE emergency_notifications
SET template_key = REPLACE(template_key, 'lifeline_', 'emergency_')
WHERE template_key LIKE 'lifeline_%';

-- =====================================================
-- STEP 6: ADD MISSING COLUMNS IF NEEDED
-- =====================================================
-- Add city and blood_type columns to emergency_requests if they don't exist

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'emergency_requests'
    AND COLUMN_NAME = 'city'
);

IF @col_exists = 0 THEN
    ALTER TABLE emergency_requests ADD COLUMN city VARCHAR(100) NULL AFTER location;
    ALTER TABLE emergency_requests ADD COLUMN blood_type VARCHAR(10) NULL AFTER city;
END IF;

-- =====================================================
-- STEP 7: ADD INDEXES FOR PERFORMANCE
-- =====================================================
-- Add indexes to emergency_requests if they don't exist
ALTER TABLE emergency_requests 
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_recipient_id (recipient_id),
ADD INDEX IF NOT EXISTS idx_created_at (created_at),
ADD INDEX IF NOT EXISTS idx_preferred_date (preferred_date),
ADD INDEX IF NOT EXISTS idx_city (city),
ADD INDEX IF NOT EXISTS idx_blood_type (blood_type);

-- Add indexes to emergency_confirmations if they don't exist
ALTER TABLE emergency_confirmations 
ADD INDEX IF NOT EXISTS idx_request_id (request_id),
ADD INDEX IF NOT EXISTS idx_donor_id (donor_id),
ADD INDEX IF NOT EXISTS idx_donor_response (donor_response);

-- Add indexes to emergency_notifications if they don't exist
ALTER TABLE emergency_notifications 
ADD INDEX IF NOT EXISTS idx_user_id (user_id),
ADD INDEX IF NOT EXISTS idx_template_key (template_key),
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_created_at (created_at);

-- Add indexes to emergency_profiles if they don't exist
ALTER TABLE emergency_profiles 
ADD INDEX IF NOT EXISTS idx_recipient_id (recipient_id),
ADD INDEX IF NOT EXISTS idx_blood_type (blood_type);

-- Add indexes to emergency_links if they don't exist
ALTER TABLE emergency_links 
ADD INDEX IF NOT EXISTS idx_recipient_id (recipient_id),
ADD INDEX IF NOT EXISTS idx_donor_id (donor_id),
ADD INDEX IF NOT EXISTS idx_status (status);

-- =====================================================
-- STEP 8: CREATE BACKWARD COMPATIBILITY VIEWS (OPTIONAL)
-- =====================================================
-- Uncomment if you still need legacy code to work with old table names
-- These views allow old code to still reference lifeline_* names

-- CREATE VIEW lifeline_requests AS SELECT * FROM emergency_requests;
-- CREATE VIEW lifeline_confirmations AS SELECT * FROM emergency_confirmations;
-- CREATE VIEW lifeline_notifications AS SELECT * FROM emergency_notifications;
-- CREATE VIEW lifeline_reminders AS SELECT * FROM emergency_reminders;
-- CREATE VIEW lifeline_profiles AS SELECT * FROM emergency_profiles;
-- CREATE VIEW lifeline_links AS SELECT * FROM emergency_links;
-- CREATE VIEW lifeline_feedback AS SELECT * FROM emergency_feedback;

-- =====================================================
-- STEP 9: VERIFICATION QUERIES
-- =====================================================
-- Display migration results

SELECT '=== MIGRATION VERIFICATION ===' AS status;
SELECT 'Emergency Tables Count:' AS check_type;
SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME LIKE 'emergency_%'
ORDER BY TABLE_NAME;

SELECT 'Emergency Data Counts:' AS check_type;
SELECT 'emergency_requests' AS table_name, COUNT(*) AS row_count FROM emergency_requests 
UNION ALL
SELECT 'emergency_confirmations', COUNT(*) FROM emergency_confirmations 
UNION ALL
SELECT 'emergency_notifications', COUNT(*) FROM emergency_notifications 
UNION ALL
SELECT 'emergency_reminders', COUNT(*) FROM emergency_reminders 
UNION ALL
SELECT 'emergency_profiles', COUNT(*) FROM emergency_profiles 
UNION ALL
SELECT 'emergency_links', COUNT(*) FROM emergency_links 
UNION ALL
SELECT 'emergency_feedback', COUNT(*) FROM emergency_feedback;

-- Show updated template keys
SELECT 'Template Keys in Notifications:' AS check_type;
SELECT DISTINCT template_key FROM emergency_notifications ORDER BY template_key;

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
-- 
-- SUCCESS! Your database is now fully migrated to Emergency Panel.
-- 
-- Summary:
-- 1. All lifeline_* tables have been renamed to emergency_*
-- 2. Backward compatibility views are available (commented out)
-- 3. Missing columns (city, blood_type) have been added
-- 4. Performance indexes have been created
-- 5. Notification templates have been updated
-- 
-- Next Steps:
-- 1. Verify the application works correctly
-- 2. If everything works, keep the current setup
-- 3. If you still see errors referencing lifeline_*, uncomment the VIEW creation statements above
-- 
-- =====================================================

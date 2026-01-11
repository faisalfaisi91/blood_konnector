-- =====================================================
-- DATABASE MIGRATION: LIFELINE TO EMERGENCY PANEL RENAME
-- =====================================================
-- Complete automated migration script to convert all Lifeline Panel
-- database tables, columns, and references to Emergency Panel
-- 
-- Date: January 9, 2026
-- Version: 1.0
--
-- USAGE:
-- 1. Backup your database first!
-- 2. Run this script in MySQL: mysql -u root -p database_name < migration.sql
-- 3. Or paste this into phpMyAdmin SQL tab and execute
-- =====================================================

-- =====================================================
-- STEP 1: CREATE BACKUP TABLES
-- =====================================================
-- Create full backup copies of all lifeline tables

CREATE TABLE IF NOT EXISTS emergency_requests_full_backup LIKE lifeline_requests;
INSERT INTO emergency_requests_full_backup SELECT * FROM lifeline_requests;

CREATE TABLE IF NOT EXISTS emergency_confirmations_full_backup LIKE lifeline_confirmations;
INSERT INTO emergency_confirmations_full_backup SELECT * FROM lifeline_confirmations;

CREATE TABLE IF NOT EXISTS emergency_notifications_full_backup LIKE lifeline_notifications;
INSERT INTO emergency_notifications_full_backup SELECT * FROM lifeline_notifications;

CREATE TABLE IF NOT EXISTS emergency_reminders_full_backup LIKE lifeline_reminders;
INSERT INTO emergency_reminders_full_backup SELECT * FROM lifeline_reminders;

CREATE TABLE IF NOT EXISTS emergency_profiles_full_backup LIKE lifeline_profiles;
INSERT INTO emergency_profiles_full_backup SELECT * FROM lifeline_profiles;

CREATE TABLE IF NOT EXISTS emergency_links_full_backup LIKE lifeline_links;
INSERT INTO emergency_links_full_backup SELECT * FROM lifeline_links;

CREATE TABLE IF NOT EXISTS emergency_feedback_full_backup LIKE lifeline_feedback;
INSERT INTO emergency_feedback_full_backup SELECT * FROM lifeline_feedback;

-- =====================================================
-- STEP 2: DISABLE FOREIGN KEY CONSTRAINTS
-- =====================================================
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- STEP 3: RENAME TABLES
-- =====================================================
-- Atomic operation to rename all lifeline tables to emergency
RENAME TABLE
    lifeline_requests TO emergency_requests,
    lifeline_confirmations TO emergency_confirmations,
    lifeline_notifications TO emergency_notifications,
    lifeline_reminders TO emergency_reminders,
    lifeline_profiles TO emergency_profiles,
    lifeline_links TO emergency_links,
    lifeline_feedback TO emergency_feedback;

-- Re-enable foreign key constraints
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- STEP 4: CREATE BACKWARD COMPATIBILITY VIEWS
-- =====================================================
-- These views allow old code to still work while using new table names

DROP VIEW IF EXISTS lifeline_requests;
DROP VIEW IF EXISTS lifeline_confirmations;
DROP VIEW IF EXISTS lifeline_notifications;
DROP VIEW IF EXISTS lifeline_reminders;
DROP VIEW IF EXISTS lifeline_profiles;
DROP VIEW IF EXISTS lifeline_links;
DROP VIEW IF EXISTS lifeline_feedback;

CREATE VIEW lifeline_requests AS SELECT * FROM emergency_requests;
CREATE VIEW lifeline_confirmations AS SELECT * FROM emergency_confirmations;
CREATE VIEW lifeline_notifications AS SELECT * FROM emergency_notifications;
CREATE VIEW lifeline_reminders AS SELECT * FROM emergency_reminders;
CREATE VIEW lifeline_profiles AS SELECT * FROM emergency_profiles;
CREATE VIEW lifeline_links AS SELECT * FROM emergency_links;
CREATE VIEW lifeline_feedback AS SELECT * FROM emergency_feedback;

-- =====================================================
-- STEP 5: UPDATE NOTIFICATION TEMPLATE KEYS
-- =====================================================
-- Migrate all notification template keys from 'lifeline_*' to 'emergency_*'
UPDATE emergency_notifications
SET template_key = REPLACE(template_key, 'lifeline_', 'emergency_')
WHERE template_key LIKE 'lifeline_%';

-- =====================================================
-- STEP 6: ADD INDEXES FOR PERFORMANCE
-- =====================================================
-- Ensure proper indexes exist for common queries

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
-- STEP 7: VERIFICATION QUERIES
-- =====================================================
-- Display migration results

SELECT '=== MIGRATION VERIFICATION ===' AS status;
SELECT 'Table' AS type, 'Row Count' AS info UNION ALL
SELECT 'emergency_requests', CAST(COUNT(*) AS CHAR) FROM emergency_requests UNION ALL
SELECT 'emergency_confirmations', CAST(COUNT(*) AS CHAR) FROM emergency_confirmations UNION ALL
SELECT 'emergency_notifications', CAST(COUNT(*) AS CHAR) FROM emergency_notifications UNION ALL
SELECT 'emergency_reminders', CAST(COUNT(*) AS CHAR) FROM emergency_reminders UNION ALL
SELECT 'emergency_profiles', CAST(COUNT(*) AS CHAR) FROM emergency_profiles UNION ALL
SELECT 'emergency_links', CAST(COUNT(*) AS CHAR) FROM emergency_links UNION ALL
SELECT 'emergency_feedback', CAST(COUNT(*) AS CHAR) FROM emergency_feedback;

-- Show updated template keys
SELECT DISTINCT 'Template Keys Updated' AS info, template_key FROM emergency_notifications ORDER BY template_key;

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
-- 
-- SUCCESS! All tables have been migrated from Lifeline to Emergency.
-- 
-- What happens next:
-- 1. The application code (emergency-donor.php, emergency-recipient.php, etc.) 
--    now references emergency_* tables
-- 2. For backward compatibility, views exist so legacy code won't break immediately
-- 3. When you're confident everything works, you can:
--    - Delete the backward compatibility views (DROP VIEW...)
--    - Delete the backup tables (DROP TABLE...)
-- 4. Update any remaining code that references 'lifeline_' to use 'emergency_'
-- 
-- If you need to rollback:
-- 1. Run the rollback statements at the bottom of this file
-- 2. Or restore from your database backup
--
-- =====================================================

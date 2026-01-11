-- =====================================================
-- COMPREHENSIVE DATABASE MIGRATION SCRIPT
-- Rename all Lifeline Panel tables to Emergency Panel
-- =====================================================
-- Purpose: Complete migration from Lifeline to Emergency terminology
-- Created: 2026-01-09
-- 
-- IMPORTANT:
-- 1) BACKUP YOUR DATABASE FIRST before running this script
-- 2) Test on a staging/dev environment first
-- 3) Run during a maintenance window
-- 4) This script creates table backups and compatibility views for backward compatibility
-- =====================================================

-- =====================================================
-- PHASE 1: BACKUP EXISTING TABLES (optional but recommended)
-- =====================================================

-- Create lightweight backups of all lifeline tables (structure only, no data)
CREATE TABLE IF NOT EXISTS emergency_requests_backup AS 
SELECT * FROM lifeline_requests WHERE 1=0;

CREATE TABLE IF NOT EXISTS emergency_confirmations_backup AS 
SELECT * FROM lifeline_confirmations WHERE 1=0;

CREATE TABLE IF NOT EXISTS emergency_notifications_backup AS 
SELECT * FROM lifeline_notifications WHERE 1=0;

CREATE TABLE IF NOT EXISTS emergency_reminders_backup AS 
SELECT * FROM lifeline_reminders WHERE 1=0;

CREATE TABLE IF NOT EXISTS emergency_profiles_backup AS 
SELECT * FROM lifeline_profiles WHERE 1=0;

CREATE TABLE IF NOT EXISTS emergency_links_backup AS 
SELECT * FROM lifeline_links WHERE 1=0;

CREATE TABLE IF NOT EXISTS emergency_feedback_backup AS 
SELECT * FROM lifeline_feedback WHERE 1=0;

-- Now populate the backups with actual data
INSERT INTO emergency_requests_backup SELECT * FROM lifeline_requests;
INSERT INTO emergency_confirmations_backup SELECT * FROM lifeline_confirmations;
INSERT INTO emergency_notifications_backup SELECT * FROM lifeline_notifications;
INSERT INTO emergency_reminders_backup SELECT * FROM lifeline_reminders;
INSERT INTO emergency_profiles_backup SELECT * FROM lifeline_profiles;
INSERT INTO emergency_links_backup SELECT * FROM lifeline_links;
INSERT INTO emergency_feedback_backup SELECT * FROM lifeline_feedback;

-- =====================================================
-- PHASE 2: DISABLE FOREIGN KEY CHECKS FOR RENAME
-- =====================================================
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- PHASE 3: RENAME ALL TABLES FROM LIFELINE TO EMERGENCY
-- =====================================================

RENAME TABLE
    lifeline_requests TO emergency_requests,
    lifeline_confirmations TO emergency_confirmations,
    lifeline_notifications TO emergency_notifications,
    lifeline_reminders TO emergency_reminders,
    lifeline_profiles TO emergency_profiles,
    lifeline_links TO emergency_links,
    lifeline_feedback TO emergency_feedback;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- PHASE 4: CREATE BACKWARD-COMPATIBILITY VIEWS
-- =====================================================
-- These views allow legacy code to continue working with old table names
-- while the actual data lives in the new emergency_* tables.
-- Remove these views once all code is updated to use emergency_* names

CREATE OR REPLACE VIEW lifeline_requests AS 
SELECT * FROM emergency_requests;

CREATE OR REPLACE VIEW lifeline_confirmations AS 
SELECT * FROM emergency_confirmations;

CREATE OR REPLACE VIEW lifeline_notifications AS 
SELECT * FROM emergency_notifications;

CREATE OR REPLACE VIEW lifeline_reminders AS 
SELECT * FROM emergency_reminders;

CREATE OR REPLACE VIEW lifeline_profiles AS 
SELECT * FROM emergency_profiles;

CREATE OR REPLACE VIEW lifeline_links AS 
SELECT * FROM emergency_links;

CREATE OR REPLACE VIEW lifeline_feedback AS 
SELECT * FROM emergency_feedback;

-- =====================================================
-- PHASE 5: MIGRATE NOTIFICATION TEMPLATE KEYS
-- =====================================================
-- Update any historical template_key values with 'lifeline_' prefix to 'emergency_'
-- Examples: 'lifeline_24h' -> 'emergency_24h', 'lifeline_new_request' -> 'emergency_new_request'

UPDATE emergency_notifications
SET template_key = REPLACE(template_key, 'lifeline_', 'emergency_')
WHERE template_key LIKE 'lifeline_%';

-- =====================================================
-- PHASE 6: VERIFICATION QUERIES
-- =====================================================
-- Run these queries to verify the migration was successful

SELECT 'Verification Summary' AS check_type, COUNT(*) AS count_value 
FROM emergency_requests 
UNION ALL 
SELECT 'Emergency Requests Count', COUNT(*) 
FROM emergency_requests 
UNION ALL 
SELECT 'Emergency Confirmations Count', COUNT(*) 
FROM emergency_confirmations 
UNION ALL 
SELECT 'Emergency Notifications Count', COUNT(*) 
FROM emergency_notifications 
UNION ALL 
SELECT 'Emergency Reminders Count', COUNT(*) 
FROM emergency_reminders 
UNION ALL 
SELECT 'Emergency Profiles Count', COUNT(*) 
FROM emergency_profiles 
UNION ALL 
SELECT 'Emergency Links Count', COUNT(*) 
FROM emergency_links 
UNION ALL 
SELECT 'Emergency Feedback Count', COUNT(*) 
FROM emergency_feedback;

-- Verify that all template keys have been updated
SELECT template_key, COUNT(*) AS count 
FROM emergency_notifications 
GROUP BY template_key 
ORDER BY template_key;

-- Check that views exist and are accessible
SELECT 'lifeline_requests view' AS view_name, COUNT(*) AS row_count 
FROM lifeline_requests 
UNION ALL 
SELECT 'lifeline_confirmations view', COUNT(*) 
FROM lifeline_confirmations 
UNION ALL 
SELECT 'lifeline_notifications view', COUNT(*) 
FROM lifeline_notifications;

-- =====================================================
-- PHASE 7: OPTIONAL CLEANUP (Manual - do later)
-- =====================================================
-- Once you've confirmed the application works with emergency_* tables,
-- you can remove the backward-compatibility views:
--
-- DROP VIEW IF EXISTS lifeline_requests;
-- DROP VIEW IF EXISTS lifeline_confirmations;
-- DROP VIEW IF EXISTS lifeline_notifications;
-- DROP VIEW IF EXISTS lifeline_reminders;
-- DROP VIEW IF EXISTS lifeline_profiles;
-- DROP VIEW IF EXISTS lifeline_links;
-- DROP VIEW IF EXISTS lifeline_feedback;
--
-- And optionally drop the backup tables (be very careful with this):
--
-- DROP TABLE IF EXISTS emergency_requests_backup;
-- DROP TABLE IF EXISTS emergency_confirmations_backup;
-- DROP TABLE IF EXISTS emergency_notifications_backup;
-- DROP TABLE IF EXISTS emergency_reminders_backup;
-- DROP TABLE IF EXISTS emergency_profiles_backup;
-- DROP TABLE IF EXISTS emergency_links_backup;
-- DROP TABLE IF EXISTS emergency_feedback_backup;

-- =====================================================
-- ROLLBACK INSTRUCTIONS (if something goes wrong)
-- =====================================================
-- If you need to rollback:
--
-- 1. Stop the application immediately
-- 2. Restore from your database backup (mysqldump or export file)
-- 3. Or if you want to rename back:
--
-- SET FOREIGN_KEY_CHECKS = 0;
-- RENAME TABLE
--     emergency_requests TO lifeline_requests,
--     emergency_confirmations TO lifeline_confirmations,
--     emergency_notifications TO lifeline_notifications,
--     emergency_reminders TO lifeline_reminders,
--     emergency_profiles TO lifeline_profiles,
--     emergency_links TO lifeline_links,
--     emergency_feedback TO lifeline_feedback;
-- SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- END OF MIGRATION SCRIPT
-- =====================================================
-- Migration completed successfully!
-- All lifeline_* tables have been renamed to emergency_*
-- Backward-compatibility views are in place for existing code
-- Please update your application code to use emergency_* table names
-- =====================================================

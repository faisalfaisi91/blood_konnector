-- =====================================================
-- DELETE ALL LIFELINE PANEL RELATED DATABASE OBJECTS
-- =====================================================
-- Purpose: Remove all unused lifeline-related tables, views, and backups
--          after migration to Emergency Panel
-- 
-- IMPORTANT WARNINGS:
-- 1. BACKUP YOUR DATABASE FIRST! This script is irreversible.
-- 2. Verify that your application is fully using emergency_* tables
-- 3. Test on a staging/dev environment first
-- 4. Run during a maintenance window
-- 
-- Date: 2026-01-09
-- =====================================================

-- =====================================================
-- STEP 1: DROP BACKWARD COMPATIBILITY VIEWS
-- =====================================================
-- These views were created to allow legacy code to work temporarily
-- If they exist, drop them now

DROP VIEW IF EXISTS lifeline_requests;
DROP VIEW IF EXISTS lifeline_confirmations;
DROP VIEW IF EXISTS lifeline_notifications;
DROP VIEW IF EXISTS lifeline_reminders;
DROP VIEW IF EXISTS lifeline_profiles;
DROP VIEW IF EXISTS lifeline_links;
DROP VIEW IF EXISTS lifeline_feedback;

-- =====================================================
-- STEP 2: DROP LIFELINE BACKUP TABLES
-- =====================================================
-- These backup tables were created during migration

DROP TABLE IF EXISTS lifeline_requests_bak;
DROP TABLE IF EXISTS lifeline_confirmations_bak;
DROP TABLE IF EXISTS lifeline_notifications_bak;
DROP TABLE IF EXISTS lifeline_reminders_bak;
DROP TABLE IF EXISTS lifeline_profiles_bak;
DROP TABLE IF EXISTS lifeline_links_bak;
DROP TABLE IF EXISTS lifeline_feedback_bak;

-- =====================================================
-- STEP 3: DROP EMERGENCY BACKUP TABLES (if created during migration)
-- =====================================================
-- These were created as backups during the migration process

DROP TABLE IF EXISTS emergency_requests_full_backup;
DROP TABLE IF EXISTS emergency_confirmations_full_backup;
DROP TABLE IF EXISTS emergency_notifications_full_backup;
DROP TABLE IF EXISTS emergency_reminders_full_backup;
DROP TABLE IF EXISTS emergency_profiles_full_backup;
DROP TABLE IF EXISTS emergency_links_full_backup;
DROP TABLE IF EXISTS emergency_feedback_full_backup;

DROP TABLE IF EXISTS emergency_requests_backup;
DROP TABLE IF EXISTS emergency_confirmations_backup;
DROP TABLE IF EXISTS emergency_notifications_backup;
DROP TABLE IF EXISTS emergency_reminders_backup;
DROP TABLE IF EXISTS emergency_profiles_backup;
DROP TABLE IF EXISTS emergency_links_backup;
DROP TABLE IF EXISTS emergency_feedback_backup;

-- =====================================================
-- STEP 4: DROP ACTUAL LIFELINE TABLES (if they still exist)
-- =====================================================
-- These should have been renamed to emergency_* tables,
-- but if they still exist for some reason, drop them
-- WARNING: Only run this if you're absolutely sure the tables
--          were renamed and you don't need the data

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS lifeline_requests;
DROP TABLE IF EXISTS lifeline_confirmations;
DROP TABLE IF EXISTS lifeline_notifications;
DROP TABLE IF EXISTS lifeline_reminders;
DROP TABLE IF EXISTS lifeline_profiles;
DROP TABLE IF EXISTS lifeline_links;
DROP TABLE IF EXISTS lifeline_feedback;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- STEP 5: VERIFICATION QUERIES
-- =====================================================
-- Run these queries to verify all lifeline objects are removed

-- Check for any remaining lifeline views
SELECT 
    TABLE_NAME AS 'Remaining Lifeline Views'
FROM 
    INFORMATION_SCHEMA.VIEWS 
WHERE 
    TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME LIKE 'lifeline_%';

-- Check for any remaining lifeline tables
SELECT 
    TABLE_NAME AS 'Remaining Lifeline Tables'
FROM 
    INFORMATION_SCHEMA.TABLES 
WHERE 
    TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME LIKE 'lifeline_%';

-- Check for any remaining emergency backup tables
SELECT 
    TABLE_NAME AS 'Remaining Emergency Backup Tables'
FROM 
    INFORMATION_SCHEMA.TABLES 
WHERE 
    TABLE_SCHEMA = DATABASE() 
    AND (TABLE_NAME LIKE '%_backup' OR TABLE_NAME LIKE '%_bak')
    AND TABLE_NAME LIKE 'emergency_%';

-- =====================================================
-- CLEANUP COMPLETE
-- =====================================================
-- 
-- All lifeline-related database objects have been removed.
-- 
-- If the verification queries above return any results,
-- those objects still exist and may need manual removal.
-- 
-- Your emergency_* tables should remain intact and functional.
-- =====================================================

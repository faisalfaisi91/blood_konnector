-- migration_rename_lifeline_to_emergency.sql
-- Purpose: Rename lifeline_* tables to emergency_* and migrate notification template_key prefixes
-- IMPORTANT:
-- 1) BACKUP the database first (use mysqldump or phpMyAdmin Export). Do NOT skip backups.
-- 2) Test on a staging copy first. Prefer running during maintenance window.
-- 3) This script creates backups (lightweight copy tables) and compatibility views so existing code keeps working
--    until you fully update code references to use emergency_* names.

-- -------------------------------
-- OPTIONAL: Create quick table backups (only if backup tables do not already exist)
-- -------------------------------
CREATE TABLE IF NOT EXISTS lifeline_requests_bak AS SELECT * FROM lifeline_requests LIMIT 0;
INSERT INTO lifeline_requests_bak SELECT * FROM lifeline_requests;

CREATE TABLE IF NOT EXISTS lifeline_confirmations_bak AS SELECT * FROM lifeline_confirmations LIMIT 0;
INSERT INTO lifeline_confirmations_bak SELECT * FROM lifeline_confirmations;

CREATE TABLE IF NOT EXISTS lifeline_notifications_bak AS SELECT * FROM lifeline_notifications LIMIT 0;
INSERT INTO lifeline_notifications_bak SELECT * FROM lifeline_notifications;

CREATE TABLE IF NOT EXISTS lifeline_reminders_bak AS SELECT * FROM lifeline_reminders LIMIT 0;
INSERT INTO lifeline_reminders_bak SELECT * FROM lifeline_reminders;

CREATE TABLE IF NOT EXISTS lifeline_profiles_bak AS SELECT * FROM lifeline_profiles LIMIT 0;
INSERT INTO lifeline_profiles_bak SELECT * FROM lifeline_profiles;

CREATE TABLE IF NOT EXISTS lifeline_links_bak AS SELECT * FROM lifeline_links LIMIT 0;
INSERT INTO lifeline_links_bak SELECT * FROM lifeline_links;

CREATE TABLE IF NOT EXISTS lifeline_feedback_bak AS SELECT * FROM lifeline_feedback LIMIT 0;
INSERT INTO lifeline_feedback_bak SELECT * FROM lifeline_feedback;

-- -------------------------------
-- Step 1: Disable foreign key checks for rename
-- -------------------------------
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------
-- Step 2: Rename tables (atomic operation)
-- -------------------------------
RENAME TABLE
    lifeline_requests TO emergency_requests,
    lifeline_confirmations TO emergency_confirmations,
    lifeline_notifications TO emergency_notifications,
    lifeline_reminders TO emergency_reminders,
    lifeline_profiles TO emergency_profiles,
    lifeline_links TO emergency_links,
    lifeline_feedback TO emergency_feedback;

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------
-- Step 3: Create compatibility VIEWS with the old names so legacy code continues to work
-- NOTE: Keep these temporarily while you update the application code; later remove them.
-- -------------------------------
CREATE OR REPLACE VIEW lifeline_requests AS SELECT * FROM emergency_requests;
CREATE OR REPLACE VIEW lifeline_confirmations AS SELECT * FROM emergency_confirmations;
CREATE OR REPLACE VIEW lifeline_notifications AS SELECT * FROM emergency_notifications;
CREATE OR REPLACE VIEW lifeline_reminders AS SELECT * FROM emergency_reminders;
CREATE OR REPLACE VIEW lifeline_profiles AS SELECT * FROM emergency_profiles;
CREATE OR REPLACE VIEW lifeline_links AS SELECT * FROM emergency_links;
CREATE OR REPLACE VIEW lifeline_feedback AS SELECT * FROM emergency_feedback;

-- -------------------------------
-- Step 4: Migrate old template_key values (optional but recommended)
-- This converts historical template_key values like 'lifeline_24h' -> 'emergency_24h'
-- -------------------------------
UPDATE emergency_notifications
SET template_key = REPLACE(template_key, 'lifeline_', 'emergency_')
WHERE template_key LIKE 'lifeline_%';

-- -------------------------------
-- Step 5: Verification queries (run after the script completes)
-- You can run these in phpMyAdmin to sanity-check results
-- -------------------------------
SELECT 'counts' AS type, 'emergency_requests' AS table_name, COUNT(*) AS rows FROM emergency_requests;
SELECT 'counts' AS type, 'emergency_notifications' AS table_name, COUNT(*) AS rows FROM emergency_notifications;
SELECT COUNT(*) AS migrated_notification_templates FROM emergency_notifications WHERE template_key LIKE 'emergency_%';

-- -------------------------------
-- Rollback notes:
-- - If you must rollback quickly, stop the app, then RENAME TABLE back to original names
--     RENAME TABLE emergency_requests TO lifeline_requests, emergency_confirmations TO lifeline_confirmations, ...;
-- - Or restore from the SQL dump created before starting this migration.

-- -------------------------------
-- Final cleanup (manual):
-- - Once you confirm the app works with emergency_* names, remove the compatibility VIEWS:
--     DROP VIEW IF EXISTS lifeline_requests;
--     ...
-- - Remove backup tables if you no longer need them (they may be large):
--     DROP TABLE IF EXISTS lifeline_requests_bak;
--     ...
-- -------------------------------

-- End of migration file

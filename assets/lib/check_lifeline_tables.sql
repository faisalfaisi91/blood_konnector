-- =====================================================
-- CHECK LIFELINE TABLES BEFORE DELETION
-- =====================================================
-- Purpose: Check what lifeline-related database objects exist
--          before running the deletion script
-- 
-- Run this script first to see what will be deleted
-- =====================================================

-- Check for lifeline views
SELECT 
    'VIEW' AS object_type,
    TABLE_NAME AS object_name,
    'Backward compatibility view - safe to delete' AS description
FROM 
    INFORMATION_SCHEMA.VIEWS 
WHERE 
    TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME LIKE 'lifeline_%'
ORDER BY TABLE_NAME;

-- Check for lifeline backup tables
SELECT 
    'TABLE' AS object_type,
    TABLE_NAME AS object_name,
    'Backup table created during migration - safe to delete' AS description
FROM 
    INFORMATION_SCHEMA.TABLES 
WHERE 
    TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME LIKE 'lifeline_%_bak'
ORDER BY TABLE_NAME;

-- Check for emergency backup tables
SELECT 
    'TABLE' AS object_type,
    TABLE_NAME AS object_name,
    'Emergency backup table created during migration - safe to delete' AS description
FROM 
    INFORMATION_SCHEMA.TABLES 
WHERE 
    TABLE_SCHEMA = DATABASE() 
    AND (TABLE_NAME LIKE '%_backup' OR TABLE_NAME LIKE '%_bak')
    AND TABLE_NAME LIKE 'emergency_%'
ORDER BY TABLE_NAME;

-- Check for actual lifeline tables (should not exist if migration was successful)
SELECT 
    'TABLE' AS object_type,
    TABLE_NAME AS object_name,
    'WARNING: Actual lifeline table - verify migration was successful before deleting!' AS description
FROM 
    INFORMATION_SCHEMA.TABLES 
WHERE 
    TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME LIKE 'lifeline_%'
    AND TABLE_NAME NOT LIKE '%_bak'
ORDER BY TABLE_NAME;

-- Summary count
SELECT 
    'SUMMARY' AS object_type,
    CONCAT(
        'Total lifeline views: ', 
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'lifeline_%'),
        ' | Total lifeline backup tables: ',
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'lifeline_%_bak'),
        ' | Total emergency backup tables: ',
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND (TABLE_NAME LIKE '%_backup' OR TABLE_NAME LIKE '%_bak') AND TABLE_NAME LIKE 'emergency_%'),
        ' | Total actual lifeline tables: ',
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'lifeline_%' AND TABLE_NAME NOT LIKE '%_bak')
    ) AS object_name,
    'Review the results above before running delete script' AS description;

-- =====================================================
-- Database Migration: Add City Column to lifeline_requests
-- =====================================================
-- This migration adds a city column to lifeline_requests table
-- for better donor matching based on city location
-- =====================================================

-- Backup recommendation: Please backup your database before running this migration!

-- Add city column to lifeline_requests table if it doesn't exist
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_requests' 
    AND COLUMN_NAME = 'city');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE lifeline_requests ADD COLUMN city VARCHAR(100) NULL AFTER location',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add blood_type column to lifeline_requests table if it doesn't exist
SET @exist_blood := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_requests' 
    AND COLUMN_NAME = 'blood_type');
SET @sqlstmt_blood := IF(@exist_blood = 0, 
    'ALTER TABLE lifeline_requests ADD COLUMN blood_type VARCHAR(10) NULL AFTER city',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt_blood;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for better query performance on city matching
SET @exist_index := (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_requests' 
    AND INDEX_NAME = 'idx_city');
SET @sqlstmt_index := IF(@exist_index = 0, 
    'ALTER TABLE lifeline_requests ADD INDEX idx_city (city)',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for better query performance on blood_type matching
SET @exist_blood_index := (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lifeline_requests' 
    AND INDEX_NAME = 'idx_blood_type');
SET @sqlstmt_blood_index := IF(@exist_blood_index = 0, 
    'ALTER TABLE lifeline_requests ADD INDEX idx_blood_type (blood_type)',
    'SELECT 1');
PREPARE stmt FROM @sqlstmt_blood_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


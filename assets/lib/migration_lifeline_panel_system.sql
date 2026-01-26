-- =====================================================
-- DATABASE MIGRATION: LIFELINE PANEL SYSTEM
-- =====================================================
-- Purpose: Create database tables for LifeLine Panel system
--          This is a separate system from Emergency Panel
-- 
-- Date: 2026-01-09
-- =====================================================

-- =====================================================
-- 1. LIFELINE PROFILES TABLE
-- =====================================================
-- Stores recipient profile information (filled once)
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
    signature_path VARCHAR(255) DEFAULT NULL,
    declaration_date DATE DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_recipient (recipient_id),
    UNIQUE KEY unique_cnic (cnic_national_id),
    CONSTRAINT fk_lifeline_profiles_recipient FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 2. LIFELINE REQUESTS TABLE
-- =====================================================
-- Stores blood donation requests
CREATE TABLE IF NOT EXISTS lifeline_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id VARCHAR(64) NOT NULL,
    blood_type VARCHAR(8) NOT NULL,
    city VARCHAR(100) NOT NULL,
    urgency ENUM('low','normal','high','critical') DEFAULT 'normal',
    note TEXT DEFAULT NULL,
    status ENUM('pending','accepted','completed','cancelled') DEFAULT 'pending',
    accepted_donor_id VARCHAR(64) DEFAULT NULL,
    accepted_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lifeline_requests_recipient FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_lifeline_requests_donor FOREIGN KEY (accepted_donor_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_blood_city (blood_type, city),
    INDEX idx_recipient (recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 3. LIFELINE NOTIFICATIONS TABLE
-- =====================================================
-- Stores notifications sent to donors about new requests
CREATE TABLE IF NOT EXISTS lifeline_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    donor_id VARCHAR(64) NOT NULL,
    status ENUM('sent','read','accepted','declined') DEFAULT 'sent',
    read_at DATETIME DEFAULT NULL,
    responded_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lifeline_notifications_request FOREIGN KEY (request_id) REFERENCES lifeline_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_lifeline_notifications_donor FOREIGN KEY (donor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_donor_status (donor_id, status),
    INDEX idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 4. LIFELINE DONOR RESPONSES TABLE
-- =====================================================
-- Stores donor responses to requests
CREATE TABLE IF NOT EXISTS lifeline_donor_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    donor_id VARCHAR(64) NOT NULL,
    response ENUM('accept','decline') NOT NULL,
    message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lifeline_responses_request FOREIGN KEY (request_id) REFERENCES lifeline_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_lifeline_responses_donor FOREIGN KEY (donor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_donor_request (request_id, donor_id),
    INDEX idx_donor (donor_id),
    INDEX idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

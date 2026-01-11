-- Emergency Panel Phase 2 schema
-- Run in MySQL 8+ (requires utf8mb4)

CREATE TABLE IF NOT EXISTS emergency_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id VARCHAR(64) NOT NULL,
    blood_type VARCHAR(8) NOT NULL,
    hospital_preference VARCHAR(255) DEFAULT NULL,
    frequency ENUM('weekly','monthly','on-demand') DEFAULT 'on-demand',
    medical_documents_path VARCHAR(255) DEFAULT NULL,
    verified_by VARCHAR(64) DEFAULT NULL,
    health_notes TEXT DEFAULT NULL,
    reliability_score DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_emergency_profiles_recipient FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emergency_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id VARCHAR(64) NOT NULL,
    donor_id VARCHAR(64) NOT NULL,
    status ENUM('active','paused','blocked') DEFAULT 'active',
    reliability_score_cache DECIMAL(5,2) DEFAULT 0,
    last_donation_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_link (recipient_id, donor_id),
    CONSTRAINT fk_emergency_links_recipient FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_emergency_links_donor FOREIGN KEY (donor_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emergency_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id VARCHAR(64) NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    urgency ENUM('low','normal','high','critical') DEFAULT 'normal',
    note TEXT DEFAULT NULL,
    status ENUM('pending','confirmed','completed','failed','rescheduled','expired') DEFAULT 'pending',
    responder_timeout_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_emergency_requests_recipient FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emergency_confirmations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    recipient_confirmed TINYINT(1) DEFAULT 0,
    recipient_confirmed_at DATETIME DEFAULT NULL,
    donor_id VARCHAR(64) DEFAULT NULL,
    donor_response ENUM('approve','decline','reschedule','timeout') DEFAULT NULL,
    donor_response_at DATETIME DEFAULT NULL,
    reschedule_payload JSON DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,
    countdown_start_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_emergency_confirmations_request FOREIGN KEY (request_id) REFERENCES emergency_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_emergency_confirmations_donor FOREIGN KEY (donor_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emergency_reminders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    type ENUM('24h','6h','1h','final_timeout') NOT NULL,
    scheduled_for DATETIME NOT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_emergency_reminders_request FOREIGN KEY (request_id) REFERENCES emergency_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emergency_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    from_user_id VARCHAR(64) NOT NULL,
    to_user_id VARCHAR(64) NOT NULL,
    role ENUM('donor','recipient') NOT NULL,
    rating TINYINT UNSIGNED CHECK (rating BETWEEN 1 AND 5),
    remarks VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_emergency_feedback_request FOREIGN KEY (request_id) REFERENCES emergency_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_emergency_feedback_from FOREIGN KEY (from_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_emergency_feedback_to FOREIGN KEY (to_user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emergency_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    channel ENUM('in_app','email','sms','push') NOT NULL DEFAULT 'in_app',
    template_key VARCHAR(64) NOT NULL,
    payload JSON DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    status ENUM('queued','sent','failed') DEFAULT 'queued',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_emergency_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Super Admin table for secure authentication
CREATE TABLE IF NOT EXISTS super_admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert an initial super admin (replace hash with your own bcrypt hash)
-- Example to generate: php -r "echo password_hash('ChangeMe123!', PASSWORD_BCRYPT), \"\n\";"
-- INSERT INTO super_admins (username, password_hash, full_name, email) VALUES ('superadmin', '$2y$10$REPLACE_ME_WITH_REAL_HASH', 'Main Admin', 'admin@example.com');


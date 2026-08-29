-- Users table for authentication and authorization
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'teknisi', 'operator') NOT NULL DEFAULT 'operator',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    last_login DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Role permissions mapping
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin', 'teknisi', 'operator') NOT NULL,
    permission VARCHAR(50) NOT NULL,
    UNIQUE KEY unique_role_permission (role, permission)
) ENGINE=InnoDB;

-- Insert default permissions for each role
-- Admin: full access
INSERT IGNORE INTO role_permissions (role, permission) VALUES
('admin', 'dashboard'),
('admin', 'vouchers'),
('admin', 'packages'),
('admin', 'hotspot_users'),
('admin', 'pppoe_profiles'),
('admin', 'pppoe_users'),
('admin', 'billing'),
('admin', 'billing_settings'),
('admin', 'reports'),
('admin', 'users'),
('admin', 'settings'),
('admin', 'monitoring');

-- Teknisi: limited access (no billing settings, no user management)
INSERT IGNORE INTO role_permissions (role, permission) VALUES
('teknisi', 'dashboard'),
('teknisi', 'vouchers'),
('teknisi', 'packages'),
('teknisi', 'hotspot_users'),
('teknisi', 'pppoe_profiles'),
('teknisi', 'pppoe_users'),
('teknisi', 'reports'),
('teknisi', 'monitoring');

-- Operator: basic access (vouchers, users, billing view)
INSERT IGNORE INTO role_permissions (role, permission) VALUES
('operator', 'dashboard'),
('operator', 'vouchers'),
('operator', 'hotspot_users'),
('operator', 'pppoe_users'),
('operator', 'billing'),
('operator', 'monitoring');

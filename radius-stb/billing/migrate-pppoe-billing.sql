-- Step 1: Add customer fields to pppoe_users
ALTER TABLE pppoe_users
    ADD COLUMN nama_lengkap VARCHAR(100) DEFAULT NULL AFTER password,
    ADD COLUMN alamat TEXT DEFAULT NULL AFTER nama_lengkap,
    ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER alamat,
    ADD COLUMN billing_type ENUM('prepaid','postpaid') DEFAULT 'postpaid' AFTER phone,
    ADD COLUMN billing_date INT DEFAULT 1 AFTER billing_type,
    ADD COLUMN isolir_status ENUM('active','isolir') DEFAULT 'active' AFTER billing_date,
    ADD COLUMN isolir_date DATETIME DEFAULT NULL AFTER isolir_status,
    ADD COLUMN last_paid_date DATE DEFAULT NULL AFTER isolir_date;

-- Step 2: Create pppoe_billing table (tagihan bulanan)
CREATE TABLE IF NOT EXISTS pppoe_billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    billing_period VARCHAR(7) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date DATE NOT NULL,
    status ENUM('unpaid','paid','overdue','isolir') DEFAULT 'unpaid',
    paid_amount DECIMAL(12,2) DEFAULT 0,
    paid_date DATETIME DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES pppoe_users(id) ON DELETE CASCADE
);

-- Step 3: Create pppoe_payments table (riwayat bayar)
CREATE TABLE IF NOT EXISTS pppoe_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    billing_id INT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','transfer','qris','ewallet','other') DEFAULT 'cash',
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,
    received_by VARCHAR(64) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES pppoe_users(id) ON DELETE CASCADE,
    FOREIGN KEY (billing_id) REFERENCES pppoe_billing(id) ON DELETE SET NULL
);

-- Step 4: Create whatsapp_settings table
CREATE TABLE IF NOT EXISTS whatsapp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_url VARCHAR(255) DEFAULT NULL,
    api_key VARCHAR(255) DEFAULT NULL,
    sender_phone VARCHAR(20) DEFAULT NULL,
    provider ENUM('fonnte','wablas','manual') DEFAULT 'fonnte',
    is_active TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Step 5: Add isolir group to FreeRADIUS
INSERT IGNORE INTO radgroupreply (groupname, attribute, op, value) VALUES
('pppoe-isolir', 'Reply-Message', ':=', 'Akun anda sedang diisolir. Silahkan bayar tagihan anda.');

-- Step 6: Insert default whatsapp settings
INSERT IGNORE INTO whatsapp_settings (id, api_url, api_key, sender_phone, provider, is_active) VALUES
(1, NULL, NULL, NULL, 'fonnte', 0);

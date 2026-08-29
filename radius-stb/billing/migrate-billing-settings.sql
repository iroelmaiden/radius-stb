CREATE TABLE IF NOT EXISTS billing_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default values
INSERT INTO billing_settings (setting_key, setting_value) VALUES
('billing_day', '20'),
('invoice_generate_days_before', '7'),
('overdue_grace_days', '3'),
('reminder_days_before', '2'),
('isolir_time', '00:15:00'),
('notify_invoice_issued', '0'),
('notify_payment_status', '1'),
('notify_member_status', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

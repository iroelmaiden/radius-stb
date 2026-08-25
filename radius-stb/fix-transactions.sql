ALTER TABLE transactions
    ADD COLUMN username VARCHAR(64) DEFAULT NULL AFTER voucher_id,
    ADD COLUMN package_name VARCHAR(100) DEFAULT NULL AFTER username,
    ADD COLUMN selling_price DECIMAL(12,2) DEFAULT 0 AFTER package_name,
    ADD COLUMN buyer_contact VARCHAR(20) DEFAULT NULL AFTER buyer_name,
    ADD COLUMN payment_method VARCHAR(20) DEFAULT 'cash' AFTER buyer_contact;

UPDATE transactions t
JOIN vouchers v ON t.voucher_id = v.id
SET t.username = v.username,
    t.selling_price = t.price_sold
WHERE t.username IS NULL;

SHOW COLUMNS FROM transactions;

ALTER TABLE vouchers MODIFY COLUMN status ENUM('available','sold','used','expired') DEFAULT 'available';
SHOW COLUMNS FROM vouchers WHERE Field = 'status';

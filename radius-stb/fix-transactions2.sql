UPDATE transactions t
JOIN vouchers v ON t.voucher_id = v.id
JOIN voucher_packages p ON v.package_id = p.id
SET t.package_name = p.name, t.username = v.username
WHERE t.package_name IS NULL OR t.username IS NULL;

SELECT t.id, t.username, t.package_name, t.selling_price, t.buyer_name, t.created_at FROM transactions t ORDER BY t.id DESC LIMIT 10;

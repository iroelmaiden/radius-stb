SELECT id, code, status, sold_at, selling_price FROM vouchers WHERE status IN ('sold','used') ORDER BY id DESC LIMIT 10;
SELECT COUNT(*) as total_trans FROM transactions;

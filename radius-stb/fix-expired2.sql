-- Fix existing expired vouchers: replace Auth-Type with Cleartext-Password dummy
DELETE FROM radcheck WHERE username IN (SELECT username FROM vouchers WHERE status = 'expired') AND attribute = 'Auth-Type';
DELETE FROM radcheck WHERE username IN (SELECT username FROM vouchers WHERE status = 'expired') AND attribute = 'Cleartext-Password';

INSERT INTO radcheck (username, attribute, op, value)
SELECT username, 'Cleartext-Password', ':=', CONCAT('EXPIRED_', username)
FROM vouchers WHERE status = 'expired';

-- Ensure Reply-Message exists
INSERT IGNORE INTO radreply (username, attribute, op, value)
SELECT username, 'Reply-Message', ':=', 'Voucher sudah expired. Silakan beli voucher baru.'
FROM vouchers WHERE status = 'expired' AND username NOT IN (SELECT username FROM radreply WHERE attribute = 'Reply-Message');

SELECT v.username, v.status, r.attribute, r.value FROM vouchers v JOIN radcheck r ON v.username = r.username WHERE v.status = 'expired';

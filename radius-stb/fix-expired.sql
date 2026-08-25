-- Fix existing expired vouchers that don't have Reply-Message
INSERT IGNORE INTO radcheck (username, attribute, op, value)
SELECT v.username, 'Auth-Type', ':=', 'Reject'
FROM vouchers v
LEFT JOIN radcheck r ON v.username = r.username AND r.attribute = 'Auth-Type'
WHERE v.status = 'expired' AND r.username IS NULL;

INSERT IGNORE INTO radreply (username, attribute, op, value)
SELECT v.username, 'Reply-Message', ':=', 'Voucher sudah expired. Silakan beli voucher baru.'
FROM vouchers v
LEFT JOIN radreply r ON v.username = r.username AND r.attribute = 'Reply-Message'
WHERE v.status = 'expired' AND r.username IS NULL;

SELECT v.username, v.status, r.attribute, r.value 
FROM vouchers v 
LEFT JOIN radreply r ON v.username = r.username AND r.attribute = 'Reply-Message'
WHERE v.status = 'expired';

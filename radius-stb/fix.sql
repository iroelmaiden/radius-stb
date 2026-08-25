UPDATE radcheck SET value = CONCAT('EXPIRED_', username) WHERE value = 'EXPIRED_$USER_NAME';
SELECT username, attribute, value FROM radcheck WHERE username IN (SELECT username FROM vouchers WHERE status = 'expired');

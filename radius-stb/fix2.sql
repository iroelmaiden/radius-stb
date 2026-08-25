UPDATE radcheck SET value = CONCAT('EXPIRED_', username) WHERE value LIKE 'EXPIRED_%';
SELECT username, attribute, value FROM radcheck WHERE value LIKE 'EXPIRED_%';

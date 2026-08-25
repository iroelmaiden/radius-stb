#!/usr/bin/env bash

echo "=== 1. Tambah accounting start query ==="
python3 -c "
with open('/etc/freeradius/3.0/mods-config/sql/main/mysql/queries.conf','r') as f:
    c = f.read()

# Cari section 'start' dan tambah query setelah insert
start_marker = 'start {'
idx = c.find(start_marker)
if idx == -1:
    print('start section not found')
else:
    # Cari posisi insert query
    insert_end = c.find('VALUES', idx)
    insert_end = c.find(')', insert_end) + 1
    
    new_query = '''

			# Update Session-Timeout based on remaining voucher time
			query = \"UPDATE vouchers SET activated_at = COALESCE(activated_at, NOW()), status = 'used' WHERE username = '%{SQL-User-Name}' AND status = 'available' AND activated_at IS NULL\"

			query = \"UPDATE radreply SET value = GREATEST(1, CAST((v.duration_hours * 3600) - TIMESTAMPDIFF(SECOND, v.activated_at, NOW()) AS UNSIGNED)) FROM vouchers v WHERE v.username = '%{SQL-User-Name}' AND v.status = 'used' AND v.activated_at IS NOT NULL AND radreply.username = '%{SQL-User-Name}' AND radreply.attribute = 'Session-Timeout'\"
'''
    c = c[:insert_end] + new_query + c[insert_end:]
    
    with open('/etc/freeradius/3.0/mods-config/sql/main/mysql/queries.conf','w') as f:
        f.write(c)
    print('Start queries added')
"

echo ""
echo "=== 2. Pastikan radreply punya Session-Timeout untuk semua voucher ==="
mariadb radius -e "
INSERT IGNORE INTO radreply(username,attribute,op,value) 
SELECT username, 'Session-Timeout', ':=', CAST(duration_hours * 3600 AS CHAR) 
FROM vouchers WHERE status IN ('available','used');
" 2>/dev/null
echo "Radreply updated"

echo ""
echo "=== 3. Test config ==="
freeradius -XC 2>&1 | tail -3

echo ""
echo "=== 4. Restart ==="
systemctl restart freeradius
sleep 2

echo ""
echo "=== 5. Test ==="
mariadb radius -e "UPDATE vouchers SET status='available', activated_at=NULL WHERE username='test02'; DELETE FROM radacct WHERE username='test02'; DELETE FROM radreply WHERE username='test02' AND attribute='Session-Timeout';" 2>/dev/null

# Pastikan radreply ada
mariadb radius -e "INSERT IGNORE INTO radreply(username,attribute,op,value) VALUES('test02','Session-Timeout',':=','86400');" 2>/dev/null

echo "--- Login 1 ---"
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout

echo ""
echo "--- Cek DB ---"
mariadb -N radius -e "SELECT status, activated_at FROM vouchers WHERE username='test02';" 2>/dev/null
mariadb -N radius -e "SELECT value FROM radreply WHERE username='test02' AND attribute='Session-Timeout';" 2>/dev/null

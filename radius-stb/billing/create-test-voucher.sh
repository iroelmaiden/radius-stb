#!/bin/bash
echo "=== Buat voucher test baru ==="
VOUCHER_CODE="TEST$(date +%s | tail -c 5)"
VOUCHER_USER=$(echo "$VOUCHER_CODE" | tr 'A-Z' 'a-z')

echo "Code: $VOUCHER_CODE"
echo "User: $VOUCHER_USER"

mariadb radius -e "
INSERT INTO vouchers(code,username,password,package_id,status,duration_hours)
VALUES('$VOUCHER_CODE','$VOUCHER_USER','$VOUCHER_USER',1,'available',24.0);
INSERT INTO radcheck(username,attribute,op,value) VALUES('$VOUCHER_USER','Cleartext-Password',':=','$VOUCHER_USER');
INSERT INTO radusergroup(username,groupname,priority) VALUES('$VOUCHER_USER','hotspot',1);
" 2>/dev/null

echo ""
echo "=== Verify ==="
mariadb -N radius -e "SELECT username, status, duration_hours FROM vouchers WHERE username='$VOUCHER_USER';"

echo ""
echo "=== Test radtest ==="
radtest $VOUCHER_USER $VOUCHER_USER 127.0.0.1 0 testing123 2>&1 | grep -E "Session-Timeout|Reply-Message|Accept|Reject"

echo ""
echo "=== Available vouchers ==="
mariadb -N radius -e "SELECT username, status, duration_hours FROM vouchers WHERE status='available';"

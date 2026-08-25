#!/usr/bin/env bash
echo "=== Pendekatan baru: gunakan sql module untuk post-auth ==="
echo "=== Hapus exec-voucher dari config ==="

# Hapus semua exec-voucher dari default
python3 -c "
with open('/etc/freeradius/3.0/sites-enabled/default','r') as f:
    c = f.read()
c = c.replace('\t\texec-voucher\n', '')
with open('/etc/freeradius/3.0/sites-enabled/default','w') as f:
    f.write(c)
print('exec-voucher removed')
"

# Hapus dari Auth-Type PAP
python3 -c "
with open('/etc/freeradius/3.0/sites-enabled/default','r') as f:
    c = f.read()
c = c.replace('\t\t\texec-voucher\n', '')
with open('/etc/freeradius/3.0/sites-enabled/default','w') as f:
    f.write(c)
print('exec-voucher removed from PAP')
"

freeradius -XC 2>&1 | tail -3

echo ""
echo "=== Strategi baru: tambah Session-Timeout ke radreply ==="
echo "Jangan pakai radcheck, pakai radreply"
echo "Exec script akan update radreply saat login"

echo ""
echo "=== Update check-voucher.sh untuk update radreply ==="
cat > /etc/freeradius/3.0/scripts/check-voucher.sh << 'SHEOF'
#!/bin/bash
MYCNF="/etc/freeradius/3.0/scripts/.my.cnf"
USER_NAME="$1"
if [ -z "$USER_NAME" ]; then exit 0; fi

RESULT=$(mysql --defaults-file="$MYCNF" -N -e "
SELECT v.status, v.activated_at, v.duration_hours 
FROM vouchers v 
WHERE v.username='$USER_NAME' LIMIT 1" 2>/dev/null)

if [ -z "$RESULT" ]; then exit 0; fi

STATUS=$(echo "$RESULT" | cut -f1)
ACTIVATED=$(echo "$RESULT" | cut -f2)
DURATION=$(echo "$RESULT" | cut -f3)

if [ "$ACTIVATED" = "NULL" ] || [ -z "$ACTIVATED" ]; then ACTIVATED=""; fi

if [ "$STATUS" = "expired" ]; then
    exit 0
fi

if [ "$STATUS" = "available" ] && [ -z "$ACTIVATED" ]; then
    DURATION_SEC=$(echo "$DURATION * 3600" | bc | cut -d. -f1)
    mysql --defaults-file="$MYCNF" -e "UPDATE vouchers SET activated_at=NOW(), status='used' WHERE username='$USER_NAME'" 2>/dev/null
    # Update radreply Session-Timeout
    mysql --defaults-file="$MYCNF" -e "DELETE FROM radreply WHERE username='$USER_NAME' AND attribute='Session-Timeout'" 2>/dev/null
    mysql --defaults-file="$MYCNF" -e "INSERT INTO radreply(username,attribute,op,value) VALUES('$USER_NAME','Session-Timeout',':=','$DURATION_SEC')" 2>/dev/null
    exit 0
fi

if [ "$STATUS" = "used" ] && [ -n "$ACTIVATED" ]; then
    DURATION_SEC=$(echo "$DURATION * 3600" | bc | cut -d. -f1)
    ACTIVATED_TS=$(date -d "$ACTIVATED" +%s 2>/dev/null)
    NOW_TS=$(date +%s)
    ELAPSED=$((NOW_TS - ACTIVATED_TS))
    REMAINING=$((DURATION_SEC - ELAPSED))
    if [ "$REMAINING" -le 0 ]; then
        mysql --defaults-file="$MYCNF" -e "UPDATE vouchers SET status='expired' WHERE username='$USER_NAME'" 2>/dev/null
        mysql --defaults-file="$MYCNF" -e "DELETE FROM radcheck WHERE username='$USER_NAME'" 2>/dev/null
        mysql --defaults-file="$MYCNF" -e "DELETE FROM radreply WHERE username='$USER_NAME'" 2>/dev/null
        mysql --defaults-file="$MYCNF" -e "DELETE FROM radusergroup WHERE username='$USER_NAME'" 2>/dev/null
    else
        # Update radreply Session-Timeout dengan sisa waktu
        mysql --defaults-file="$MYCNF" -e "DELETE FROM radreply WHERE username='$USER_NAME' AND attribute='Session-Timeout'" 2>/dev/null
        mysql --defaults-file="$MYCNF" -e "INSERT INTO radreply(username,attribute,op,value) VALUES('$USER_NAME','Session-Timeout',':=','$REMAINING')" 2>/dev/null
    fi
    exit 0
fi
SHEOF
chmod +x /etc/freeradius/3.0/scripts/check-voucher.sh
chown freerad:freerad /etc/freeradius/3.0/scripts/check-voucher.sh
echo "Script updated"

echo ""
echo "=== Tambah ke accounting start (saat user pertama kali connect) ==="
echo "Query akan panggil script untuk set Session-Timeout"

#!/bin/bash
cat > /etc/freeradius/3.0/scripts/check-voucher.sh << 'SHEOF'
#!/bin/bash
MYCNF="/etc/freeradius/3.0/scripts/.my.cnf"
USER_NAME="$1"
if [ -z "$USER_NAME" ]; then exit 0; fi

RESULT=$(mysql --defaults-file="$MYCNF" -N -e "
SELECT v.status, v.activated_at, v.duration_hours, v.valid_until
FROM vouchers v 
WHERE v.username='$USER_NAME' LIMIT 1" 2>/dev/null)

if [ -z "$RESULT" ]; then exit 0; fi

STATUS=$(echo "$RESULT" | cut -f1)
ACTIVATED=$(echo "$RESULT" | cut -f2)
DURATION=$(echo "$RESULT" | cut -f3)
VALID_UNTIL=$(echo "$RESULT" | cut -f4)

if [ "$ACTIVATED" = "NULL" ] || [ -z "$ACTIVATED" ]; then ACTIVATED=""; fi
if [ "$VALID_UNTIL" = "NULL" ] || [ -z "$VALID_UNTIL" ]; then VALID_UNTIL=""; fi

if [ "$STATUS" = "expired" ]; then
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
        echo "Session-Timeout = $REMAINING"
    fi
    exit 0
fi

if [ "$STATUS" = "available" ]; then
    if [ -n "$VALID_UNTIL" ]; then
        VALID_TS=$(date -d "$VALID_UNTIL" +%s 2>/dev/null)
        NOW_TS=$(date +%s)
        if [ "$NOW_TS" -gt "$VALID_TS" ]; then
            mysql --defaults-file="$MYCNF" -e "UPDATE vouchers SET status='expired' WHERE username='$USER_NAME'" 2>/dev/null
            mysql --defaults-file="$MYCNF" -e "DELETE FROM radcheck WHERE username='$USER_NAME'" 2>/dev/null
            mysql --defaults-file="$MYCNF" -e "DELETE FROM radreply WHERE username='$USER_NAME'" 2>/dev/null
            mysql --defaults-file="$MYCNF" -e "DELETE FROM radusergroup WHERE username='$USER_NAME'" 2>/dev/null
            exit 0
        fi
    fi
    DURATION_SEC=$(echo "$DURATION * 3600" | bc | cut -d. -f1)
    mysql --defaults-file="$MYCNF" -e "UPDATE vouchers SET activated_at=NOW(), status='used' WHERE username='$USER_NAME'" 2>/dev/null
    echo "Session-Timeout = $DURATION_SEC"
    exit 0
fi
SHEOF

chmod +x /etc/freeradius/3.0/scripts/check-voucher.sh
chown freerad:freerad /etc/freeradius/3.0/scripts/check-voucher.sh
echo "=== check-voucher.sh updated ==="

echo "=== Test ==="
echo "Fresh voucher (should return 86400):"
/etc/freeradius/3.0/scripts/check-voucher.sh fresh01

echo ""
echo "=== Test expired valid_until ==="
mariadb radius -e "INSERT INTO vouchers(code,username,password,package_id,status,duration_hours,valid_until) VALUES('EXPTEST','exptest','exptest',1,'available',24,'2020-01-01 00:00:00');" 2>/dev/null
mariadb radius -e "INSERT INTO radcheck(username,attribute,op,value) VALUES('exptest','Cleartext-Password',':=','exptest');" 2>/dev/null
mariadb radius -e "INSERT INTO radusergroup(username,groupname,priority) VALUES('exptest','hotspot',1);" 2>/dev/null

echo "Test expired voucher (should be rejected):"
/etc/freeradius/3.0/scripts/check-voucher.sh exptest
echo "Voucher status:"
mariadb -N radius -e "SELECT status FROM vouchers WHERE username='exptest';"

echo ""
echo "=== Cleanup ==="
mariadb radius -e "DELETE FROM vouchers WHERE username='exptest'; DELETE FROM radcheck WHERE username='exptest'; DELETE FROM radusergroup WHERE username='exptest';" 2>/dev/null

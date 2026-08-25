#!/bin/bash
echo "=== Stale sessions ==="
mariadb -N radius -e "SELECT username, acctstoptime FROM radacct WHERE acctstoptime IS NULL;"

echo ""
echo "=== Close all stale ==="
mariadb radius -e "UPDATE radacct SET acctstoptime=NOW(), acctterminatecause='Admin-Reset' WHERE acctstoptime IS NULL;"
echo "Done"

echo ""
echo "=== Test fresh01 ==="
radtest fresh01 fresh01 127.0.0.1 0 testing123 2>&1 | head -5

echo ""
echo "=== Check clients.conf ==="
grep -n "client" /etc/freeradius/3.0/clients.conf | head -20

echo ""
echo "=== Restart FreeRADIUS ==="
systemctl restart freeradius
sleep 2
radtest fresh01 fresh01 127.0.0.1 0 testing123 2>&1 | head -5

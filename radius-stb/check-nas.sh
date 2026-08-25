#!/usr/bin/env bash
echo "=== NAS Table ==="
mariadb -N radius -e "SELECT id,nasname,shortname,type FROM nas;" 2>/dev/null
echo ""
echo "=== Cek FreeRADIUS clients.conf ==="
grep -A5 "client mikrotik" /etc/freeradius/3.0/clients.conf | tail -5

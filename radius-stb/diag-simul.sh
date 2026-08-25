#!/usr/bin/env bash
echo "=== ISI modul sql (bagian session/simul) ==="
grep -n -B2 -A4 -i "simul\|session" /etc/freeradius/3.0/mods-enabled/sql | head -30
echo ""
echo "=== BARIS radacct ==="
mariadb -N radius -e "SELECT COUNT(*) FROM radacct;"
mariadb radius -e "SELECT radacctid,username,nasipaddress,acctstarttime,acctstoptime,acctsessionid FROM radacct LIMIT 5;"
echo ""
echo "=== radutmp module ==="
ls -la /etc/freeradius/3.0/mods-enabled/ | grep -i utmp

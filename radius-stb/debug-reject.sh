#!/usr/bin/env bash
systemctl stop freeradius
sleep 1
freeradius -X > /tmp/frx.log 2>&1 &
FPID=$!
sleep 4
radtest test01 test123 172.16.2.2 0 M1kr0t1kS3cr3t > /dev/null 2>&1
sleep 2
kill $FPID 2>/dev/null
sleep 1
systemctl start freeradius
echo "--- Alasan reject ---"
grep -aB2 -A6 "Login incorrect\|Access-Reject\|reject" /tmp/frx.log | tail -30

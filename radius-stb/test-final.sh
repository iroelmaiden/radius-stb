#!/usr/bin/env bash
echo "=== Bersihkan sesi palsu ==="
mariadb radius -e "DELETE FROM radacct;"
echo ""
echo "=== RADTEST #1 (harus ACCEPT) ==="
radtest test01 test123 172.16.2.2 0 M1kr0t1kS3cr3t 2>&1 | grep -a Access
echo ""
echo "=== Cek radacct setelah auth (tanpa accounting) ==="
sleep 1
mariadb -N radius -e "SELECT COUNT(*) FROM radacct;"
echo ""
echo "=== RADTEST #2 user sama (harus REJECT karena sudah login) ==="
radtest test01 test123 172.16.2.2 0 M1kr0t1kS3cr3t 2>&1 | grep -a Access
echo ""
echo "=== RADTEST #3 password salah (harus REJECT) ==="
radtest test01 passsalah 172.16.2.2 0 M1kr0t1kS3cr3t 2>&1 | grep -a Access
echo ""
echo "=== Bersihkan lagi utk kondisi bersih ==="
mariadb radius -e "DELETE FROM radacct;"
echo SELESAI

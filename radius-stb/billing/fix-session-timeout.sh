#!/usr/bin/env bash
echo "=== Hapus Session-Timeout dari radcheck ==="
echo "(biarkan exec script yang set berdasarkan sisa waktu)"
mariadb radius -e "
DELETE FROM radcheck WHERE attribute='Session-Timeout';
" 2>/dev/null
echo "Dihapus semua"

echo ""
echo "=== Cek test02 radcheck ==="
mariadb -N radius -e "SELECT attribute, value FROM radcheck WHERE username='test02';" 2>/dev/null

echo ""
echo "=== Test radtest ==="
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep -E "Session-Timeout|Accept|Reject"

echo ""
echo "--- Re-login ---"
mariadb radius -e "UPDATE radacct SET acctstoptime=NOW() WHERE username='test02' AND acctstoptime IS NULL;" 2>/dev/null
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep -E "Session-Timeout|Accept|Reject"

echo ""
echo "=== Restart ==="
systemctl restart freeradius
sleep 2

echo ""
echo "=== Final test ==="
mariadb radius -e "UPDATE vouchers SET status='available', activated_at=NULL WHERE username='test02'; DELETE FROM radacct WHERE username='test02';" 2>/dev/null
echo "--- First login ---"
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout
sleep 2
mariadb radius -e "UPDATE radacct SET acctstoptime=NOW() WHERE username='test02' AND acctstoptime IS NULL;" 2>/dev/null
echo "--- Re-login ---"
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout
echo "(harusnya kurang dari 86400)"

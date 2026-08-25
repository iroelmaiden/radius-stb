#!/usr/bin/env bash
echo "=== Hapus Session-Timeout dari radreply ==="
mariadb radius -e "DELETE FROM radreply WHERE attribute='Session-Timeout';" 2>/dev/null
echo "Dihapus semua"

echo ""
echo "=== Test ==="
systemctl restart freeradius
sleep 2

mariadb radius -e "UPDATE vouchers SET status='available', activated_at=NULL WHERE username='test02'; DELETE FROM radacct WHERE username='test02';" 2>/dev/null

echo "--- Login 1 ---"
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout
mariadb -N radius -e "SELECT status, activated_at FROM vouchers WHERE username='test02';" 2>/dev/null

sleep 2
echo ""
echo "--- Close stale ---"
mariadb radius -e "UPDATE radacct SET acctstoptime=NOW() WHERE username='test02' AND acctstoptime IS NULL;" 2>/dev/null

echo "--- Login 2 ---"
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout

echo ""
echo "--- Login 3 ---"
mariadb radius -e "UPDATE radacct SET acctstoptime=NOW() WHERE username='test02' AND acctstoptime IS NULL;" 2>/dev/null
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout
echo "(harusnya terus berkurang)"

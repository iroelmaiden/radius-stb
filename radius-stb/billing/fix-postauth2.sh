#!/usr/bin/env bash
echo "=== Pindah exec-voucher ke post-auth ==="

python3 -c "
with open('/etc/freeradius/3.0/sites-enabled/default','r') as f:
    c = f.read()

# Hapus dari authorize
c = c.replace('\t\texec-voucher\n\t\tattr_filter.access_reject', '\t\tattr_filter.access_reject')

# Tambah ke post-auth (setelah -sql di post-auth)
# Cari post-auth section
import re
# Tambah exec-voucher setelah -sql di post-auth section
c = re.sub(r'(post-auth \{[^}]*-sql)', r'\1\n\t\texec-voucher', c, count=1)

with open('/etc/freeradius/3.0/sites-enabled/default','w') as f:
    f.write(c)
print('Moved to post-auth')
"

echo ""
echo "=== Verifikasi ==="
grep -B1 -A1 "exec-voucher" /etc/freeradius/3.0/sites-enabled/default

echo ""
echo "=== Test config ==="
freeradius -XC 2>&1 | tail -3

echo ""
echo "=== Restart ==="
systemctl restart freeradius
sleep 2

echo ""
echo "=== Test ==="
mariadb radius -e "UPDATE vouchers SET status='available', activated_at=NULL WHERE username='test02'; DELETE FROM radacct WHERE username='test02';" 2>/dev/null
rm -f /tmp/voucher-debug.log

echo "--- Login 1 ---"
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout
sleep 1

echo "--- Close stale ---"
mariadb radius -e "UPDATE radacct SET acctstoptime=NOW() WHERE username='test02' AND acctstoptime IS NULL;" 2>/dev/null

echo "--- Login 2 ---"
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout

echo ""
echo "=== Debug log ==="
cat /tmp/voucher-debug.log 2>/dev/null || echo "(kosong)"

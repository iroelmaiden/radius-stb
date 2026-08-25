#!/usr/bin/env bash
echo "=== Cek exec-voucher di config ==="
grep -n "exec-voucher" /etc/freeradius/3.0/sites-enabled/default
echo ""
grep -n "exec-voucher" /etc/freeradius/3.0/sites-enabled/inner-tunnel 2>/dev/null
echo ""
echo "=== Cek post-auth section ==="
grep -n "post-auth" /etc/freeradius/3.0/sites-enabled/default | head -5
echo ""
echo "=== Tambah manual ke post-auth ==="
# Tambah setelah baris post-auth { dan -sql
python3 -c "
with open('/etc/freeradius/3.0/sites-enabled/default','r') as f:
    lines = f.readlines()

in_post_auth = False
new_lines = []
for i, line in enumerate(lines):
    new_lines.append(line)
    if 'post-auth' in line and '{' in line:
        in_post_auth = True
    if in_post_auth and '-sql' in line and 'exec-voucher' not in lines[i+1] if i+1 < len(lines) else True:
        new_lines.append('\t\texec-voucher\n')
        in_post_auth = False

with open('/etc/freeradius/3.0/sites-enabled/default','w') as f:
    f.writelines(new_lines)
print('Added')
"

echo ""
echo "=== Verifikasi ==="
grep -n "exec-voucher" /etc/freeradius/3.0/sites-enabled/default

echo ""
freeradius -XC 2>&1 | tail -3
systemctl restart freeradius
sleep 2

rm -f /tmp/voucher-debug.log
mariadb radius -e "UPDATE vouchers SET status='available', activated_at=NULL WHERE username='test02'; DELETE FROM radacct WHERE username='test02';" 2>/dev/null

echo "--- Login 1 ---"
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout
sleep 1
mariadb radius -e "UPDATE radacct SET acctstoptime=NOW() WHERE username='test02' AND acctstoptime IS NULL;" 2>/dev/null
echo "--- Login 2 ---"
radtest test02 test02 127.0.0.1 0 testing123 2>&1 | grep Session-Timeout

echo ""
echo "=== Debug log ==="
cat /tmp/voucher-debug.log 2>/dev/null || echo "(kosong)"

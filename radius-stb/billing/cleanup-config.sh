#!/usr/bin/env bash
echo "=== Bersihkan config ==="
python3 -c "
with open('/etc/freeradius/3.0/sites-enabled/default','r') as f:
    c = f.read()
c = c.replace('\t\t\texec-voucher\n', '')
with open('/etc/freeradius/3.0/sites-enabled/default','w') as f:
    f.write(c)
print('removed from PAP if present')
"
rm -f /etc/freeradius/3.0/mods-enabled/exec-test 2>/dev/null
echo "exec-test removed"

echo ""
echo "=== Config check ==="
freeradius -XC 2>&1 | tail -3

echo ""
echo "=== exec-voucher location ==="
grep -n 'exec-voucher' /etc/freeradius/3.0/sites-enabled/default

echo ""
echo "=== Restart ==="
systemctl restart freeradius
sleep 2
echo "FreeRADIUS restarted"
systemctl status freeradius --no-pager | head -5

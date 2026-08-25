#!/usr/bin/env bash
echo "=== SITES APACHE ==="
apache2ctl -S 2>/dev/null | grep -e 8080 -e 8000
echo ""
echo "=== CURL OPERATORS (:8000) ==="
curl -s -o /dev/null -w "%{http_code} %{url_effective}\n" -L http://localhost:8000/
echo "=== CURL USERS (:8080) ==="
curl -s -o /dev/null -w "%{http_code} %{url_effective}\n" -L http://localhost:8080/
echo ""
echo "=== CLIENT MIKROTIK DI clients.conf ==="
grep -A5 "client mikrotik" /etc/freeradius/3.0/clients.conf | head -7
echo ""
echo "=== SEED USER TEST + GROUP HOTSPOT ==="
mariadb radius <<'SQL'
INSERT IGNORE INTO radcheck (username,attribute,op,value) VALUES ('test01','Cleartext-Password',':=','test123');
INSERT INTO radusergroup (username,groupname,priority) SELECT 'test01','hotspot',1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM radusergroup WHERE username='test01');
INSERT INTO radgroupcheck (groupname,attribute,op,value) SELECT 'hotspot','Simultaneous-Use',':=','1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM radgroupcheck WHERE groupname='hotspot' AND attribute='Simultaneous-Use');
SELECT username,attribute,value FROM radcheck WHERE username='test01';
SQL
echo ""
echo "=== RADTEST (via localhost secret testing123) ==="
radtest test01 test123 localhost 0 testing123 2>&1 | grep -e Access -e Reply-Message | head -3
echo ""
echo "=== RADTEST via secret NAS (simulasi dari subnet MikroTik) ==="
radtest test01 test123 127.0.0.1 0 M1kr0t1kS3cr3t 2>&1 | grep -e Access -e Reply-Message | head -3

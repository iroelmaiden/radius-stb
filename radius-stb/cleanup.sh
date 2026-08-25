#!/usr/bin/env bash
systemctl disable --now lighttpd >/dev/null 2>&1 || true
mariadb <<'SQL'
DROP DATABASE IF EXISTS radius;
DROP USER IF EXISTS 'radius'@'localhost';
FLUSH PRIVILEGES;
SQL
rm -rf /var/www/html/daloradius
echo CLEANUP_OK

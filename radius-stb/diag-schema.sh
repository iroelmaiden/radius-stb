#!/usr/bin/env bash
echo "--- cek isi tabel saat ini ---"
mariadb -N -e "SELECT COUNT(*) AS jml_tabel FROM information_schema.tables WHERE table_schema='radius';"
echo "--- import manual daloRADIUS dump ---"
mariadb radius < /var/www/daloradius/contrib/db/mariadb-daloradius.sql 2>&1 | head -8
echo "EXIT=$?"

#!/bin/bash
echo "=== 1. Tambah kolom validity_days ke voucher_packages ==="
mariadb radius -e "ALTER TABLE voucher_packages ADD COLUMN validity_days INT NOT NULL DEFAULT 7 AFTER duration_hours;" 2>/dev/null
echo "Done"

echo ""
echo "=== 2. Tambah kolom valid_until ke vouchers ==="
mariadb radius -e "ALTER TABLE vouchers ADD COLUMN valid_until DATETIME NULL AFTER created_at;" 2>/dev/null
echo "Done"

echo ""
echo "=== 3. Update validity_days existing packages ==="
mariadb radius -e "
UPDATE voucher_packages SET validity_days=7 WHERE duration_hours<=24;
UPDATE voucher_packages SET validity_days=14 WHERE duration_hours>24 AND duration_hours<=168;
UPDATE voucher_packages SET validity_days=30 WHERE duration_hours>168;
" 2>/dev/null
echo "Done"

echo ""
echo "=== 4. Update valid_until existing vouchers ==="
mariadb radius -e "
UPDATE vouchers v 
JOIN voucher_packages p ON v.package_id=p.id 
SET v.valid_until=DATE_ADD(v.created_at, INTERVAL p.validity_days DAY)
WHERE v.valid_until IS NULL;
" 2>/dev/null
echo "Done"

echo ""
echo "=== 5. Verify ==="
echo "--- packages ---"
mariadb -N radius -e "SELECT id,name,duration_hours,validity_days FROM voucher_packages;"
echo ""
echo "--- vouchers ---"
mariadb -N radius -e "SELECT id,username,status,created_at,valid_until,duration_hours FROM vouchers WHERE status='available';"

#!/bin/bash
echo "=== Add price column ==="
mariadb radius -e "ALTER TABLE vouchers ADD COLUMN price DECIMAL(12,2) DEFAULT 0 AFTER password;" 2>/dev/null
echo "Done"

echo ""
echo "=== Verify ==="
mariadb -N radius -e "DESCRIBE vouchers;"

echo ""
echo "=== Also add selling_price if missing ==="
mariadb radius -e "ALTER TABLE vouchers ADD COLUMN selling_price DECIMAL(12,2) DEFAULT 0 AFTER price;" 2>/dev/null
echo "Done"

echo ""
echo "=== Final structure ==="
mariadb -N radius -e "DESCRIBE vouchers;"

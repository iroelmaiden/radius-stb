#!/usr/bin/env bash
echo "=== Operator daloRADIUS ==="
mariadb radius -e "SELECT username,plaintext_password,creationdate FROM operators;" 2>/dev/null
echo ""
echo "=== Default login daloRADIUS ==="
echo "Username: administrator"
echo "Password: radius"
echo ""
echo "(Jika tidak bisa, coba admin / admin)"

#!/usr/bin/env bash
# ============================================
# DEPLOY HOTSPOT BILLING ke STB Baru
# ============================================
# Jalankan dari PC yang sudah terkoneksi ke STB baru
# Usage: bash deploy.sh <IP_STB_BARU>
# ============================================

set -euo pipefail

STB_IP="${1:-}"
STB_USER="root"
STB_PASS="admin123"
REMOTE_DIR="/var/www/html/billing"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

if [ -z "$STB_IP" ]; then
    echo "Usage: bash deploy.sh <IP_STB_BARU>"
    echo "Contoh: bash deploy.sh 172.16.2.50"
    exit 1
fi

echo "============================================"
echo " DEPLOY HOTSPOT BILLING ke $STB_IP"
echo "============================================"

# 1. Setup .env di STB baru
echo ""
echo "[1/5] Setup .env file..."
if [ ! -f "$SCRIPT_DIR/.env" ]; then
    echo "ERROR: File .env tidak ditemukan!"
    echo "Jalankan: cp .env.example .env && nano .env"
    exit 1
fi

# 2. Install dependencies di STB baru
echo "[2/5] Install dependencies..."
sshpass -p "$STB_PASS" ssh -o StrictHostKeyChecking=no "$STB_USER@$STB_IP" "
    apt-get update -qq
    apt-get install -y -qq freeradius freeradius-mysql freeradius-utils \
        mariadb-server apache2 php php-mysql php-cli php-mbstring php-xml
" 2>/dev/null

# 3. Upload files
echo "[3/5] Upload billing app..."
sshpass -p "$STB_PASS" ssh -o StrictHostKeyChecking=no "$STB_USER@$STB_IP" "mkdir -p $REMOTE_DIR/includes"
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no -r "$SCRIPT_DIR"/*.php "$STB_USER@$STB_IP:$REMOTE_DIR/"
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no -r "$SCRIPT_DIR/includes/"*.php "$STB_USER@$STB_IP:$REMOTE_DIR/includes/"
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no "$SCRIPT_DIR/.env" "$STB_USER@$STB_IP:/var/www/html/.env"
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no "$SCRIPT_DIR/.env.example" "$STB_USER@$STB_IP:/var/www/html/.env.example"

# 4. Setup database
echo "[4/5] Setup database..."
sshpass -p "$STB_PASS" ssh -o StrictHostKeyChecking=no "$STB_USER@$STB_IP" "
    source /var/www/html/.env
    systemctl enable --now mariadb
    sleep 2
    mariadb -e \"CREATE DATABASE IF NOT EXISTS \${DB_NAME};\"
    mariadb -e \"CREATE USER IF NOT EXISTS '\${DB_USER}'@'localhost' IDENTIFIED BY '\${DB_PASS}';\"
    mariadb -e \"GRANT ALL ON \${DB_NAME}.* TO '\${DB_USER}'@'localhost'; FLUSH PRIVILEGES;\"
    
    # Import schema dari daloRADIUS
    DALO_DIR=/var/www/html/daloradius
    if [ ! -d \"\$DALO_DIR\" ]; then
        git clone --depth 1 https://github.com/lirantal/daloradius.git \"\$DALO_DIR\"
    fi
    mariadb \"\${DB_NAME}\" < \"\$DALO_DIR/contrib/db/fr3-mariadb-freeradius.sql\" 2>/dev/null || true
    mariadb \"\${DB_NAME}\" < \"\$DALO_DIR/contrib/db/mariadb-daloradius.sql\" 2>/dev/null || true
    mariadb \"\${DB_NAME}\" < \"\$DALO_DIR/contrib/db/mariadb-daloradius-dictionaries.sql\" 2>/dev/null || true
    
    # Import tabel billing
    mariadb \"\${DB_NAME}\" < $REMOTE_DIR/create-pppoe.sql 2>/dev/null || true
    mariadb \"\${DB_NAME}\" < $REMOTE_DIR/migrate-hotspot-users.sql 2>/dev/null || true
" 2>/dev/null

# 5. Setup Apache
echo "[5/5] Setup Apache virtual host..."
sshpass -p "$STB_PASS" ssh -o StrictHostKeyChecking=no "$STB_USER@$STB_IP" "
    cat > /etc/apache2/sites-available/billing.conf <<'EOF'
<VirtualHost *:8081>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
    sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf 2>/dev/null || true
    echo 'Listen 8081' >> /etc/apache2/ports.conf
    a2ensite billing
    systemctl restart apache2
" 2>/dev/null

echo ""
echo "============================================"
echo " DEPLOY SELESAI!"
echo " Billing app: http://$STB_IP:8081"
echo " Login: http://$STB_IP:8000 (daloRADIUS)"
echo "============================================"

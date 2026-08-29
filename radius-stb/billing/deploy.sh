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
echo "[1/7] Setup .env file..."
if [ ! -f "$SCRIPT_DIR/.env" ]; then
    echo "ERROR: File .env tidak ditemukan!"
    echo "Jalankan: cp .env.example .env && nano .env"
    exit 1
fi

# 2. Install dependencies di STB baru
echo "[2/7] Install dependencies..."
sshpass -p "$STB_PASS" ssh -o StrictHostKeyChecking=no "$STB_USER@$STB_IP" "
    apt-get update -qq
    apt-get install -y -qq freeradius freeradius-mysql freeradius-utils \
        mariadb-server apache2 php php-mysql php-cli php-mbstring php-xml \
        php-curl php-json php-gd php-zip
" 2>/dev/null

# 3. Upload files
echo "[3/7] Upload billing app..."
sshpass -p "$STB_PASS" ssh -o StrictHostKeyChecking=no "$STB_USER@$STB_IP" "mkdir -p $REMOTE_DIR/includes $REMOTE_DIR/assets/css $REMOTE_DIR/assets/js $REMOTE_DIR/assets/webfonts"
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no -r "$SCRIPT_DIR"/*.php "$STB_USER@$STB_IP:$REMOTE_DIR/"
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no -r "$SCRIPT_DIR/includes/"*.php "$STB_USER@$STB_IP:$REMOTE_DIR/includes/"
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no -r "$SCRIPT_DIR/assets/css/"* "$STB_USER@$STB_IP:$REMOTE_DIR/assets/css/" 2>/dev/null || true
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no -r "$SCRIPT_DIR/assets/js/"* "$STB_USER@$STB_IP:$REMOTE_DIR/assets/js/" 2>/dev/null || true
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no -r "$SCRIPT_DIR/assets/webfonts/"* "$STB_USER@$STB_IP:$REMOTE_DIR/assets/webfonts/" 2>/dev/null || true
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no "$SCRIPT_DIR/.env" "$STB_USER@$STB_IP:/var/www/html/.env"
sshpass -p "$STB_PASS" scp -o StrictHostKeyChecking=no "$SCRIPT_DIR/.env.example" "$STB_USER@$STB_IP:/var/www/html/.env.example" 2>/dev/null || true

# 4. Setup database
echo "[4/7] Setup database..."
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
    mariadb \"\${DB_NAME}\" < $REMOTE_DIR/migrate-users.sql 2>/dev/null || true
    mariadb \"\${DB_NAME}\" < $REMOTE_DIR/migrate-billing-settings.sql 2>/dev/null || true
    
    # Insert default admin user (password: billingku)
    HASH=\$(php -r \"echo password_hash('billingku', PASSWORD_DEFAULT);\")
    mariadb \"\${DB_NAME}\" -e \"INSERT IGNORE INTO users (username, password, nama_lengkap, role, status) VALUES ('admin', '\$HASH', 'Administrator', 'admin', 'active');\"
" 2>/dev/null

# 5. Setup Apache
echo "[5/7] Setup Apache virtual host..."
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

# 6. Setup FreeRADIUS
echo "[6/7] Setup FreeRADIUS..."
sshpass -p "$STB_PASS" ssh -o StrictHostKeyChecking=no "$STB_USER@$STB_IP" "
    source /var/www/html/.env
    
    # Setup SQL module
    cat > /etc/freeradius/3.0/mods-available/sql <<SQLEOF
sql {
    dialect = \"mysql\"
    driver = \"rlm_sql_mysql\"
    server = \"localhost\"
    port = 3306
    login = \"\${DB_USER}\"
    password = \"\${DB_PASS}\"
    radius_db = \"\${DB_NAME}\"
    read_clients = yes
    client_table = \"nas\"
    require_message_authenticator = true
}
SQLEOF

    ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql
    
    # Setup exec-voucher module
    cat > /etc/freeradius/3.0/mods-available/exec-voucher <<'EXECEOF'
exec voucher {
    wait = yes
    program = \"/bin/bash /etc/freeradius/3.0/scripts/check-voucher.sh %{reply:Reply-Message} %{User-Name} %{Stripped-User-Name}\"
    input_pairs = request
    output_pairs = reply
    shell_escape = yes
}
EXECEOF
    ln -sf /etc/freeradius/3.0/mods-available/exec-voucher /etc/freeradius/3.0/mods-enabled/exec-voucher 2>/dev/null || true
    
    # Setup exec-voucher in post-auth
    if ! grep -q 'exec-voucher' /etc/freeradius/3.0/sites-enabled/default; then
        sed -i '/post-auth {/a\\n\\texec-voucher' /etc/freeradius/3.0/sites-enabled/default
    fi
    
    # Add default groups
    mariadb \"\${DB_NAME}\" -e \"INSERT IGNORE INTO radgroupreply (groupname, attribute, op, value) VALUES ('hotspot', 'Reply-Message', '+=', 'Hotspot User'), ('hotspot', 'Idle-Timeout', ':=', '120'), ('pppoe', 'Service-Type', ':=', 'Framed-User'), ('pppoe', 'Framed-Protocol', ':=', 'PPP'), ('pppoe', 'Simultaneous-Use', ':=', '1'), ('pppoe-isolir', 'Service-Type', ':=', 'Framed-User'), ('pppoe-isolir', 'Framed-Protocol', ':=', 'PPP'), ('pppoe-isolir', 'Reply-Message', ':=', 'ISOLIR: Akun anda sedang diisolir'), ('pppoe-isolir', 'Simultaneous-Use', ':=', '1');\"
    
    # Setup reject_delay
    sed -i 's/reject_delay = .*/reject_delay = 0/' /etc/freeradius/3.0/radiusd.conf 2>/dev/null || true
    
    systemctl restart freeradius
" 2>/dev/null

# 7. Setup Cron Jobs
echo "[7/7] Setup Cron Jobs..."
sshpass -p "$STB_PASS" ssh -o StrictHostKeyChecking=no "$STB_USER@$STB_IP" "
    cat > /tmp/crontab.txt <<'CRONEOF'
# Daily reboot at 4:00 WIB (5:00 WITA)
0 5 * * * /sbin/reboot
* * * * * /usr/bin/php /var/www/html/billing/cron-expire.php >> /var/log/voucher-expire.log 2>&1
* * * * * /usr/bin/php /var/www/html/billing/cron-stale.php >> /dev/null 2>&1
* * * * * /usr/bin/php /var/www/html/billing/cron-pppoe-overdue.php >> /var/log/pppoe-overdue.log 2>&1
* * * * * /usr/bin/php /var/www/html/billing/cron-pppoe-reminder.php >> /var/log/pppoe-reminder.log 2>&1
0 6 1 * * /usr/bin/php /var/www/html/billing/cron-pppoe-billing.php >> /var/log/pppoe-billing.log 2>&1
CRONEOF
    crontab /tmp/crontab.txt
    rm /tmp/crontab.txt
    
    # Setup www-data group for clients.conf
    usermod -aG freerad www-data 2>/dev/null || true
" 2>/dev/null

echo ""
echo "============================================"
echo " DEPLOY SELESAI!"
echo ""
echo " Billing App:    http://$STB_IP:8081"
echo " Login Billing:  http://$STB_IP:8081/login.php"
echo "   Username:     admin"
echo "   Password:     billingku"
echo ""
echo " daloRADIUS:     http://$STB_IP:8000"
echo "   Username:     administrator"
echo "   Password:     radius"
echo ""
echo " User Portal:    http://$STB_IP:8080"
echo "============================================"

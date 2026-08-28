#!/bin/bash
# =============================================================================
# HOTSPOT BILLING - One-Click Setup Script
# FreeRADIUS + daloRADIUS + Custom Billing App
# For Armbian ARM64 STB (Debian/Ubuntu based)
# =============================================================================
set -e

# ========================= CONFIGURATION =====================================
# Change these values before running the script
# =============================================================================

# Database
DB_PASS="R@d1us2026"
DB_NAME="radius"
DB_USER="radius"

# FreeRADIUS
NAS_SECRET="M1kr0t1kS3cr3t"
MIKROTIK_IP="172.16.2.0/24"

# daloRADIUS
DALO_USER="administrator"
DALO_PASS="radius"

# Server
SERVER_IP=$(hostname -I | awk '{print $1}')

# GitHub Repo
REPO_URL="https://github.com/iroelmaiden/New-OpenCode-Project.git"
REPO_DIR="/opt/New-OpenCode-Project"
REPO_BRANCH="main"

# App Ports
PORT_DALO="8000"
PORT_BILLING="8081"
PORT_PORTAL="8080"

# ========================= END CONFIGURATION =================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()    { echo -e "${GREEN}[OK]${NC} $1"; }
warn()   { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }
info()   { echo -e "${CYAN}[INFO]${NC} $1"; }
line()   { echo -e "${CYAN}============================================================${NC}"; }

# Check root
if [[ $EUID -ne 0 ]]; then
    error "Script harus dijalankan sebagai root! Gunakan: sudo bash setup.sh"
fi

clear
line
echo -e "${CYAN}    HOTSPOT BILLING - ONE-CLICK SETUP${NC}"
echo -e "${CYAN}    FreeRADIUS + daloRADIUS + Billing App${NC}"
line
echo ""
echo "Server IP: $SERVER_IP"
echo "DB Name:   $DB_NAME"
echo "DB User:   $DB_USER"
echo "NAS Secret: $NAS_SECRET"
echo "MikroTik Range: $MIKROTIK_IP"
echo ""
read -p "Lanjutkan instalasi? (y/n): " CONFIRM
if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
    echo "Dibatalkan."
    exit 0
fi

line
echo -e "${CYAN}STEP 1/8: Installing System Packages${NC}"
line

export DEBIAN_FRONTEND=noninteractive

apt-get update -qq
apt-get install -y -qq \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    git \
    wget \
    unzip \
    mariadb-server \
    mariadb-client \
    freeradius \
    freeradius-mysql \
    freeradius-utils \
    apache2 \
    libapache2-mod-php \
    php \
    php-mysql \
    php-mbstring \
    php-xml \
    php-gd \
    php-cli \
    php-curl \
    php-db \
    2>/dev/null

log "System packages installed"

line
echo -e "${CYAN}STEP 2/8: Configuring MariaDB${NC}"
line

# Start and enable MariaDB
systemctl enable mariadb
systemctl start mariadb

# Create database and user
mysql -u root <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOSQL

log "MariaDB configured: db=${DB_NAME} user=${DB_USER}"

line
echo -e "${CYAN}STEP 3/8: Cloning Repository${NC}"
line

if [[ ! -d "$REPO_DIR" ]]; then
    git clone --depth 1 "$REPO_URL" "$REPO_DIR" 2>/dev/null || warn "Gagal clone repo, gunakan file lokal jika tersedia"
fi
log "Repository ready at $REPO_DIR"

line
echo -e "${CYAN}STEP 4/8: Importing Database Schema${NC}"
line

# Import daloRADIUS schema
DALO_SCHEMA_DIR="$REPO_DIR"
if [[ -d "$REPO_DIR/radius-stb" ]]; then
    DALO_SCHEMA_DIR="$REPO_DIR/radius-stb"
fi

# Try to find and import daloRADIUS schema files
DALO_SQL_DIR=$(find "$REPO_DIR" -path "*/daloradius*" -name "*.sql" -type f 2>/dev/null | head -5)
if [[ -z "$DALO_SQL_DIR" ]]; then
    # Download daloRADIUS schema if not in repo
    DALO_TEMP="/tmp/daloradius_schema"
    mkdir -p "$DALO_TEMP"
    if [[ ! -d "$DALO_TEMP/daloradius" ]]; then
        git clone --depth 1 https://github.com/lirantal/daloradius.git "$DALO_TEMP/daloradius" 2>/dev/null || true
    fi
fi

# Import schema using the DB config
# Find fr3 schema files
SCHEMA_FILES=$(find /usr/share/freeradius-dialupadmin -name "*.sql" 2>/dev/null | head -10)
for f in $SCHEMA_FILES; do
    mysql -u root "$DB_NAME" < "$f" 2>/dev/null && log "Imported: $(basename $f)" || true
done

# Import from repo if exists
if [[ -d "$REPO_DIR" ]]; then
    find "$REPO_DIR" -name "*.sql" -type f | while read sqlfile; do
        mysql -u root "$DB_NAME" < "$sqlfile" 2>/dev/null && log "Imported: $(basename $sqlfile)" || true
    done
fi

# Import daloRADIUS mariadb schema
if [[ -f "/tmp/daloradius_schema/daloradius/contrib/dbimages-daloradius.sql" ]]; then
    mysql -u root "$DB_NAME" < "/tmp/daloradius_schema/daloradius/contrib/dbimages-daloradius.sql" 2>/dev/null || true
fi

if [[ -f "/tmp/daloradius_schema/daloradius/contrib/daloradius.sql" ]]; then
    mysql -u root "$DB_NAME" < "/tmp/daloradius_schema/daloradius/contrib/daloradius.sql" 2>/dev/null || true
fi

log "Database schema imported"

line
echo -e "${CYAN}STEP 5/8: Creating Custom Tables${NC}"
line

mysql -u root "$DB_NAME" <<'EOSQL'

-- Voucher Packages
CREATE TABLE IF NOT EXISTS `voucher_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `duration_hours` decimal(8,2) NOT NULL DEFAULT 24,
  `price` decimal(12,2) NOT NULL DEFAULT 0,
  `validity_days` int(11) DEFAULT 7,
  `rate_limit` varchar(50) DEFAULT NULL,
  `session_timeout` int(11) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vouchers
CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `username` varchar(64) NOT NULL,
  `password` varchar(128) NOT NULL,
  `package_id` int(11) DEFAULT NULL,
  `status` enum('available','sold','used','expired') DEFAULT 'available',
  `duration_hours` decimal(8,2) DEFAULT 24,
  `time_limit` varchar(20) DEFAULT NULL,
  `comment` varchar(100) DEFAULT NULL,
  `price` decimal(12,2) DEFAULT 0,
  `selling_price` decimal(12,2) DEFAULT 0,
  `sold_at` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `sold_by` varchar(64) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PPPoE Profiles
CREATE TABLE IF NOT EXISTS `pppoe_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `rate_limit` varchar(50) DEFAULT NULL,
  `session_timeout` int(11) DEFAULT NULL,
  `price` decimal(12,2) DEFAULT 0,
  `comment` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PPPoE Users
CREATE TABLE IF NOT EXISTS `pppoe_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `password` varchar(128) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `billing_type` enum('prepaid','postpaid') DEFAULT 'prepaid',
  `billing_date` int(11) DEFAULT 1,
  `isolir_status` enum('active','isolir') DEFAULT 'active',
  `isolir_date` datetime DEFAULT NULL,
  `last_paid_date` datetime DEFAULT NULL,
  `package_id` int(11) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `status` enum('active','disabled') DEFAULT 'active',
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PPPoE Billing
CREATE TABLE IF NOT EXISTS `pppoe_billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `billing_period` varchar(20) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('unpaid','paid','overdue','isolir') DEFAULT 'unpaid',
  `paid_amount` decimal(12,2) DEFAULT 0,
  `paid_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PPPoE Payments
CREATE TABLE IF NOT EXISTS `pppoe_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `billing_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','transfer','qris','ewallet','other') DEFAULT 'cash',
  `payment_date` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `received_by` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hotspot Users
CREATE TABLE IF NOT EXISTS `hotspot_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `password` varchar(128) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `package_id` int(11) DEFAULT NULL,
  `rate_limit` varchar(50) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `status` enum('active','disabled') DEFAULT 'active',
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) DEFAULT NULL,
  `username` varchar(64) DEFAULT NULL,
  `package_name` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('sale','activation','extension','refund') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `selling_price` decimal(12,2) DEFAULT 0,
  `profit` decimal(12,2) DEFAULT 0,
  `payment_method` enum('cash','transfer','qris','ewallet','other') DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `buyer_name` varchar(100) DEFAULT NULL,
  `buyer_contact` varchar(50) DEFAULT NULL,
  `buyer_phone` varchar(20) DEFAULT NULL,
  `created_by` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WA Templates
CREATE TABLE IF NOT EXISTS `wa_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `variables` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

EOSQL

# Ensure core daloRADIUS tables exist (in case schema import failed)
mysql -u root "$DB_NAME" <<'EOSQL'

CREATE TABLE IF NOT EXISTS `nas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nasname` varchar(128) NOT NULL,
  `shortname` varchar(32) DEFAULT NULL,
  `type` varchar(30) DEFAULT 'other',
  `secret` varchar(60) NOT NULL DEFAULT 'secret',
  `server` varchar(64) DEFAULT NULL,
  `community` varchar(50) DEFAULT NULL,
  `description` varchar(200) DEFAULT 'RADIUS Client',
  PRIMARY KEY (`id`),
  KEY `nasname` (`nasname`(5))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `radcheck` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT ':=',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `radreply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '=',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `radgroupcheck` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT ':=',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `groupname` (`groupname`(32))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `radgroupreply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '=',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `groupname` (`groupname`(32))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `radacct` (
  `radacctid` bigint(21) NOT NULL AUTO_INCREMENT,
  `radacctsessionid` varchar(64) NOT NULL DEFAULT '',
  `radacctuniqueid` varchar(32) NOT NULL DEFAULT '',
  `acctsessiontime` int(12) unsigned DEFAULT NULL,
  `acctstarttime` datetime DEFAULT NULL,
  `acctstoptime` datetime DEFAULT NULL,
  `acctinputoctets` bigint(20) DEFAULT NULL,
  `acctoutputoctets` bigint(20) DEFAULT NULL,
  `calledstationid` varchar(50) NOT NULL DEFAULT '',
  `callingstationid` varchar(50) NOT NULL DEFAULT '',
  `acctterminatecause` varchar(32) NOT NULL DEFAULT '',
  `nasipaddress` varchar(15) NOT NULL DEFAULT '',
  `nasportid` varchar(15) DEFAULT NULL,
  `nasporttype` varchar(32) DEFAULT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `realm` varchar(64) DEFAULT '',
  `acctauth` varchar(32) DEFAULT NULL,
  `connectinfo_start` varchar(50) DEFAULT NULL,
  `connectinfo_stop` varchar(50) DEFAULT NULL,
  `servicetype` varchar(32) DEFAULT NULL,
  `framedprotocol` varchar(32) DEFAULT NULL,
  `framedipaddress` varchar(15) NOT NULL DEFAULT '',
  `framedipv6address` varchar(45) NOT NULL DEFAULT '',
  `framedipv6prefix` varchar(45) NOT NULL DEFAULT '',
  `framedinterfaceid` varchar(44) DEFAULT NULL,
  `framedprefixlen` tinyint(4) DEFAULT NULL,
  `class` varchar(64) DEFAULT NULL,
  `xascendvendorid` int(11) DEFAULT NULL,
  `xascendassignnasportid` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`radacctid`),
  KEY `radacctsessionid` (`radacctsessionid`(32)),
  KEY `radacctuniqueid` (`radacctuniqueid`(32)),
  KEY `acctstarttime` (`acctstarttime`),
  KEY `acctstoptime` (`acctstoptime`),
  KEY `nasipaddress` (`nasipaddress`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `radpostauth` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `pass` varchar(64) NOT NULL DEFAULT '',
  `reply` varchar(32) NOT NULL DEFAULT '',
  `authdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `class` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `radusergroup` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `priority` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

EOSQL

log "Core daloRADIUS tables ensured"

# Insert default voucher packages if empty
PKG_COUNT=$(mysql -u root -N -e "SELECT COUNT(*) FROM voucher_packages" "$DB_NAME" 2>/dev/null || echo "0")
if [[ "$PKG_COUNT" == "0" ]]; then
    mysql -u root "$DB_NAME" <<'EOSQL'
INSERT INTO voucher_packages (name, duration_hours, price, validity_days, rate_limit, session_timeout) VALUES
('1 Jam', 1.00, 3000.00, 1, '3M/4M', 3600),
('3 Jam', 3.00, 5000.00, 1, NULL, NULL),
('6 Jam', 6.00, 8000.00, 1, NULL, NULL),
('12 Jam', 12.00, 12000.00, 1, NULL, NULL),
('24 Jam', 24.00, 15000.00, 1, NULL, NULL),
('7 Hari', 168.00, 50000.00, 7, NULL, NULL),
('30 Hari', 720.00, 150000.00, 30, NULL, NULL);
EOSQL
    log "Default voucher packages inserted"
fi

# Insert localhost into nas table
mysql -u root "$DB_NAME" <<EOSQL
INSERT IGNORE INTO nas (nasname, shortname, type, secret) VALUES ('127.0.0.1', 'localhost', 'other', 'testing123');
EOSQL

log "Custom tables created"

line
echo -e "${CYAN}STEP 6/8: Installing daloRADIUS${NC}"
line

DALO_DIR="/var/www/html/daloradius"
if [[ ! -d "$DALO_DIR" ]]; then
    git clone --depth 1 https://github.com/lirantal/daloradius.git "$DALO_DIR" 2>/dev/null || warn "Gagal clone daloRADIUS"
fi

# Configure daloRADIUS DB
DALO_CONF="$DALO_DIR/app/common/includes/daloradius.conf.php"
if [[ -f "$DALO_CONF" ]]; then
    sed -i "s/CONFIG_DB_HOST.*/CONFIG_DB_HOST','${DB_HOST:-localhost}'/" "$DALO_CONF"
    sed -i "s/CONFIG_DB_USER.*/CONFIG_DB_USER','${DB_USER}'/" "$DALO_CONF"
    sed -i "s/CONFIG_DB_PASS.*/CONFIG_DB_PASS','${DB_PASS}'/" "$DALO_CONF"
    sed -i "s/CONFIG_DB_NAME.*/CONFIG_DB_NAME','${DB_NAME}'/" "$DALO_CONF"
    log "daloRADIUS DB configured"
fi

# Install php-db for daloRADIUS
apt-get install -y -qq php-db 2>/dev/null || true

# Fix JPgraph for PHP 8.x
JPGRAPH_DIR="$DALO_DIR/library/jpgraph"
if [[ -d "$JPGRAPH_DIR" ]]; then
    find "$JPGRAPH_DIR" -name "*.php" -exec sed -i "s/define('IMG_PNG', 4);/if (!defined('IMG_PNG')) { define('IMG_PNG', 4); }/" {} \; 2>/dev/null || true
    log "JPgraph PHP 8.x compatibility fixed"
fi

log "daloRADIUS installed"

line
echo -e "${CYAN}STEP 7/8: Deploying Billing App${NC}"
line

# Create web directories
mkdir -p /var/www/html/billing
mkdir -p /var/www/html/portal

# Copy billing app from repo
BILLING_SRC=""
if [[ -d "$REPO_DIR/radius-stb/billing" ]]; then
    BILLING_SRC="$REPO_DIR/radius-stb/billing"
elif [[ -d "$REPO_DIR/billing" ]]; then
    BILLING_SRC="$REPO_DIR/billing"
fi

if [[ -n "$BILLING_SRC" ]]; then
    cp -r "$BILLING_SRC/"* /var/www/html/billing/ 2>/dev/null
    log "Billing app deployed from $BILLING_SRC"
else
    warn "Billing source not found in repo, skipping copy"
fi

# Create .env
cat > /var/www/html/.env <<EOF
DB_HOST=localhost
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
NAS_SECRET=${NAS_SECRET}
MT_IP=172.16.2.1
MT_USER=admin
MT_PASS=admin
DALO_USER=${DALO_USER}
DALO_PASS=${DALO_PASS}
APP_URL=http://${SERVER_IP}:${PORT_BILLING}
EOF

log ".env created"

# Fix setFlash signature in config.php
CONFIG_PHP="/var/www/html/billing/config.php"
if [[ -f "$CONFIG_PHP" ]]; then
    # Ensure setFlash has correct signature: setFlash($type, $message)
    sed -i "s/function setFlash(\\\$message, \\\$type = 'success')/function setFlash(\\\$type, \\\$message)/" "$CONFIG_PHP" 2>/dev/null || true
    log "config.php setFlash fixed"
fi

# Fix CSS/JS to use CDN
HEADER_PHP="/var/www/html/billing/includes/header.php"
FOOTER_PHP="/var/www/html/billing/includes/footer.php"

if [[ -f "$HEADER_PHP" ]]; then
    # Replace local CSS paths with CDN
    sed -i 's|href="assets/css/bootstrap.min.css"|href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"|g' "$HEADER_PHP" 2>/dev/null || true
    sed -i 's|href="assets/css/all.min.css"|href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"|g' "$HEADER_PHP" 2>/dev/null || true
    log "Header CSS fixed (CDN)"
fi

if [[ -f "$FOOTER_PHP" ]]; then
    sed -i 's|src="assets/js/bootstrap.bundle.min.js"|src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"|g' "$FOOTER_PHP" 2>/dev/null || true
    log "Footer JS fixed (CDN)"
fi

# Set permissions
chown -R www-data:www-data /var/www/html/
chmod -R 755 /var/www/html/
chmod 600 /var/www/html/.env

log "Billing app deployed"

line
echo -e "${CYAN}STEP 8/8: Configuring Apache + FreeRADIUS + Services${NC}"
line

# --- APACHE ---
a2enmod rewrite 2>/dev/null || true
a2enmod php* 2>/dev/null || true

# Ports
cat > /etc/apache2/ports.conf <<EOF
Listen 80
Listen 443
Listen ${PORT_DALO}
Listen ${PORT_BILLING}
Listen ${PORT_PORTAL}
EOF

# daloRADIUS VirtualHost
cat > /etc/apache2/sites-available/daloradius.conf <<EOF
<VirtualHost *:${PORT_DALO}>
    DocumentRoot /var/www/html/daloradius/app/operators
    ErrorLog /var/log/apache2/daloradius-error.log
    CustomLog /var/log/apache2/daloradius-access.log combined
    <Directory /var/www/html/daloradius/app/operators>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <Directory /var/www/html/daloradius>
        Require all denied
    </Directory>
    <Directory /var/www/html/daloradius/app/operators>
        Require all granted
    </Directory>
</VirtualHost>
EOF

# Billing VirtualHost
cat > /etc/apache2/sites-available/billing.conf <<EOF
<VirtualHost *:${PORT_BILLING}>
    DocumentRoot /var/www/html/billing
    ErrorLog /var/log/apache2/billing-error.log
    CustomLog /var/log/apache2/billing-access.log combined
    <Directory /var/www/html/billing>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

# Portal VirtualHost
cat > /etc/apache2/sites-available/portal.conf <<EOF
<VirtualHost *:${PORT_PORTAL}>
    DocumentRoot /var/www/html
    ErrorLog /var/log/apache2/portal-error.log
    CustomLog /var/log/apache2/portal-access.log combined
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

a2ensite daloradius.conf 2>/dev/null || true
a2ensite billing.conf 2>/dev/null || true
a2ensite portal.conf 2>/dev/null || true
a2dissite 000-default.conf 2>/dev/null || true

systemctl restart apache2
log "Apache configured and restarted"

# --- FREERADIUS ---
# SQL Module Config
cat > /etc/freeradius/3.0/mods-available/sql <<EOF
sql {
    dialect = "mysql"
    driver = "rlm_sql_mysql"
    server = "localhost"
    port = 3306
    login = "${DB_USER}"
    password = "${DB_PASS}"
    radius_db = "${DB_NAME}"
    read_clients = yes
    client_table = "nas"

    authcheck_table = "radcheck"
    authreply_table = "radreply"
    groupcheck_table = "radgroupcheck"
    groupreply_table = "radgroupreply"
    acct_table1 = "radacct"
    acct_table2 = "radacct"
    postauth_table = "radpostauth"
    usergroup_table = "radusergroup"
    group_attribute = "SQL-Group"

    \$INCLUDE /etc/freeradius/3.0/mods-config/sql/main/mysql/queries.conf
}
EOF

# FreeRADIUS Clients
cat > /etc/freeradius/3.0/clients.conf <<EOF
client localhost {
    ipaddr = 127.0.0.1
    secret = testing123
    nas_type = "other"
}

client mikrotik {
    ipaddr = ${MIKROTIK_IP}
    secret = ${NAS_SECRET}
    shortname = "mikrotik"
    nas_type = "other"
}
EOF

# Enable SQL module
ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql
log "SQL module enabled"

# Enable SQL in default site
DEFAULT_SITE="/etc/freeradius/3.0/sites-enabled/default"
if [[ -f "$DEFAULT_SITE" ]]; then
    # Ensure sql module is listed in authorize section
    if ! grep -q "^-sql" "$DEFAULT_SITE" 2>/dev/null; then
        sed -i '/^\tauthorize {/a \        -sql' "$DEFAULT_SITE" 2>/dev/null || true
    fi
fi

# Enable SQL in inner-tunnel
INNER_TUNNEL="/etc/freeradius/3.0/sites-enabled/inner-tunnel"
if [[ -f "$INNER_TUNNEL" ]]; then
    if ! grep -q "^-sql" "$INNER_TUNNEL" 2>/dev/null; then
        sed -i '/^\tauthorize {/a \        -sql' "$INNER_TUNNEL" 2>/dev/null || true
    fi
fi

systemctl restart freeradius
log "FreeRADIUS configured and restarted"

# --- CRON JOBS ---
cat > /tmp/billing_cron <<EOF
* * * * * /usr/bin/php /var/www/html/billing/cron-expire.php
* * * * * /usr/bin/php /var/www/html/billing/cron-stale.php
* * * * * /usr/bin/php /var/www/html/billing/cron-pppoe-overdue.php
0 6 1 * * /usr/bin/php /var/www/html/billing/cron-pppoe-billing.php
EOF
crontab /tmp/billing_cron
rm /tmp/billing_cron
log "Cron jobs installed"

# ========================= DONE =============================================
line
echo ""
echo -e "${GREEN}============================================================${NC}"
echo -e "${GREEN}  INSTALASI SELESAI!${NC}"
echo -e "${GREEN}============================================================${NC}"
echo ""
echo -e "  daloRADIUS : http://${SERVER_IP}:${PORT_DALO}"
echo -e "  Billing    : http://${SERVER_IP}:${PORT_BILLING}"
echo -e "  Portal     : http://${SERVER_IP}:${PORT_PORTAL}"
echo ""
echo -e "  daloRADIUS Login : ${DALO_USER} / ${DALO_PASS}"
echo -e "  DB User: ${DB_USER} | DB Pass: ${DB_PASS}"
echo -e "  NAS Secret (MikroTik): ${NAS_SECRET}"
echo ""
echo -e "${YELLOW}  PENTING:${NC}"
echo -e "  1. Pastikan MikroTik RADIUS client IP: ${SERVER_IP}"
echo -e "  2. Pastikan MikroTik RADIUS secret: ${NAS_SECRET}"
echo -e "  3. Buat voucher package dulu di Billing > Packages"
echo -e "  4. Generate voucher di Billing > Voucher"
echo ""
line

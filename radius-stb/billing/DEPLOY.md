# ============================================
# DEPLOY HOTSPOT BILLING ke STB Baru
# ============================================

## Persiapan di PC

1. Pastikan `sshpass` terinstall (untuk automation):
```bash
sudo apt-get install sshpass    # Linux
brew install sshpass            # macOS
# Windows: download dari https://sourceforge.net/projects/sshpass/
```

2. Copy folder `billing/` ke PC lokal (sudah ada)

3. Buat file `.env`:
```bash
cp .env.example .env
nano .env    # isi credential sesuai STB baru
```

## Deploy Manual (Step by Step)

### Step 1: Install di STB Baru
```bash
ssh root@<IP_STB_BARU>
# password: admin123

# Install packages
apt-get update
apt-get install -y freeradius freeradius-mysql freeradius-utils \
    mariadb-server apache2 php php-mysql php-cli php-mbstring php-xml

# Install daloRADIUS
cd /var/www/html
git clone --depth 1 https://github.com/lirantal/daloradius.git daloradius
```

### Step 2: Setup Database
```bash
# Edit .env dulu
cp /var/www/html/.env.example /var/www/html/.env
nano /var/www/html/.env

# Jalankan setup DB
source /var/www/html/.env
systemctl enable --now mariadb
sleep 2

mariadb -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};"
mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mariadb -e "GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

# Import schema daloRADIUS
mariadb ${DB_NAME} < /var/www/html/daloradius/contrib/db/fr3-mariadb-freeradius.sql
mariadb ${DB_NAME} < /var/www/html/daloradius/contrib/db/mariadb-daloradius.sql
mariadb ${DB_NAME} < /var/www/html/daloradius/contrib/db/mariadb-daloradius-dictionaries.sql
```

### Step 3: Upload Billing App
Dari PC:
```bash
# Upload semua file PHP
scp -r billing/* root@<IP_STB_BARU>:/var/www/html/billing/

# Upload .env
scp .env root@<IP_STB_BARU>:/var/www/html/.env

# Upload assets (CSS/JS)
scp -r billing/assets root@<IP_STB_BARU>:/var/www/html/billing/
```

### Step 4: Setup FreeRADIUS
```bash
# Konfigurasi SQL module
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
}
EOF

ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql

# Aktifkan SQL di default site
sed -i 's/#-sql/-sql/' /etc/freeradius/3.0/sites-available/default

# Restart
systemctl restart freeradius
```

### Step 5: Setup Apache
```bash
cat > /etc/apache2/sites-available/billing.conf <<EOF
<VirtualHost *:8081>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

echo 'Listen 8081' >> /etc/apache2/ports.conf
a2ensite billing
systemctl restart apache2
```

### Step 6: Setup MikroTik
```bash
# Dari STB, tambahkan NAS client
cat >> /etc/freeradius/3.0/clients.conf <<EOF
client mikrotik {
    ipaddr = <SUBNET_MIKROTIK>/24
    secret = "<NAS_SECRET_DARI_.ENV>"
    shortname = "mikrotik"
    nas_type = "other"
}
EOF

systemctl restart freeradius
```

### Step 7: Setup Cron Jobs
```bash
# Edit crontab
crontab -e

# Tambahkan:
* * * * * /usr/bin/php /var/www/html/billing/cron-expire.php
* * * * * /usr/bin/php /var/www/html/billing/cron-stale.php
* * * * * /usr/bin/php /var/www/html/billing/cron-pppoe-overdue.php
0 6 1 * * /usr/bin/php /var/www/html/billing/cron-pppoe-billing.php
```

## Akses

| Service | URL |
|---------|-----|
| Billing App | http://<IP_STB>:8081 |
| daloRADIUS Operators | http://<IP_STB>:8000 |
| daloRADIUS Users | http://<IP_STB>:8080 |

## Login Defaults

| Service | Username | Password |
|---------|----------|----------|
| daloRADIUS | administrator | radius |
| MariaDB | radius | (sesuai .env) |

## Checklist

- [ ] .env sudah diisi dengan credential STB baru
- [ ] IP MikroTik sudah benar di .env
- [ ] NAS Secret sudah cocok antara .env dan MikroTik
- [ ] FreeRADIUS bisa auth test: `radtest test01 test123 localhost 0 testing123`
- [ ] Billing app bisa diakses dari browser
- [ ] Cron jobs sudah jalan

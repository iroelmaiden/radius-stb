# ============================================
# DEPLOY HOTSPOT BILLING ke STB Baru
# ============================================

## Deploy Otomatis (Recommended)

```bash
# Dari PC yang sudah terkoneksi ke STB baru
cd billing/
cp .env.example .env
nano .env    # isi credential sesuai STB baru

bash deploy.sh <IP_STB_BARU>
```

Script akan otomatis:
1. Install dependencies (FreeRADIUS, MariaDB, Apache, PHP)
2. Upload semua file billing app
3. Setup database (FreeRADIUS + billing + users)
4. Setup Apache virtual host
5. Setup FreeRADIUS (SQL, exec-voucher, groups)
6. Setup cron jobs

## Deploy Manual (Step by Step)

### Step 1: Install di STB Baru
```bash
ssh root@<IP_STB_BARU>
# password: admin123

# Install packages
apt-get update
apt-get install -y freeradius freeradius-mysql freeradius-utils \
    mariadb-server apache2 php php-mysql php-cli php-mbstring php-xml \
    php-curl php-json php-gd php-zip

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

# Import tabel billing
mariadb ${DB_NAME} < /var/www/html/billing/create-pppoe.sql
mariadb ${DB_NAME} < /var/www/html/billing/migrate-hotspot-users.sql
mariadb ${DB_NAME} < /var/www/html/billing/migrate-users.sql
mariadb ${DB_NAME} < /var/www/html/billing/migrate-billing-settings.sql

# Insert default admin user (password: billingku)
HASH=$(php -r "echo password_hash('billingku', PASSWORD_DEFAULT);")
mariadb ${DB_NAME} -e "INSERT IGNORE INTO users (username, password, nama_lengkap, role, status) VALUES ('admin', '$HASH', 'Administrator', 'admin', 'active');"
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
    require_message_authenticator = true
}
EOF

ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql

# Setup exec-voucher module
cat > /etc/freeradius/3.0/mods-available/exec-voucher <<'EOF'
exec voucher {
    wait = yes
    program = "/bin/bash /etc/freeradius/3.0/scripts/check-voucher.sh %{reply:Reply-Message} %{User-Name} %{Stripped-User-Name}"
    input_pairs = request
    output_pairs = reply
    shell_escape = yes
}
EOF
ln -sf /etc/freeradius/3.0/mods-available/exec-voucher /etc/freeradius/3.0/mods-enabled/exec-voucher

# Aktifkan SQL di default site
sed -i 's/#-sql/-sql/' /etc/freeradius/3.0/sites-available/default

# Tambahkan exec-voucher di post-auth
sed -i '/post-auth {/a\\n\\texec-voucher' /etc/freeradius/3.0/sites-enabled/default

# Setup reject_delay
sed -i 's/reject_delay = .*/reject_delay = 0/' /etc/freeradius/3.0/radiusd.conf

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

sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
echo 'Listen 8081' >> /etc/apache2/ports.conf
a2ensite billing
systemctl restart apache2
```

### Step 6: Setup Cron Jobs
```bash
cat > /tmp/crontab.txt <<EOF
# Daily reboot at 4:00 WIB (5:00 WITA)
0 5 * * * /sbin/reboot
* * * * * /usr/bin/php /var/www/html/billing/cron-expire.php >> /var/log/voucher-expire.log 2>&1
* * * * * /usr/bin/php /var/www/html/billing/cron-stale.php >> /dev/null 2>&1
* * * * * /usr/bin/php /var/www/html/billing/cron-pppoe-overdue.php >> /var/log/pppoe-overdue.log 2>&1
* * * * * /usr/bin/php /var/www/html/billing/cron-pppoe-reminder.php >> /var/log/pppoe-reminder.log 2>&1
0 6 1 * * /usr/bin/php /var/www/html/billing/cron-pppoe-billing.php >> /var/log/pppoe-billing.log 2>&1
EOF
crontab /tmp/crontab.txt
rm /tmp/crontab.txt
```

### Step 7: Setup www-data Group
```bash
# Allow Apache to write clients.conf
usermod -aG freerad www-data
```

## Akses

| Service | URL |
|---------|-----|
| Billing App | http://<IP_STB>:8081 |
| Login Billing | http://<IP_STB>:8081/login.php |
| daloRADIUS Operators | http://<IP_STB>:8000 |
| daloRADIUS Users | http://<IP_STB>:8080 |

## Login Defaults

| Service | Username | Password |
|---------|----------|----------|
| Billing App | admin | billingku |
| daloRADIUS | administrator | radius |
| MariaDB | radius | (sesuai .env) |

## User Roles

| Role | Akses |
|------|-------|
| Admin | Full access (12 permissions) |
| Teknisi | Dashboard, Voucher, Profile, User Hotspot, PPPoE, Reports, Monitoring |
| Operator | Dashboard, Voucher, User Hotspot, User PPPoE, Billing, Monitoring |

## Database Tables

| Table | Fungsi |
|-------|--------|
| users | User login billing app |
| role_permissions | Hak akses per role |
| vouchers | Voucher data |
| voucher_packages | Paket voucher |
| pppoe_users | PPPoE customer data |
| pppoe_profiles | PPPoE profile |
| pppoe_billing | Tagihan bulanan |
| pppoe_payments | Riwayat pembayaran |
| hotspot_users | User hotspot non-voucher |
| billing_settings | Pengaturan billing |
| whatsapp_settings | Pengaturan WhatsApp API |
| wa_templates | Template pesan WhatsApp |

## Checklist

- [ ] .env sudah diisi dengan credential STB baru
- [ ] IP MikroTik sudah benar di .env
- [ ] NAS Secret sudah cocok antara .env dan MikroTik
- [ ] FreeRADIUS bisa auth test: `radtest test01 test123 localhost 0 testing123`
- [ ] Billing app bisa diakses dari browser
- [ ] Login dengan admin/billingku berhasil
- [ ] Cron jobs sudah jalan
- [ ] WhatsApp API sudah dikonfigurasi (opsional)

# RADIUS Server untuk STB (Hotspot MikroTik)

## Topologi

```
[HP/Laptop] --WiFi--> [MikroTik Hotspot] --RADIUS--> [STB: FreeRADIUS + daloRADIUS]
                         :80/443                       172.16.2.2
                                                        |
                                                    :1812-1813
                                                    :8000 (operator panel)
                                                    :8080 (portal user)
```

## Spesifikasi STB

- Amlogic S9xx box (ARM aarch64)
- Armbian 25.11 (Ubuntu 25.04 base)
- RAM: 2GB
- OS: Linux, kernel 7.1.3-edge-meson64

## Komponen Terinstall

| Komponen | Versi | Fungsi |
|---|---|---|
| FreeRADIUS | 3.2.7 | Server AAA (Authentication/Authorization/Accounting) |
| MariaDB | 11.4.7 | Database user, voucher, session |
| daloRADIUS | latest (master) | Web panel manajemen user & voucher |
| Apache2 | 2.4 | Web server untuk panel |
| PHP | 8.4 | Runtime daloRADIUS |

## Akses Panel Web

| Panel | URL | Login |
|---|---|---|
| Operator (admin) | http://172.16.2.2:8000/ | admin / radius |
| Portal User | http://172.16.2.2:8080/ | voucher dari database |

**GANTI PASSWORD DEFAULT SEGERA!**

## Kredensial Penting

| Item | Nilai |
|---|---|
| DB Name | radius |
| DB User | radius |
| DB Password | R@d1us2026 |
| NAS Secret (MikroTik) | M1kr0t1kS3cr3t |
| FreeRADIUS Port | 1812 (auth) / 1813 (acct) |
| Subnet MikroTik | 172.16.2.0/24 |

## Konfigurasi MikroTik

### Langkah 1: Tambahkan RADIUS Server

```
/radius
add address=172.16.2.2 secret="M1kr0t1kS3cr3t" service=hotspot timeout=3000ms
```

### Langkah 2: Aktifkan Accounting

```
/radius incoming
set accept=yes
```

### Langkah 3: Aktifkan RADIUS di Profil Hotspot

```
/ip hotspot profile
set [find default=yes] use-radius=yes radius-accounting=yes radius-interim-update=received nas-port-type=wireless
```

### Langkah 4: Test dari MikroTik

```
/radius
print detail
```

Pastikan status MikroTik terdaftar di daloRADIUS: login ke panel operator → NAS → pastikan IP MikroTik muncul.

## Membuat Voucher

### Via daloRADIUS Web Panel

1. Login ke http://172.16.2.2:8000/
2. Users → Batch Users
3. Set parameter:
   - Quantity: jumlah voucher
   - Username prefix: misal "vou-"
   - Password: random atau sama dengan username
   - Group: "hotspot"
   - Simultaneous-Use: 1 (hanya 1 device per voucher)
   - Expiration: masa aktif voucher
4. Generate

### Via SQL Langsung (Cepat)

```sql
-- Buat user voucher
INSERT INTO radcheck (username,attribute,op,value)
VALUES ('VOUCHER001','Cleartext-Password',':=','pass123');

-- Masukkan ke grup hotspot
INSERT INTO radusergroup (username,groupname,priority)
VALUES ('VOUCHER001','hotspot',1);
```

## Layanan yang Berjalan

```
systemctl status freeradius apache2 mariadb
```

| Service | Port |
|---|---|
| freeradius | 1812/1813 (UDP) |
| apache2 (operators) | 8000 (TCP) |
| apache2 (users) | 8080 (TCP) |
| mariadb | 3306 (TCP) |

## Troubleshooting

### Cek Konfigurasi FreeRADIUS

```bash
freeradius -CX          # test config tanpa restart
freeradius -X           # debug mode (verbose)
journalctl -u freeradius -f   # lihat log real-time
```

### Cek Log Autentikasi

```bash
mariadb radius -e "SELECT username,pass,reply,authdate FROM radpostauth ORDER BY id DESC LIMIT 10;"
```

### Cek Sesi Aktif

```bash
mariadb radius -e "SELECT username,nasipaddress,acctstarttime,acctstoptime FROM radacct;"
```

### Bersihkan Sesi Stale

```bash
mariadb radius -e "DELETE FROM radacct WHERE acctstoptime IS NULL AND acctstarttime < DATE_SUB(NOW(), INTERVAL 24 HOUR);"
```

### Restart Semua Service

```bash
systemctl restart freeradius apache2 mariadb
```

## Backup Database

```bash
mysqldump radius > /root/backup-radius-$(date +%F).sql
```

## Restorasi Database

```bash
mariadb radius < /root/backup-radius-2026-08-22.sql
```

## Paket yang Di-hold (upgrade bermasalah)

```bash
apt-mark hold armbian-plymouth-theme fake-ubuntu-advantage-tools \
  linux-dtb-edge-meson64 linux-image-edge-meson64
```

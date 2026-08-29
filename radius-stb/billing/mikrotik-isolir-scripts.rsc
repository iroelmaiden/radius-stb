# ============================================
# MIKROTIK ISOLIR SETUP SCRIPTS
# Jalankan dari WinBox → New Terminal
# ============================================
#
# FLOW:
# 1. Billing app update FreeRADIUS group → pppoe-isolir
# 2. User reconnect → MikroTik on-login script auto-detect
# 3. Firewall block traffic dari user isolir
# 4. User bayar → Billing app update group → pppoe
# 5. User reconnect → normal access
#
# ============================================

# --------------------------------------------
# STEP 1: Buat address list kosong (inisialisasi)
# --------------------------------------------

/ip firewall address-list

# --------------------------------------------
# STEP 2: Firewall Rules
# --------------------------------------------

# Allow isolir users ke DNS
/ip firewall filter add chain=forward src-address-list=pppoe-isolir dst-port=53 protocol=udp action=accept place-before=0 comment="ISOLIR: Allow DNS UDP"
/ip firewall filter add chain=forward src-address-list=pppoe-isolir dst-port=53 protocol=tcp action=accept place-before=0 comment="ISOLIR: Allow DNS TCP"

# Allow isolir users ke billing app (port 8081 di STB)
/ip firewall filter add chain=forward src-address-list=pppoe-isolir dst-address=172.16.2.2 dst-port=8081 protocol=tcp action=accept place-before=0 comment="ISOLIR: Allow Billing App"

# Allow isolir users ke STB HTTP (portal)
/ip firewall filter add chain=forward src-address-list=pppoe-isolir dst-address=172.16.2.2 dst-port=80,8080 protocol=tcp action=accept place-before=0 comment="ISOLIR: Allow STB HTTP"

# Allow isolir users ke RADIUS (auth + accounting)
/ip firewall filter add chain=forward src-address-list=pppoe-isolir dst-address=172.16.2.2 dst-port=1812,1813 protocol=udp action=accept place-before=0 comment="ISOLIR: Allow RADIUS"

# BLOCK semua traffic lain dari isolir users
/ip firewall filter add chain=forward src-address-list=pppoe-isolir action=drop place-before=0 comment="ISOLIR: Block all other traffic"

# --------------------------------------------
# STEP 3: On-Login Script untuk PPPoE
# --------------------------------------------
# Script ini otomatis jalan saat PPPoE user login
# Cek Reply-Message dari RADIUS, jika "ISOLIR" → block

/system script add name=pppoe-isolir-detect source={
    :local uName [/interface pppoe-server get [find where running] username]
    :local uIP [/interface pppoe-server get [find where running] remote-address]
    :local rMsg [/interface pppoe-server get [find where running] reply-message]
    
    :if ([:find $rMsg "ISOLIR"] != 0) do={
        /ip firewall address-list add list=pppoe-isolir address=$uIP comment=$uName timeout=0s
        /log warning ("ISOLIR: User " . $uName . " (" . $uIP . ") blocked")
    }
}

# --------------------------------------------
# STEP 4: Attach script ke PPPoE service
# --------------------------------------------

/ip pppoe-server set [find] on-login=pppoe-isolir-detect

# --------------------------------------------
# STEP 5: Cara Manual Isolir/Release
# --------------------------------------------
#
# ISOLIR MANUAL:
#   /interface pppoe-server disconnect [find where name="username"]
#   /ip firewall address-list add list=pppoe-isolir address=IP comment="username"
#
# RELEASE MANUAL:
#   /ip firewall address-list remove [find where list=pppoe-isolir and address="IP"]
#   /interface pppoe-server disconnect [find where name="username"]
#
# LIHAT USER ISOLIR:
#   /ip firewall address-list print where list=pppoe-isolir
#
# RELEASE SEMUA:
#   /ip firewall address-list remove [find where list=pppoe-isolir]
#
# ============================================

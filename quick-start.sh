#!/bin/bash
# =============================================================================
# QUICK START - One command to deploy hotspot billing on fresh STB
# =============================================================================
# Usage:
#   curl -sL https://raw.githubusercontent.com/iroelmaiden/New-OpenCode-Project/main/setup.sh | sudo bash
#
# Or download and run manually:
#   wget https://raw.githubusercontent.com/iroelmaiden/New-OpenCode-Project/main/setup.sh
#   sudo bash setup.sh
# =============================================================================

set -e

echo "============================================================"
echo "  HOTSPOT BILLING - Quick Start Setup"
echo "============================================================"
echo ""

# Check root
if [[ $EUID -ne 0 ]]; then
    echo "[ERROR] Jalankan sebagai root: sudo bash quick-start.sh"
    exit 1
fi

# Check internet
if ! ping -c 1 -W 3 google.com &>/dev/null; then
    echo "[ERROR] Tidak ada koneksi internet!"
    exit 1
fi

# Detect server IP
SERVER_IP=$(hostname -I | awk '{print $1}')
echo "Server IP: $SERVER_IP"
echo ""

# Ask for MikroTik IP range
read -p "MikroTik IP range [172.16.2.0/24]: " MIKROTIK_IP
MIKROTIK_IP=${MIKROTIK_IP:-"172.16.2.0/24"}

# Ask for NAS Secret
read -p "RADIUS secret untuk MikroTik [M1kr0t1kS3cr3t]: " NAS_SECRET
NAS_SECRET=${NAS_SECRET:-"M1kr0t1kS3cr3t"}

# Ask for DB password
read -p "Database password [R@d1us2026]: " DB_PASS
DB_PASS=${DB_PASS:-"R@d1us2026"}

echo ""
echo "Konfigurasi:"
echo "  Server IP:      $SERVER_IP"
echo "  MikroTik Range: $MIKROTIK_IP"
echo "  NAS Secret:     $NAS_SECRET"
echo "  DB Password:    $DB_PASS"
echo ""
read -p "Konfirmasi? (y/n): " CONFIRM
if [[ "$CONFIRM" != "y" ]]; then
    echo "Dibatalkan."
    exit 0
fi

echo ""
echo "[1/3] Cloning repository..."

REPO_URL="https://github.com/iroelmaiden/New-OpenCode-Project.git"
REPO_DIR="/opt/New-OpenCode-Project"

if [[ -d "$REPO_DIR" ]]; then
    cd "$REPO_DIR"
    git pull 2>/dev/null || true
else
    git clone --depth 1 "$REPO_URL" "$REPO_DIR" 2>/dev/null || {
        echo "[ERROR] Gagal clone repo. Periksa koneksi internet."
        exit 1
    }
fi

echo "[2/3] Running setup script..."

# Set variables and run setup
export DB_PASS NAS_SECRET MIKROTIK_IP SERVER_IP
bash "$REPO_DIR/setup.sh"

echo "[3/3] Setup complete!"

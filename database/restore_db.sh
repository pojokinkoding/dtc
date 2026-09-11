#!/usr/bin/env bash

# ==============================================================================
# Script Restore Database MySQL / MariaDB (Linux / Ubuntu / Debian / CentOS)
# Proyek: DTC (Digital Quality Control)
#
# Penggunaan:
#   chmod +x restore_db.sh
#   ./restore_db.sh [file_backup.sql] [nama_database] [user] [host] [port] [options]
#
# Opsi Flag:
#   -y, --force     : Lewati prompt konfirmasi (Mode otomatis)
#   --help, -h      : Tampilkan bantuan ini
#
# Contoh:
#   ./restore_db.sh                                  (Restore otomatis file backup terbaru ke dtc_v1)
#   ./restore_db.sh backup_latest.sql               (Restore file tertentu)
#   ./restore_db.sh backup_latest.sql -y            (Restore tanpa konfirmasi manual)
#   sudo ./restore_db.sh backup.sql                 (Jika user root menggunakan auth_socket)
#   DB_PASS='secret123' ./restore_db.sh             (Dengan password MySQL via ENV)
# ==============================================================================

set -o pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

# Warna Output Terminal
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Flag non-interaktif
AUTO_CONFIRM=false
POSITIONAL_ARGS=()

for arg in "$@"; do
    case $arg in
        -y|--yes|--force)
            AUTO_CONFIRM=true
            ;;
        -h|--help)
            echo -e "${CYAN}Script Restore Database DTC (Linux)${NC}"
            echo -e "Penggunaan: ./restore_db.sh [file_backup.sql] [nama_database] [user] [host] [port] [-y]"
            exit 0
            ;;
        *)
            POSITIONAL_ARGS+=("$arg")
            ;;
    esac
done

# ------------------------------------------------------------------------------
# 1. Deteksi Kredensial Database dari config/config.php
# ------------------------------------------------------------------------------
CONFIG_FILE="$SCRIPT_DIR/../config/config.php"
[ ! -f "$CONFIG_FILE" ] && CONFIG_FILE="$SCRIPT_DIR/config/config.php"

DETECTED_DB_NAME=""
DETECTED_DB_USER=""
DETECTED_DB_PASS=""
DETECTED_DB_HOST=""

if [ -f "$CONFIG_FILE" ]; then
    if command -v php &> /dev/null; then
        PHP_EXTRACT=$(php -r "require '$CONFIG_FILE'; echo DB_NAME.'|'.DB_HOST.'|'.DB_USER.'|'.DB_PASS;" 2>/dev/null)
        if [ -n "$PHP_EXTRACT" ]; then
            DETECTED_DB_NAME=$(echo "$PHP_EXTRACT" | cut -d'|' -f1)
            DETECTED_DB_HOST=$(echo "$PHP_EXTRACT" | cut -d'|' -f2)
            DETECTED_DB_USER=$(echo "$PHP_EXTRACT" | cut -d'|' -f3)
            DETECTED_DB_PASS=$(echo "$PHP_EXTRACT" | cut -d'|' -f4)
        fi
    fi

    # Fallback grep jika PHP CLI tidak ada
    [ -z "$DETECTED_DB_NAME" ] && DETECTED_DB_NAME=$(grep -E "define\s*\(\s*['\"]DB_NAME['\"]\s*," "$CONFIG_FILE" | head -n 1 | sed -E "s/.*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]*)['\"].*/\1/")
    [ -z "$DETECTED_DB_USER" ] && DETECTED_DB_USER=$(grep -E "define\s*\(\s*['\"]DB_USER['\"]\s*," "$CONFIG_FILE" | head -n 1 | sed -E "s/.*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]*)['\"].*/\1/")
    [ -z "$DETECTED_DB_PASS" ] && DETECTED_DB_PASS=$(grep -E "define\s*\(\s*['\"]DB_PASS['\"]\s*," "$CONFIG_FILE" | head -n 1 | sed -E "s/.*['\"]DB_PASS['\"]\s*,\s*['\"]([^'\"]*)['\"].*/\1/")
    [ -z "$DETECTED_DB_HOST" ] && DETECTED_DB_HOST=$(grep -E "define\s*\(\s*['\"]DB_HOST['\"]\s*," "$CONFIG_FILE" | head -n 1 | sed -E "s/.*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]*)['\"].*/\1/")
fi

# ------------------------------------------------------------------------------
# 2. Deteksi File Backup yang akan di-restore
# ------------------------------------------------------------------------------
INPUT_FILE="${POSITIONAL_ARGS[0]}"
RESTORE_FILE=""

if [ -n "$INPUT_FILE" ] && [ -f "$INPUT_FILE" ]; then
    RESTORE_FILE="$INPUT_FILE"
elif [ -n "$INPUT_FILE" ] && [ -f "$SCRIPT_DIR/$INPUT_FILE" ]; then
    RESTORE_FILE="$SCRIPT_DIR/$INPUT_FILE"
elif [ -f "backup_latest.sql" ]; then
    RESTORE_FILE="backup_latest.sql"
elif [ -f "backup.sql" ]; then
    RESTORE_FILE="backup.sql"
else
    # Cari file backup_*.sql terbaru
    LATEST_FOUND=$(ls -t backup_*.sql 2>/dev/null | head -n 1)
    if [ -n "$LATEST_FOUND" ] && [ -f "$LATEST_FOUND" ]; then
        RESTORE_FILE="$LATEST_FOUND"
    fi
fi

if [ -z "$RESTORE_FILE" ] || [ ! -f "$RESTORE_FILE" ]; then
    echo -e "${RED}[ERROR] File backup SQL tidak ditemukan!${NC}"
    echo -e "Pastikan terdapat file backup di folder ${SCRIPT_DIR}"
    echo -e "Contoh: ./restore_db.sh nama_file_backup.sql"
    exit 1
fi

# ------------------------------------------------------------------------------
# 3. Konfigurasi Parameter Database
# ------------------------------------------------------------------------------
DB_NAME="${POSITIONAL_ARGS[1]:-${DB_NAME:-${DETECTED_DB_NAME:-dtc_v1}}}"
DB_USER="${POSITIONAL_ARGS[2]:-${DB_USER:-${DETECTED_DB_USER:-root}}}"
DB_HOST="${POSITIONAL_ARGS[3]:-${DB_HOST:-${DETECTED_DB_HOST:-localhost}}}"
DB_PORT="${POSITIONAL_ARGS[4]:-${DB_PORT:-3306}}"
PASSWORD="${DB_PASS:-$DETECTED_DB_PASS}"

FILE_SIZE=$(ls -lh "$RESTORE_FILE" | awk '{print $5}')

echo -e "${CYAN}==============================================================================${NC}"
echo -e "${BOLD}${CYAN}            RESTORE DATABASE SYSTEM (LINUX / UBUNTU / DEBIAN)                 ${NC}"
echo -e "${CYAN}==============================================================================${NC}"
echo -e "Target Host       : ${BOLD}${DB_HOST}:${DB_PORT}${NC}"
echo -e "Target Database   : ${BOLD}${DB_NAME}${NC}"
echo -e "User Database     : ${BOLD}${DB_USER}${NC}"
echo -e "File Backup Input : ${BOLD}${RESTORE_FILE}${NC}"
echo -e "Ukuran File       : ${GREEN}${FILE_SIZE}${NC}"
echo -e "------------------------------------------------------------------------------"

# ------------------------------------------------------------------------------
# 4. Deteksi Client mysql / mariadb
# ------------------------------------------------------------------------------
MYSQL_CMD=""
if command -v mysql &> /dev/null; then
    MYSQL_CMD="mysql"
elif command -v mariadb &> /dev/null; then
    MYSQL_CMD="mariadb"
elif [ -f "/opt/lampp/bin/mysql" ]; then
    MYSQL_CMD="/opt/lampp/bin/mysql"
else
    echo -e "${RED}[ERROR] Perintah 'mysql' atau 'mariadb' tidak ditemukan!${NC}"
    echo -e "Silakan instal dengan: ${YELLOW}sudo apt update && sudo apt install mysql-client -y${NC}"
    exit 1
fi

PASS_PARAM=""
if [ -n "$PASSWORD" ]; then
    PASS_PARAM="-p${PASSWORD}"
fi

# ------------------------------------------------------------------------------
# 5. Konfirmasi Keamanan (Safety Confirmation)
# ------------------------------------------------------------------------------
echo -e "${RED}${BOLD}[PERINGATAN KRITIS]${NC}"
echo -e "${YELLOW}Proses ini akan MENIMPA SELURUH DATA pada database '${BOLD}${DB_NAME}${YELLOW}'!${NC}"
echo -e "${YELLOW}Pastikan Anda telah memiliki backup terkini sebelum melanjutkan.${NC}"
echo -e "------------------------------------------------------------------------------"

if [ "$AUTO_CONFIRM" != true ]; then
    read -r -p "Apakah Anda yakin ingin me-restore database '${DB_NAME}'? (y/N): " CONFIRM
    if [[ ! "$CONFIRM" =~ ^[yY]([eE][sS])?$ ]]; then
        echo -e "\n${YELLOW}[BATAL] Proses restore dibatalkan oleh pengguna.${NC}\n"
        exit 0
    fi
fi

# ------------------------------------------------------------------------------
# 6. Eksekusi Restore
# ------------------------------------------------------------------------------
echo -e "\n${YELLOW}[*] Memastikan database '${DB_NAME}' tersedia di MySQL...${NC}"
"$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM \
    -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>restore_err.tmp

INIT_EXIT=$?
if [ $INIT_EXIT -ne 0 ]; then
    echo -e "${RED}[ERROR] Gagal membuat/memverifikasi database '${DB_NAME}'!${NC}"
    cat restore_err.tmp 2>/dev/null
    rm -f restore_err.tmp 2>/dev/null
    exit $INIT_EXIT
fi

echo -e "${YELLOW}[*] Sedang mengeksekusi restore data dari '${RESTORE_FILE}'...${NC}"
"$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM \
    --default-character-set=utf8mb4 "$DB_NAME" < "$RESTORE_FILE" 2>restore_err.tmp

RESTORE_EXIT=$?

if [ $RESTORE_EXIT -eq 0 ]; then
    rm -f restore_err.tmp 2>/dev/null

    # Hitung ringkasan jumlah tabel pasca restore
    TOTAL_TABLES=$("$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM -N -s -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" 2>/dev/null || echo "N/A")

    echo -e "\n${GREEN}==============================================================================${NC}"
    echo -e "${BOLD}${GREEN}[OK] RESTORE DATABASE BERHASIL!${NC}"
    echo -e "${GREEN}==============================================================================${NC}"
    echo -e "Database Target : ${BOLD}${DB_NAME}${NC}"
    echo -e "Total Tabel     : ${BOLD}${TOTAL_TABLES}${NC} tabel aktif"
    echo -e "Sumber Backup   : ${CYAN}${RESTORE_FILE}${NC}"
    echo -e "${GREEN}Database siap digunakan secara normal.${NC}"
    echo -e "${GREEN}==============================================================================${NC}\n"
    exit 0
else
    echo -e "\n${RED}==============================================================================${NC}"
    echo -e "${BOLD}${RED}[ERROR] RESTORE DATABASE GAGAL! (Exit Code: $RESTORE_EXIT)${NC}"
    echo -e "${RED}==============================================================================${NC}"
    if [ -s "restore_err.tmp" ]; then
        echo -e "${RED}Detail Error:${NC}"
        cat restore_err.tmp
        rm -f restore_err.tmp 2>/dev/null
    fi
    echo -e "\n${YELLOW}[TIPS TROUBLESHOOTING]${NC}"
    echo -e "  1. Pastikan service MySQL aktif: ${BOLD}sudo systemctl status mysql${NC}"
    echo -e "  2. Pastikan file backup valid dan tidak terpotong."
    echo -e "  3. Jalankan dengan hak akses root jika perlu: ${BOLD}sudo ./restore_db.sh $RESTORE_FILE${NC}"
    echo -e "${RED}==============================================================================${NC}\n"
    exit $RESTORE_EXIT
fi

#!/usr/bin/env bash

# ==============================================================================
# Script Backup Database MySQL / MariaDB (Linux / Ubuntu / Debian / CentOS)
# Proyek: DTC (Digital Quality Control)
#
# Penggunaan:
#   chmod +x backup_db.sh
#   ./backup_db.sh [nama_database] [user] [host] [port] [file_output]
#
# Contoh:
#   ./backup_db.sh                             (Auto-detect dari config.php / default dtc_v1)
#   sudo ./backup_db.sh                        (Jika user root menggunakan auth_socket)
#   DB_PASS='secret123' ./backup_db.sh         (Menggunakan password via ENV)
#   ./backup_db.sh dtc_v1 user localhost 3306  (Argumen kustom)
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

# Prioritas nilai: Argumen CLI > Variabel ENV > Nilai dari config.php > Default
DB_NAME="${1:-${DB_NAME:-${DETECTED_DB_NAME:-dtc_v1}}}"
DB_USER="${2:-${DB_USER:-${DETECTED_DB_USER:-root}}}"
DB_HOST="${3:-${DB_HOST:-${DETECTED_DB_HOST:-localhost}}}"
DB_PORT="${4:-${DB_PORT:-3306}}"
PASSWORD="${DB_PASS:-$DETECTED_DB_PASS}"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${5:-backup_${DB_NAME}_${TIMESTAMP}.sql}"
LATEST_FILE="backup_latest.sql"

echo -e "${CYAN}==============================================================================${NC}"
echo -e "${BOLD}${CYAN}            BACKUP DATABASE SYSTEM (LINUX / UBUNTU / DEBIAN)                  ${NC}"
echo -e "${CYAN}==============================================================================${NC}"
echo -e "Target Host     : ${BOLD}${DB_HOST}:${DB_PORT}${NC}"
echo -e "Target Database : ${BOLD}${DB_NAME}${NC}"
echo -e "User Database   : ${BOLD}${DB_USER}${NC}"
echo -e "Output File     : ${BOLD}${BACKUP_FILE}${NC}"
echo -e "------------------------------------------------------------------------------"

# ------------------------------------------------------------------------------
# 2. Deteksi Client mysqldump / mariadb-dump
# ------------------------------------------------------------------------------
DUMP_CMD=""
if command -v mysqldump &> /dev/null; then
    DUMP_CMD="mysqldump"
elif command -v mariadb-dump &> /dev/null; then
    DUMP_CMD="mariadb-dump"
elif [ -f "/opt/lampp/bin/mysqldump" ]; then
    DUMP_CMD="/opt/lampp/bin/mysqldump"
else
    echo -e "${RED}[ERROR] Perintah 'mysqldump' atau 'mariadb-dump' tidak ditemukan!${NC}"
    echo -e "Silakan instal paket mysql client dengan:"
    echo -e "  ${YELLOW}sudo apt update && sudo apt install mysql-client -y${NC}"
    exit 1
fi

# Parameter Password
PASS_PARAM=""
if [ -n "$PASSWORD" ]; then
    PASS_PARAM="-p${PASSWORD}"
fi

echo -e "${YELLOW}[*] Sedang mengeksekusi snapshot dumping database '${DB_NAME}'...${NC}"

# Eksekusi mysqldump dengan flags integritas & safety terbaik
"$DUMP_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM \
    --default-character-set=utf8mb4 \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --hex-blob \
    --databases "$DB_NAME" > "$BACKUP_FILE" 2>backup_err.tmp

DUMP_EXIT=$?

if [ $DUMP_EXIT -eq 0 ] && [ -s "$BACKUP_FILE" ]; then
    rm -f backup_err.tmp 2>/dev/null
    cp -f "$BACKUP_FILE" "$LATEST_FILE" 2>/dev/null || true
    cp -f "$BACKUP_FILE" "backup.sql" 2>/dev/null || true

    FILE_SIZE=$(ls -lh "$BACKUP_FILE" | awk '{print $5}')

    echo -e "\n${GREEN}==============================================================================${NC}"
    echo -e "${BOLD}${GREEN}[OK] BACKUP DATABASE BERHASIL!${NC}"
    echo -e "${GREEN}==============================================================================${NC}"
    echo -e "File Snapshot : ${CYAN}${SCRIPT_DIR}/${BACKUP_FILE}${NC}"
    echo -e "Link Latest   : ${CYAN}${SCRIPT_DIR}/${LATEST_FILE}${NC}"
    echo -e "Ukuran File   : ${BOLD}${GREEN}${FILE_SIZE}${NC}"
    echo -e "${GREEN}==============================================================================${NC}\n"
    exit 0
else
    [ -f "$BACKUP_FILE" ] && [ ! -s "$BACKUP_FILE" ] && rm -f "$BACKUP_FILE"

    echo -e "\n${RED}==============================================================================${NC}"
    echo -e "${BOLD}${RED}[ERROR] GAGAL MEMBUAT BACKUP DATABASE! (Exit Code: $DUMP_EXIT)${NC}"
    echo -e "${RED}==============================================================================${NC}"
    if [ -s "backup_err.tmp" ]; then
        echo -e "${RED}Detail Error:${NC}"
        cat backup_err.tmp
        rm -f backup_err.tmp 2>/dev/null
    fi
    echo -e "\n${YELLOW}[TIPS TROUBLESHOOTING]${NC}"
    echo -e "  1. Pastikan service MySQL aktif: ${BOLD}sudo systemctl status mysql${NC}"
    echo -e "  2. Jika user root menggunakan auth_socket: ${BOLD}sudo ./backup_db.sh${NC}"
    echo -e "  3. Jika user membutuhkan password: ${BOLD}DB_PASS='password' ./backup_db.sh${NC}"
    echo -e "${RED}==============================================================================${NC}\n"
    exit $DUMP_EXIT
fi

#!/usr/bin/env bash

# ==============================================================================
# Script setup & import database MySQL / MariaDB (Optimized for Ubuntu / Linux)
#
# Penggunaan:
#   ./import_db.sh [nama_file_sql] [nama_database] [user] [host] [port]
#
# Contoh:
#   ./import_db.sh                     (Default: import dtc.sql ke database dtc_v1)
#   ./import_db.sh backup.sql dtc      (Import backup.sql ke database dtc)
#   sudo ./import_db.sh                (Jika MySQL root menggunakan auth_socket di Ubuntu)
# ==============================================================================

# Dapatkan direktori tempat script berada
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

# Konfigurasi Default
SQL_FILE="${1:-dtc.sql}"
DB_NAME="${2:-dtc_v1}"
DB_USER="${3:-root}"
DB_HOST="${4:-localhost}"
DB_PORT="${5:-3306}"

# Jika dtc.sql tidak ada tetapi backup.sql ada, gunakan backup.sql secara otomatis jika argumen 1 tidak diisi
if [ -z "$1" ] && [ ! -f "$SQL_FILE" ] && [ -f "backup.sql" ]; then
    SQL_FILE="backup.sql"
fi

# Warna output terminal
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${CYAN}===============================================${NC}"
echo -e "${CYAN}   SETUP DATABASE & IMPORT SQL (UBUNTU / LINUX)${NC}"
echo -e "${CYAN}===============================================${NC}"
echo -e "Host        : ${DB_HOST}:${DB_PORT}"
echo -e "User        : ${DB_USER}"
echo -e "Database    : ${DB_NAME}"
echo -e "File SQL    : ${SQL_FILE}"
echo -e "-----------------------------------------------"

# Cek ketersediaan command mysql / mariadb di Ubuntu
MYSQL_CMD=""
if command -v mysql &> /dev/null; then
    MYSQL_CMD="mysql"
elif command -v mariadb &> /dev/null; then
    MYSQL_CMD="mariadb"
elif [ -f "/opt/lampp/bin/mysql" ]; then
    MYSQL_CMD="/opt/lampp/bin/mysql"
else
    echo -e "${RED}[ERROR] Perintah 'mysql' atau 'mariadb' tidak ditemukan!${NC}"
    echo -e "Di Ubuntu, Anda dapat menginstalnya dengan perintah:"
    echo -e "  ${YELLOW}sudo apt update && sudo apt install mysql-client${NC} (atau mariadb-client)"
    exit 1
fi

# Cek keberadaan file SQL
if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}[ERROR] File SQL '$SQL_FILE' tidak ditemukan di folder '$SCRIPT_DIR'!${NC}"
    echo -e "File .sql yang tersedia di folder ini:"
    ls -1 *.sql 2>/dev/null || echo "(Tidak ada file .sql)"
    exit 1
fi

# Parameter password handling
PASS_PARAM=""
if [ -n "$DB_PASS" ]; then
    PASS_PARAM="-p${DB_PASS}"
fi

# 1. Buat Database jika belum ada
echo -e "\n${YELLOW}[1/2] Membuat database '$DB_NAME' jika belum ada...${NC}"
"$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if [ $? -ne 0 ]; then
    echo -e "${RED}[ERROR] Gagal membuat database '$DB_NAME'.${NC}"
    echo -e "${YELLOW}[TIPS UBUNTU] Jika MySQL root menggunakan auth_socket, jalankan script ini dengan 'sudo':${NC}"
    echo -e "  sudo ./import_db.sh $SQL_FILE $DB_NAME $DB_USER"
    echo -e "${YELLOW}Atau atur password via env variable:${NC}"
    echo -e "  DB_PASS='password_kamu' ./import_db.sh $SQL_FILE $DB_NAME $DB_USER"
    exit 1
fi
echo -e "${GREEN}[OK] Database '$DB_NAME' siap.${NC}"

# 2. Import file SQL
echo -e "\n${YELLOW}[2/2] Mengimpor file '$SQL_FILE' ke database '$DB_NAME'...${NC}"
"$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM "$DB_NAME" < "$SQL_FILE"

if [ $? -eq 0 ]; then
    echo -e "\n${GREEN}===============================================${NC}"
    echo -e "${GREEN}[SUCCESS] Import '$SQL_FILE' ke database '$DB_NAME' berhasil!${NC}"
    echo -e "${GREEN}===============================================${NC}"
else
    echo -e "\n${RED}===============================================${NC}"
    echo -e "${RED}[ERROR] Import SQL Gagal!${NC}"
    echo -e "${RED}===============================================${NC}"
    exit 1
fi

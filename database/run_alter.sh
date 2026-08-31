#!/usr/bin/env bash

# ==============================================================================
# Script Migrasi Database (ALTER & Add Tables) - Non-Destructive
#
# Menjalankan perubahan skema (ALTER & CREATE TABLE IF NOT EXISTS)
# TANPA menghapus data yang ada.
#
# Penggunaan:
#   chmod +x run_alter.sh
#   ./run_alter.sh [nama_database] [user] [host] [port]
#
# Contoh:
#   ./run_alter.sh                     (Default: database dtc_v1, user root, host localhost)
#   ./run_alter.sh dtc root 127.0.0.1  (Database dtc, user root)
#   sudo ./run_alter.sh                (Jika root MySQL menggunakan auth_socket di Linux)
#   DB_PASS='password' ./run_alter.sh  (Dengan password via env)
# ==============================================================================

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

SQL_FILE="migrate_alter.sql"
DB_NAME="${1:-dtc_v1}"
DB_USER="${2:-root}"
DB_HOST="${3:-localhost}"
DB_PORT="${4:-3306}"

# Warna Output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}======================================================${NC}"
echo -e "${CYAN}   DATABASE MIGRATION: ALTER & NON-DESTRUCTIVE UPDATE ${NC}"
echo -e "${CYAN}======================================================${NC}"
echo -e "Host        : ${DB_HOST}:${DB_PORT}"
echo -e "User        : ${DB_USER}"
echo -e "Database    : ${DB_NAME}"
echo -e "Script SQL  : ${SQL_FILE}"
echo -e "Mode        : ${GREEN}Non-destructive (ALTER / CREATE IF NOT EXISTS)${NC}"
echo -e "------------------------------------------------------"

# Deteksi binary MySQL/MariaDB
MYSQL_CMD=""
if command -v mysql &> /dev/null; then
    MYSQL_CMD="mysql"
elif command -v mariadb &> /dev/null; then
    MYSQL_CMD="mariadb"
elif [ -f "/opt/lampp/bin/mysql" ]; then
    MYSQL_CMD="/opt/lampp/bin/mysql"
else
    echo -e "${RED}[ERROR] Perintah 'mysql' atau 'mariadb' tidak ditemukan!${NC}"
    echo -e "Silakan instal dengan: ${YELLOW}sudo apt update && sudo apt install mysql-client${NC}"
    exit 1
fi

if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}[ERROR] File '$SQL_FILE' tidak ditemukan di $SCRIPT_DIR!${NC}"
    exit 1
fi

PASS_PARAM=""
if [ -n "$DB_PASS" ]; then
    PASS_PARAM="-p${DB_PASS}"
fi

echo -e "\n${YELLOW}Menjalankan migrasi alter schema...${NC}\n"

# Eksekusi migrasi
"$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM "$DB_NAME" < "$SQL_FILE"

EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo -e "\n${GREEN}======================================================${NC}"
    echo -e "${GREEN}[SUCCESS] Migrasi ALTER database '${DB_NAME}' selesai!${NC}"
    echo -e "${GREEN}Data lama tetap aman dan seluruh kolom/tabel baru siap digunakan.${NC}"
    echo -e "${GREEN}======================================================${NC}"
else
    echo -e "\n${RED}======================================================${NC}"
    echo -e "${RED}[ERROR] Terjadi kesalahan saat migrasi (Exit Code: $EXIT_CODE).${NC}"
    echo -e "${YELLOW}[TIPS] Jika menggunakan password root atau auth_socket, coba:${NC}"
    echo -e "  sudo ./run_alter.sh $DB_NAME $DB_USER"
    echo -e "  DB_PASS='password_kamu' ./run_alter.sh $DB_NAME $DB_USER"
    echo -e "${RED}======================================================${NC}"
    exit $EXIT_CODE
fi

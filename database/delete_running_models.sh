#!/usr/bin/env bash

# ==============================================================================
# Script: Reset / Hapus Semua Running Model (DTC System)
#
# Deskripsi:
#   Mengosongkan tabel `dtc_running_models` sehingga semua line/section 
#   bisa memilih kembali model yang baru (misal pergantian shift / hari baru).
#
# Penggunaan:
#   chmod +x delete_running_models.sh
#   ./delete_running_models.sh [nama_database] [user] [host] [port]
#
# Contoh:
#   ./delete_running_models.sh                     (Default: database dtc_v1, user user, host localhost)
#   DB_PASS='root' ./delete_running_models.sh      (Dengan password via env)
#   DB_PASS='root' ./delete_running_models.sh dtc_v1 user 127.0.0.1
# ==============================================================================

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

DB_NAME="${1:-dtc_v1}"
DB_USER="${2:-user}"
DB_HOST="${3:-localhost}"
DB_PORT="${4:-3306}"

# Warna Output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "${CYAN}======================================================${NC}"
echo -e "${CYAN}      RESET / HAPUS SEMUA DATA RUNNING MODEL         ${NC}"
echo -e "${CYAN}======================================================${NC}"
echo -e "Host        : ${DB_HOST}:${DB_PORT}"
echo -e "User        : ${DB_USER}"
echo -e "Database    : ${DB_NAME}"
echo -e "Target Tabel: ${YELLOW}dtc_running_models${NC}"
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

PASS_PARAM=""
if [ -n "$DB_PASS" ]; then
    PASS_PARAM="-p${DB_PASS}"
fi

# Cek jumlah running model saat ini
COUNT_QUERY="SELECT COUNT(*) FROM dtc_running_models;"
CURRENT_COUNT=$("$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM -N -e "$COUNT_QUERY" "$DB_NAME" 2>/dev/null)

if [ $? -ne 0 ]; then
    # Jika gagal connect, coba prompt password secara interaktif
    echo -e "${YELLOW}[INFO] Mencoba menghubungkan dengan prompt password...${NC}"
    CURRENT_COUNT=$("$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p -N -e "$COUNT_QUERY" "$DB_NAME")
    PASS_PARAM="-p"
fi

echo -e "Jumlah data running model saat ini: ${BOLD}${CURRENT_COUNT:-0} baris${NC}"

# Eksekusi TRUNCATE TABLE
TRUNCATE_QUERY="TRUNCATE TABLE dtc_running_models;"
echo -e "\n${YELLOW}Menjalankan pembersihan data running model...${NC}"

"$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM -e "$TRUNCATE_QUERY" "$DB_NAME"
EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo -e "\n${GREEN}======================================================${NC}"
    echo -e "${GREEN}[SUCCESS] Semua data running model berhasil dikosongkan!${NC}"
    echo -e "${GREEN}Tabel dtc_running_models kini bersih (0 baris).${NC}"
    echo -e "${GREEN}Operator / Foreman dapat memilih model baru di semua line.${NC}"
    echo -e "${GREEN}======================================================${NC}"
else
    echo -e "\n${RED}======================================================${NC}"
    echo -e "${RED}[ERROR] Gagal mengosongkan tabel dtc_running_models (Exit Code: $EXIT_CODE).${NC}"
    echo -e "${YELLOW}[TIPS] Gunakan DB_PASS jika menggunakan password:${NC}"
    echo -e "  DB_PASS='password_kamu' ./delete_running_models.sh $DB_NAME $DB_USER"
    echo -e "${RED}======================================================${NC}"
    exit $EXIT_CODE
fi

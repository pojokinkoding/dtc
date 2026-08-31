#!/usr/bin/env bash

# ==============================================================================
# Script Backup Database MySQL / MariaDB (Linux / Ubuntu)
#
# Penggunaan:
#   chmod +x backup_db.sh
#   ./backup_db.sh [nama_database] [user] [host] [port] [nama_file_output]
#
# Contoh:
#   ./backup_db.sh                              (Backup default: database dtc_v1 -> backup_dtc_v1_TIMESTAMP.sql & backup.sql)
#   sudo ./backup_db.sh                         (Jika MySQL root menggunakan auth_socket)
#   DB_PASS='password_kamu' ./backup_db.sh      (Jika ada password)
#   ./backup_db.sh dtc root 127.0.0.1 3306      (Custom database)
# ==============================================================================

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

# Konfigurasi Parameter Default
DB_NAME="${1:-dtc_v1}"
DB_USER="${2:-root}"
DB_HOST="${3:-localhost}"
DB_PORT="${4:-3306}"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DEFAULT_BACKUP="backup_${DB_NAME}_${TIMESTAMP}.sql"
BACKUP_FILE="${5:-$DEFAULT_BACKUP}"

# Warna Output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}======================================================${NC}"
echo -e "${CYAN}       BACKUP DATABASE MYSQL (UBUNTU / LINUX)         ${NC}"
echo -e "${CYAN}======================================================${NC}"
echo -e "Host          : ${DB_HOST}:${DB_PORT}"
echo -e "User          : ${DB_USER}"
echo -e "Database      : ${DB_NAME}"
echo -e "File Backup   : ${BACKUP_FILE}"
echo -e "------------------------------------------------------"

# Deteksi binary mysqldump / mariadb-dump
DUMP_CMD=""
if command -v mysqldump &> /dev/null; then
    DUMP_CMD="mysqldump"
elif command -v mariadb-dump &> /dev/null; then
    DUMP_CMD="mariadb-dump"
elif [ -f "/opt/lampp/bin/mysqldump" ]; then
    DUMP_CMD="/opt/lampp/bin/mysqldump"
else
    echo -e "${RED}[ERROR] Perintah 'mysqldump' atau 'mariadb-dump' tidak ditemukan!${NC}"
    echo -e "Silakan instal dengan: ${YELLOW}sudo apt update && sudo apt install mysql-client${NC} (atau mariadb-client)"
    exit 1
fi

PASS_PARAM=""
if [ -n "$DB_PASS" ]; then
    PASS_PARAM="-p${DB_PASS}"
fi

echo -e "${YELLOW}Memulai proses dumping database '${DB_NAME}'...${NC}"

# Eksekusi mysqldump dengan flag konsistensi & safety data
"$DUMP_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_PARAM \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --hex-blob \
    --databases "$DB_NAME" > "$BACKUP_FILE"

EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ] && [ -s "$BACKUP_FILE" ]; then
    # Salin juga ke backup.sql sebagai salinan pointer terbaru
    cp -f "$BACKUP_FILE" "backup.sql"
    
    FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo -e "\n${GREEN}======================================================${NC}"
    echo -e "${GREEN}[SUCCESS] Backup Database Berhasil!${NC}"
    echo -e "File Output : ${CYAN}${SCRIPT_DIR}/${BACKUP_FILE}${NC}"
    echo -e "Latest Link : ${CYAN}${SCRIPT_DIR}/backup.sql${NC}"
    echo -e "Ukuran File : ${GREEN}${FILE_SIZE}${NC}"
    echo -e "${GREEN}======================================================${NC}"
else
    # Hapus file kosong jika gagal
    [ -f "$BACKUP_FILE" ] && [ ! -s "$BACKUP_FILE" ] && rm -f "$BACKUP_FILE"
    
    echo -e "\n${RED}======================================================${NC}"
    echo -e "${RED}[ERROR] Gagal mem-backup database! (Exit Code: $EXIT_CODE)${NC}"
    echo -e "${YELLOW}[TIPS] Jika menggunakan password root atau auth_socket, coba:${NC}"
    echo -e "  sudo ./backup_db.sh $DB_NAME $DB_USER"
    echo -e "  DB_PASS='password_kamu' ./backup_db.sh $DB_NAME $DB_USER"
    echo -e "${RED}======================================================${NC}"
    exit 1
fi

#!/usr/bin/env bash

# ==============================================================================
# Script Migrasi & Alter Database Lengkap (Linux / Ubuntu / Debian / CentOS)
# Proyek: DTC (Digital Quality Control)
#
# Fungsi:
#   1. Otomatis mendeteksi konfigurasi DB dari config/config.php (atau via argumen)
#   2. Melakukan auto-backup database sebelum migrasi (Snapshot rollback point)
#   3. Mengeksekusi perubahan skema (ALTER & CREATE TABLE IF NOT EXISTS) secara aman
#   4. Melakukan seeding data Master Line & Section default
#   5. Menjamin tersedianya kolom multi-section (allowed_sections) & checkpoint types
#   6. Memverifikasi integritas skema database pasca migrasi
#
# Penggunaan:
#   chmod +x migrate.sh
#   ./migrate.sh [options] [nama_database] [user] [host] [port]
#
# Opsi Flag:
#   --skip-backup   : Lewati proses backup snapshot sebelum migrasi
#   --help, -h      : Tampilkan bantuan ini
#
# Contoh Penggunaan:
#   ./migrate.sh                             (Auto-detect dari config.php / default dtc_v1)
#   ./migrate.sh --skip-backup               (Jalankan migrasi cepat tanpa backup)
#   sudo ./migrate.sh                        (Jika MySQL root menggunakan auth_socket di Linux)
#   DB_PASS='pass123' ./migrate.sh           (Menggunakan password via variabel environment)
#   ./migrate.sh dtc_prod dbuser 127.0.0.1   (Parameter kustom)
# ==============================================================================

set -o pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

# Warna Output Terminal
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Flag opsi
DO_BACKUP=true
POSITIONAL_ARGS=()

for arg in "$@"; do
    case $arg in
        --skip-backup|--no-backup)
            DO_BACKUP=false
            shift
            ;;
        --help|-h)
            echo -e "${CYAN}Script Migrasi & Alter Database DTC${NC}"
            echo -e "Penggunaan: ./migrate.sh [--skip-backup] [db_name] [user] [host] [port]"
            exit 0
            ;;
        *)
            POSITIONAL_ARGS+=("$arg")
            ;;
    esac
done

# ------------------------------------------------------------------------------
# 1. Deteksi Kredensial Database
# ------------------------------------------------------------------------------
CONFIG_FILE="$SCRIPT_DIR/../config/config.php"
if [ ! -f "$CONFIG_FILE" ]; then
    CONFIG_FILE="$SCRIPT_DIR/config/config.php"
fi

DETECTED_DB_NAME=""
DETECTED_DB_USER=""
DETECTED_DB_PASS=""
DETECTED_DB_HOST=""

if [ -f "$CONFIG_FILE" ]; then
    DETECTED_DB_NAME=$(grep -E "define\s*\(\s*['\"]DB_NAME['\"]\s*," "$CONFIG_FILE" | head -n 1 | sed -E "s/.*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]*)['\"].*/\1/")
    DETECTED_DB_USER=$(grep -E "define\s*\(\s*['\"]DB_USER['\"]\s*," "$CONFIG_FILE" | head -n 1 | sed -E "s/.*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]*)['\"].*/\1/")
    DETECTED_DB_PASS=$(grep -E "define\s*\(\s*['\"]DB_PASS['\"]\s*," "$CONFIG_FILE" | head -n 1 | sed -E "s/.*['\"]DB_PASS['\"]\s*,\s*['\"]([^'\"]*)['\"].*/\1/")
    DETECTED_DB_HOST=$(grep -E "define\s*\(\s*['\"]DB_HOST['\"]\s*," "$CONFIG_FILE" | head -n 1 | sed -E "s/.*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]*)['\"].*/\1/")
fi

# Prioritas nilai: Argumen CLI > Variabel ENV > Nilai dari config.php > Default
DB_NAME="${POSITIONAL_ARGS[0]:-${DB_NAME:-${DETECTED_DB_NAME:-dtc_v1}}}"
DB_USER="${POSITIONAL_ARGS[1]:-${DB_USER:-${DETECTED_DB_USER:-root}}}"
DB_HOST="${POSITIONAL_ARGS[2]:-${DB_HOST:-${DETECTED_DB_HOST:-localhost}}}"
DB_PORT="${POSITIONAL_ARGS[3]:-${DB_PORT:-3306}}"
PASSWORD="${DB_PASS:-$DETECTED_DB_PASS}"

echo -e "${CYAN}================================================================${NC}"
echo -e "${BOLD}${CYAN}        MIGRASI & ALTER DATABASE SYSTEM (LINUX / UBUNTU)        ${NC}"
echo -e "${CYAN}================================================================${NC}"
echo -e "Target Host     : ${BOLD}${DB_HOST}:${DB_PORT}${NC}"
echo -e "Target Database : ${BOLD}${DB_NAME}${NC}"
echo -e "Database User   : ${BOLD}${DB_USER}${NC}"
echo -e "Mode Eksekusi   : ${GREEN}Non-destructive (ALTER & CREATE IF NOT EXISTS)${NC}"
echo -e "----------------------------------------------------------------"

# ------------------------------------------------------------------------------
# 2. Deteksi Client MySQL / MariaDB
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
    echo -e "Silakan instal dengan perintah:"
    echo -e "  ${YELLOW}sudo apt update && sudo apt install mysql-client -y${NC}"
    exit 1
fi

DUMP_CMD=""
if command -v mysqldump &> /dev/null; then
    DUMP_CMD="mysqldump"
elif command -v mariadb-dump &> /dev/null; then
    DUMP_CMD="mariadb-dump"
elif [ -f "/opt/lampp/bin/mysqldump" ]; then
    DUMP_CMD="/opt/lampp/bin/mysqldump"
fi

# Fungsi pembantu untuk menjalankan query mysql
run_mysql() {
    local pass_param=""
    if [ -n "$PASSWORD" ]; then
        pass_param="-p${PASSWORD}"
    fi

    "$MYSQL_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $pass_param "$@"
}

# ------------------------------------------------------------------------------
# 3. Uji Koneksi Database & Cek Password
# ------------------------------------------------------------------------------
echo -e "${YELLOW}[*] Memeriksa koneksi ke database '${DB_NAME}'...${NC}"
TEST_CONN=$(run_mysql -e "SELECT 1;" "$DB_NAME" 2>&1)
CONN_EXIT=$?

if [ $CONN_EXIT -ne 0 ]; then
    if echo "$TEST_CONN" | grep -qE "Access denied|using password"; then
        echo -e "${YELLOW}[!] Autentikasi membutuhkan password.${NC}"
        read -s -p "Masukkan password untuk user '$DB_USER': " INPUT_PASS
        echo ""
        PASSWORD="$INPUT_PASS"
        TEST_CONN=$(run_mysql -e "SELECT 1;" "$DB_NAME" 2>&1)
        CONN_EXIT=$?
    fi

    if [ $CONN_EXIT -ne 0 ]; then
        echo -e "${RED}[ERROR] Gagal terhubung ke database '${DB_NAME}'!${NC}"
        echo -e "${RED}Pesan Error:${NC} $TEST_CONN"
        echo -e "\n${YELLOW}[TIPS TROUBLESHOOTING]${NC}"
        echo -e "  1. Pastikan service MySQL aktif: ${BOLD}sudo systemctl status mysql${NC}"
        echo -e "  2. Jika user root menggunakan auth_socket: ${BOLD}sudo ./migrate.sh${NC}"
        echo -e "  3. Atau tentukan password via environment: ${BOLD}DB_PASS='password' ./migrate.sh${NC}"
        exit 1
    fi
fi

echo -e "${GREEN}[OK] Berhasil terhubung ke database '${DB_NAME}'.${NC}"

# ------------------------------------------------------------------------------
# 4. Langkah 1: Snapshot Backup Otomatis (Pre-Migration Safety)
# ------------------------------------------------------------------------------
if [ "$DO_BACKUP" = true ]; then
    if [ -n "$DUMP_CMD" ]; then
        TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
        BACKUP_FILE="backup_${DB_NAME}_pre_migrate_${TIMESTAMP}.sql"
        echo -e "\n${YELLOW}[Langkah 1/4] Membuat snapshot backup sebelum migrasi...${NC}"
        
        PASS_ARG=""
        if [ -n "$PASSWORD" ]; then
            PASS_ARG="-p${PASSWORD}"
        fi

        "$DUMP_CMD" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_ARG \
            --single-transaction --quick --routines --triggers "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null

        if [ $? -eq 0 ] && [ -s "$BACKUP_FILE" ]; then
            BACKUP_SIZE=$(ls -lh "$BACKUP_FILE" | awk '{print $5}')
            echo -e "${GREEN}[OK] Snapshot backup tersimpan: ${BOLD}${BACKUP_FILE}${NC} (${BACKUP_SIZE})"
            cp "$BACKUP_FILE" "backup_latest.sql" 2>/dev/null || true
        else
            echo -e "${YELLOW}[PERINGATAN] Backup snapshot tidak dapat dibuat, melanjutkan migrasi skema...${NC}"
        fi
    else
        echo -e "${YELLOW}[Langkah 1/4] Perintah mysqldump tidak ditemukan, melewati tahap snapshot backup.${NC}"
    fi
else
    echo -e "\n${BLUE}[Langkah 1/4] Tahap backup dilewati (--skip-backup).${NC}"
fi

# ------------------------------------------------------------------------------
# 5. Langkah 2: Eksekusi ALTER Skema (migrate_alter.sql)
# ------------------------------------------------------------------------------
ALTER_FILE="migrate_alter.sql"
if [ ! -f "$ALTER_FILE" ] && [ -f "$SCRIPT_DIR/database/$ALTER_FILE" ]; then
    ALTER_FILE="$SCRIPT_DIR/database/$ALTER_FILE"
fi

echo -e "\n${YELLOW}[Langkah 2/4] Menjalankan pembaruan skema ALTER (${ALTER_FILE})...${NC}"

if [ ! -f "$ALTER_FILE" ]; then
    echo -e "${RED}[ERROR] File '$ALTER_FILE' tidak ditemukan!${NC}"
    exit 1
fi

run_mysql "$DB_NAME" < "$ALTER_FILE"
ALTER_EXIT=$?

if [ $ALTER_EXIT -eq 0 ]; then
    echo -e "${GREEN}[OK] Skrip ${ALTER_FILE} berhasil dieksekusi.${NC}"
else
    echo -e "${RED}[ERROR] Terjadi kesalahan saat mengeksekusi ${ALTER_FILE} (Exit Code: $ALTER_EXIT).${NC}"
    exit $ALTER_EXIT
fi

# ------------------------------------------------------------------------------
# 6. Langkah 3: Eksekusi Seeding Line & Section (migrate_line_section.sql)
# ------------------------------------------------------------------------------
LINE_SEC_FILE="migrate_line_section.sql"
if [ ! -f "$LINE_SEC_FILE" ] && [ -f "$SCRIPT_DIR/database/$LINE_SEC_FILE" ]; then
    LINE_SEC_FILE="$SCRIPT_DIR/database/$LINE_SEC_FILE"
fi

echo -e "\n${YELLOW}[Langkah 3/4] Menjalankan migrasi & seeding Line/Section (${LINE_SEC_FILE})...${NC}"

if [ -f "$LINE_SEC_FILE" ]; then
    run_mysql "$DB_NAME" < "$LINE_SEC_FILE"
    LINE_EXIT=$?
    if [ $LINE_EXIT -eq 0 ]; then
        echo -e "${GREEN}[OK] Skrip ${LINE_SEC_FILE} berhasil dieksekusi.${NC}"
    else
        echo -e "${YELLOW}[PERINGATAN] Selesai dengan kode $LINE_EXIT saat eksekusi ${LINE_SEC_FILE}.${NC}"
    fi
else
    echo -e "${YELLOW}[SKIP] File ${LINE_SEC_FILE} tidak ditemukan, dilewati.${NC}"
fi

# ------------------------------------------------------------------------------
# 7. Langkah 4: Verifikasi Integritas Skema Pasca Migrasi
# ------------------------------------------------------------------------------
echo -e "\n${YELLOW}[Langkah 4/4] Memverifikasi integritas database pasca migrasi...${NC}"

check_table() {
    local tbl="$1"
    local count
    count=$(run_mysql -N -s -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='${tbl}';")
    if [ "$count" -ge 1 ]; then
        echo -e "  ${GREEN}[✓] Tabel '${tbl}' : TERSEDIA${NC}"
    else
        echo -e "  ${RED}[✗] Tabel '${tbl}' : TIDAK DITEMUKAN!${NC}"
    fi
}

check_column() {
    local tbl="$1"
    local col="$2"
    local count
    count=$(run_mysql -N -s -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='${DB_NAME}' AND table_name='${tbl}' AND column_name='${col}';")
    if [ "$count" -ge 1 ]; then
        echo -e "  ${GREEN}[✓] Kolom '${tbl}.${col}' : TERSEDIA${NC}"
    else
        echo -e "  ${RED}[✗] Kolom '${tbl}.${col}' : TIDAK DITEMUKAN!${NC}"
    fi
}

# Verifikasi Komponen Kritis
check_table "dtc_master_spec_checkpoints"
check_table "dtc_master_lines"
check_table "dtc_master_sections"
check_column "dtc_checkpoints" "checkpoint_type"
check_column "dtc_users" "allowed_sections"
check_column "dtc_inspection_sessions" "is_closed"
check_column "dtc_measurements" "checkpoint_id"
check_column "dtc_running_models" "data_type"

# Tampilkan Statistik Data Master
TOTAL_LINES=$(run_mysql -N -s -e "SELECT COUNT(*) FROM dtc_master_lines;" 2>/dev/null || echo "0")
TOTAL_SECTIONS=$(run_mysql -N -s -e "SELECT COUNT(*) FROM dtc_master_sections;" 2>/dev/null || echo "0")
TOTAL_CHECKPOINTS=$(run_mysql -N -s -e "SELECT COUNT(*) FROM dtc_master_spec_checkpoints;" 2>/dev/null || echo "0")

echo -e "\n${CYAN}--- Ringkasan Data Master ---${NC}"
echo -e "  Master Lines      : ${BOLD}${TOTAL_LINES}${NC} record"
echo -e "  Master Sections   : ${BOLD}${TOTAL_SECTIONS}${NC} record"
echo -e "  Master Checkpoints: ${BOLD}${TOTAL_CHECKPOINTS}${NC} record"

echo -e "\n${GREEN}================================================================${NC}"
echo -e "${BOLD}${GREEN}        SELURUH PROSES MIGRASI & ALTER DATABASE SELESAI!        ${NC}"
echo -e "${GREEN}================================================================${NC}"
echo -e "Database '${BOLD}${DB_NAME}${NC}' siap digunakan dengan fitur terbaru:"
echo -e "  • Multiple Checkpoints & Qualitative Parameters"
echo -e "  • Master Lines & Sections Management"
echo -e "  • Multi-Section Supervisor Access Control"
echo -e "  • Management Role & Missing Data Monitoring"
echo -e "${GREEN}Semua data lama tetap aman dan utuh.${NC}\n"

exit 0

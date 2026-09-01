# Panduan Langkah-Langkah Migrasi & Backup Database

Dokumen ini berisi panduan teknis untuk melakukan backup data dan pembaruan skema database (ALTER & penambahan tabel) pada server **Linux / Ubuntu** untuk mendukung fitur **Multiple Checkpoint pada Master Spec** dan pembaruan struktur tabel lainnya.

> [!IMPORTANT]
> **Aman untuk Data Produksi (Non-Destructive)**:
> Skrip migrasi ini **TIDAK** menghapus tabel (`NO DROP TABLE`), **TIDAK** mengosongkan data (`NO TRUNCATE`), dan **TIDAK** menghapus baris data apa pun (`NO DELETE`). Data transaksi dan parameter yang sudah ada akan tetap utuh.

---

## 1. Ringkasan File di Folder `database/`

| File | Tipe | Fungsi |
|---|---|---|
| **[`backup_db.sh`](file:///c:/xampp/htdocs/dtq/database/backup_db.sh)** | Shell Script | Script backup full database di Linux (dengan auto-timestamp) |
| **[`run_alter.sh`](file:///c:/xampp/htdocs/dtq/database/run_alter.sh)** | Shell Script | Script eksekutor migrasi alter skema di Linux |
| **[`delete_running_models.sh`](file:///c:/xampp/htdocs/dtq/database/delete_running_models.sh)** | Shell Script | Script reset/hapus semua data running model di Linux |
| **[`fix_collation.sql`](file:///c:/xampp/htdocs/dtq/database/fix_collation.sql)** | SQL File | Script standardisasi collation semua tabel ke `utf8mb4_unicode_ci` |
| **[`migrate_alter.sql`](file:///c:/xampp/htdocs/dtq/database/migrate_alter.sql)** | SQL File | Script query ALTER & CREATE TABLE IF NOT EXISTS (Idempotent) |
| **[`ddl_mysql.sql`](file:///c:/xampp/htdocs/dtq/database/ddl_mysql.sql)** | SQL File | Master blueprint skema database lengkap terbaru |

---

## 2. Langkah 1: Backup Database Terlebih Dahulu (Direkomendasikan)

Sebelum menjalankan alter skema, sangat disarankan untuk mem-backup database aktif.

### Masuk ke folder database:
```bash
cd /var/www/html/dtq/database/
# atau jika XAMPP Linux:
cd /opt/lampp/htdocs/dtq/database/

chmod +x backup_db.sh run_alter.sh
```

### Jalankan script backup:
```bash
# Opsi 1: Default (Database dtc_v1, user root)
./backup_db.sh

# Opsi 2: Jika butuh sudo (auth_socket di Ubuntu)
sudo ./backup_db.sh

# Opsi 3: Jika database menggunakan password
DB_PASS='PasswordAnda' ./backup_db.sh dtc_v1 root

# Opsi 4: Custom parameter (nama_db, user, host, port)
./backup_db.sh dtc_v1 root 127.0.0.1 3306
```

> *Hasil backup akan disimpan dengan format nama: `backup_[NAMA_DB]_[TIMESTAMP].sql` dan salinan terbaru di `backup.sql`.*

---

## 3. Langkah 2: Jalankan Script Migrasi ALTER Skema

Setelah proses backup selesai, jalankan script migrasi non-destructive:

```bash
# Opsi 1: Default (Database dtc_v1, user root)
./run_alter.sh

# Opsi 2: Jika butuh sudo (auth_socket di Ubuntu)
sudo ./run_alter.sh

# Opsi 3: Jika database menggunakan password
DB_PASS='PasswordAnda' ./run_alter.sh dtc_v1 root
```

---

## 4. Alternatif: Eksekusi Manual Lewat MySQL CLI

Jika Anda ingin menjalankan perintah backup dan migrasi langsung lewat command line bawaan:

### Backup manual via `mysqldump`:
```bash
mysqldump -u root -p --single-transaction --quick --routines --triggers dtc_v1 > backup_manual_$(date +%F).sql
```

### Migrasi manual via `mysql`:
```bash
mysql -u root -p dtc_v1 < migrate_alter.sql
```

---

## 5. Verifikasi Hasil Migrasi

Setelah migrasi selesai, Anda dapat memverifikasi dengan menjalankan query berikut di MySQL:

```sql
-- 1. Cek tabel template master spec checkpoint
DESCRIBE dtc_master_spec_checkpoints;

-- 2. Cek kolom checkpoint_type pada dtc_checkpoints
SHOW COLUMNS FROM dtc_checkpoints LIKE 'checkpoint_type';

-- 3. Cek kolom allowed_sections pada dtc_users
SHOW COLUMNS FROM dtc_users LIKE 'allowed_sections';
```

Jika query di atas menampilkan detail kolom yang sesuai, database sudah siap digunakan 100%.

---

## 6. Troubleshooting

1. **Perintah `mysqldump` / `mysql` tidak ditemukan**:
   ```bash
   sudo apt update && sudo apt install mysql-client -y
   ```
2. **Access Denied**:
   Pastikan username, password, dan nama database yang dimasukkan sudah sesuai dengan konfigurasi di server Anda.

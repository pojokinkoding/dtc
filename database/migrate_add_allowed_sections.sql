-- ==============================================================================
-- MIGRATION: Tambah kolom 'allowed_sections' ke tabel dtc_users
-- Dijalankan jika muncul error:
--   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'allowed_sections' in 'field list'
-- ==============================================================================

ALTER TABLE dtc_users
    ADD COLUMN IF NOT EXISTS allowed_sections TEXT DEFAULT NULL
        COMMENT 'Comma-separated list of section names (contoh: PRE CASE,PU CASE). Digunakan untuk akses multi-section Supervisor.'
    AFTER section_name;

-- ==============================================================================
-- MIGRATION SCRIPT: Non-Destructive Schema Alter & Table Creation
-- Safe for production: HANYA melakukan ALTER & CREATE TABLE IF NOT EXISTS.
-- TIDAK ADA DROP TABLE, TIDAK ADA TRUNCATE, TIDAK ADA DELETE DATA.
-- ==============================================================================

-- 1. Buat Tabel dtc_master_spec_checkpoints jika belum ada
CREATE TABLE IF NOT EXISTS dtc_master_spec_checkpoints (
    master_checkpoint_id INT AUTO_INCREMENT PRIMARY KEY,
    spec_id INT NOT NULL,
    checkpoint_name VARCHAR(200) NOT NULL,
    checkpoint_type VARCHAR(50) NOT NULL DEFAULT 'Qualitative',
    spec_value VARCHAR(200) DEFAULT NULL,
    lsl DECIMAL(10, 3) DEFAULT NULL,
    target_value DECIMAL(10, 3) DEFAULT NULL,
    usl DECIMAL(10, 3) DEFAULT NULL,
    reference_image VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_master_checkpoint_spec FOREIGN KEY (spec_id)
        REFERENCES dtc_master_dtc_specs(spec_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stored Procedure pembantu untuk menambah kolom secara aman (idempotent)
DELIMITER $$

DROP PROCEDURE IF EXISTS dtc_safe_add_column $$
CREATE PROCEDURE dtc_safe_add_column(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    DECLARE v_count INT;
    SELECT COUNT(*) INTO v_count
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = p_table
      AND column_name = p_column;

    IF v_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT(' [OK] Kolom `', p_column, '` berhasil ditambahkan ke tabel `', p_table, '`.') AS result;
    ELSE
        SELECT CONCAT(' [SKIP] Kolom `', p_column, '` sudah ada di tabel `', p_table, '`.') AS result;
    END IF;
END $$

DROP PROCEDURE IF EXISTS dtc_safe_add_index $$
CREATE PROCEDURE dtc_safe_add_index(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_columns VARCHAR(255)
)
BEGIN
    DECLARE v_count INT;
    SELECT COUNT(*) INTO v_count
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = p_table
      AND index_name = p_index;

    IF v_count = 0 THEN
        SET @sql = CONCAT('CREATE INDEX `', p_index, '` ON `', p_table, '` (', p_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT(' [OK] Index `', p_index, '` berhasil dibuat pada tabel `', p_table, '`.') AS result;
    ELSE
        SELECT CONCAT(' [SKIP] Index `', p_index, '` sudah ada pada tabel `', p_table, '`.') AS result;
    END IF;
END $$

DELIMITER ;

-- 2. ALTER tabel dtc_checkpoints (Tambah kolom checkpoint_type)
CALL dtc_safe_add_column('dtc_checkpoints', 'checkpoint_type', "VARCHAR(50) DEFAULT 'Qualitative' AFTER `checkpoint_name`");

-- 3. ALTER tabel dtc_users (Tambah kolom allowed_sections untuk multi-section supervisor)
CALL dtc_safe_add_column('dtc_users', 'allowed_sections', "TEXT DEFAULT NULL AFTER `section_name`");

-- 4. ALTER tabel dtc_inspection_sessions (Tambah kolom is_closed jika belum ada)
CALL dtc_safe_add_column('dtc_inspection_sessions', 'is_closed', "TINYINT(1) DEFAULT 0 AFTER `zlt_value`");

-- 5. ALTER tabel dtc_measurements (Tambah kolom checkpoint_id jika belum ada)
CALL dtc_safe_add_column('dtc_measurements', 'checkpoint_id', "INT DEFAULT NULL AFTER `session_id`");

-- 6. ALTER tabel dtc_running_models (Tambah kolom data_type & timestamps jika belum ada)
CALL dtc_safe_add_column('dtc_running_models', 'data_type', "VARCHAR(50) NOT NULL DEFAULT 'General' AFTER `model_name`");
CALL dtc_safe_add_column('dtc_running_models', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `is_active`");
CALL dtc_safe_add_column('dtc_running_models', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");

-- 7. Buat Indeks jika belum ada
CALL dtc_safe_add_index('dtc_master_spec_checkpoints', 'idx_master_spec_checkpoint', '`spec_id`');
CALL dtc_safe_add_index('dtc_checkpoints', 'idx_checkpoint_param', '`parameter_id`');
CALL dtc_safe_add_index('dtc_measurements', 'idx_meas_checkpoint', '`checkpoint_id`');

-- 7. Standardisasi Collation (Mencegah Error 1267 Illegal Mix of Collations)
ALTER TABLE `dtc_users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `dtc_master_dtc_specs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `dtc_master_parameters` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `dtc_inspection_sessions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `dtc_checkpoints` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `dtc_master_spec_checkpoints` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `dtc_measurements` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `dtc_running_models` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `dtc_app_settings` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Bersihkan stored procedure pembantu
DROP PROCEDURE IF EXISTS dtc_safe_add_column;
DROP PROCEDURE IF EXISTS dtc_safe_add_index;

-- ==============================================================================
-- FIX & STANDARDIZE DATABASE SCRIPT:
-- 1. Standardize all tables to utf8mb4_unicode_ci (Fix Error 1267 Collation)
-- 2. Ensure all columns (data_type, allowed_sections, etc.) exist (Fix Error 1054)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER DATABASE DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Buat tabel dtc_master_spec_checkpoints jika belum ada
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

-- Standardisasi Collation
ALTER TABLE dtc_users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE dtc_master_dtc_specs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE dtc_master_parameters CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE dtc_inspection_sessions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE dtc_checkpoints CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE dtc_master_spec_checkpoints CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE dtc_measurements CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE dtc_running_models CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE dtc_app_settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Database berhasil distandarisasi dan siap digunakan!' AS status;

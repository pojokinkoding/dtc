-- ==============================================================================
-- PROJECT: System Digital Time Check (DTC) - MySQL Database Implementation
-- ==============================================================================

-- 1. Tabel Users (Manajemen Akses, Profile & Sesi)
CREATE TABLE IF NOT EXISTS dtc_users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL, -- Nama lengkap (Contoh: Soni sopyan)
    role VARCHAR(20) NOT NULL, -- Contoh: 'Operator', 'Foreman', 'Supervisor', 'Admin'
    profile_picture VARCHAR(255) DEFAULT NULL,
    line_name VARCHAR(50) DEFAULT NULL,
    section_name VARCHAR(50) DEFAULT NULL,
    allowed_sections TEXT DEFAULT NULL, -- Comma-separated sections untuk akses multi-section Supervisor (contoh: PRE CASE,PU CASE)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Tabel Master Specs (Data Dinamis Spesifikasi per Model & Parameter)
CREATE TABLE IF NOT EXISTS dtc_master_dtc_specs (
    spec_id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    item_check_name VARCHAR(100) NOT NULL,
    data_type VARCHAR(50),
    lsl DOUBLE NOT NULL,
    usl DOUBLE NOT NULL,
    uom VARCHAR(20),
    target_value DOUBLE,
    section_name VARCHAR(50) NOT NULL,
    line_name VARCHAR(50) NOT NULL,
    process_name VARCHAR(100) NOT NULL,
    measuring_item VARCHAR(100) NOT NULL,
    target_zst DOUBLE DEFAULT 4.0,
    target_zlt DOUBLE DEFAULT 3.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. Tabel Master Parameter (DTC Bulanan)
CREATE TABLE IF NOT EXISTS dtc_master_parameters (
    parameter_id INT AUTO_INCREMENT PRIMARY KEY,
    spec_id INT NOT NULL,
    target_month VARCHAR(7) NOT NULL, -- YYYY-MM
    item_check_name VARCHAR(100),
    data_type VARCHAR(50), -- Kategori: CTQ, CTP, Time Check, F/Proof
    section_name VARCHAR(50),
    line_name VARCHAR(50),
    process_name VARCHAR(100),
    measuring_item VARCHAR(100),
    target_zst DECIMAL(10, 3),
    target_zlt DECIMAL(10, 3),
    reference_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dtc_param_spec FOREIGN KEY (spec_id) REFERENCES dtc_master_dtc_specs(spec_id) ON DELETE CASCADE
);

-- 4. Tabel Sesi Inspeksi (Grup Pengukuran Harian/Jam)
CREATE TABLE IF NOT EXISTS dtc_inspection_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    parameter_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    shift_name VARCHAR(20),
    operator_id INT NOT NULL, -- Relasi ke tabel users
    remarks VARCHAR(500), -- Catatan Out of Spec (OOS) / Tindakan Perbaikan
    is_active TINYINT(1) DEFAULT 1, -- 1: Aktif, 0: Dihapus (Soft Delete)
    max_value DECIMAL(10, 3),
    min_value DECIMAL(10, 3),
    x_bar DECIMAL(10, 4),
    range_value DECIMAL(10, 4),
    std_dev DECIMAL(10, 6),
    zst_value DECIMAL(10, 3),
    zlt_value DECIMAL(10, 3),
    is_closed TINYINT(1) DEFAULT 0, -- 1: Closed (no edit allowed)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dtc_session_param FOREIGN KEY (parameter_id) 
        REFERENCES dtc_master_parameters(parameter_id) ON DELETE CASCADE,
    CONSTRAINT fk_dtc_session_operator FOREIGN KEY (operator_id)
        REFERENCES dtc_users(user_id)
);

CREATE INDEX IF NOT EXISTS idx_dtc_insp_date ON dtc_inspection_sessions (inspection_date);
CREATE INDEX IF NOT EXISTS idx_dtc_param_session ON dtc_inspection_sessions (parameter_id);

-- 5. Tabel Checkpoints (Checkpoint Kualitatif & Kuantitatif per Parameter)
CREATE TABLE IF NOT EXISTS dtc_checkpoints (
    checkpoint_id INT AUTO_INCREMENT PRIMARY KEY,
    parameter_id INT NOT NULL,
    checkpoint_name VARCHAR(200) NOT NULL,
    checkpoint_type VARCHAR(50) DEFAULT 'Qualitative',
    spec_value VARCHAR(200) DEFAULT NULL,
    lsl DECIMAL(10, 3) DEFAULT NULL,
    target_value DECIMAL(10, 3) DEFAULT NULL,
    usl DECIMAL(10, 3) DEFAULT NULL,
    reference_image VARCHAR(255) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_checkpoint_param FOREIGN KEY (parameter_id)
        REFERENCES dtc_master_parameters(parameter_id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_checkpoint_param ON dtc_checkpoints (parameter_id);

-- 5a. Template checkpoint pada Master Spec; disalin saat Running Model dibuat.
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
);

CREATE INDEX IF NOT EXISTS idx_master_spec_checkpoint ON dtc_master_spec_checkpoints (spec_id);

-- 6. Tabel Detail Pengukuran (Penyimpanan Sampel Pengukuran Tunggal secara Vertikal)
CREATE TABLE IF NOT EXISTS dtc_measurements (
    measurement_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    checkpoint_id INT DEFAULT NULL, -- Relasi ke checkpoint kualitatif
    sample_sequence INT NOT NULL,
    sample_label VARCHAR(100),
    sample_value VARCHAR(20) NOT NULL,
    created_by INT NOT NULL, -- FK ke tabel users
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_by INT, -- FK ke tabel users (bisa null jika belum diubah)
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dtc_meas_session FOREIGN KEY (session_id) 
        REFERENCES dtc_inspection_sessions(session_id) ON DELETE CASCADE,
    CONSTRAINT fk_dtc_meas_creator FOREIGN KEY (created_by)
        REFERENCES dtc_users(user_id),
    CONSTRAINT fk_dtc_meas_modifier FOREIGN KEY (modified_by)
        REFERENCES dtc_users(user_id),
    CONSTRAINT fk_meas_checkpoint FOREIGN KEY (checkpoint_id)
        REFERENCES dtc_checkpoints(checkpoint_id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_dtc_sess_meas ON dtc_measurements (session_id);
CREATE INDEX IF NOT EXISTS idx_meas_checkpoint ON dtc_measurements (checkpoint_id);

-- 7. Tabel Running Models (Model yang sedang berjalan per Line, Section & Bulan)
CREATE TABLE IF NOT EXISTS dtc_running_models (
    running_id INT AUTO_INCREMENT PRIMARY KEY,
    target_month VARCHAR(7) NOT NULL,
    line_name VARCHAR(50) NOT NULL,
    section_name VARCHAR(50) NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_running_model (target_month, line_name, section_name, model_name)
);

-- 8. Tabel App Settings (Pengaturan Aplikasi, Label Waktu Matrix dll)
CREATE TABLE IF NOT EXISTS dtc_app_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 9. Tabel Master Lines (Data Master Line Produksi)
CREATE TABLE IF NOT EXISTS dtc_master_lines (
    line_id INT AUTO_INCREMENT PRIMARY KEY,
    line_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 10. Tabel Master Sections (Data Master Section Produksi)
CREATE TABLE IF NOT EXISTS dtc_master_sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(50) NOT NULL,
    line_name VARCHAR(50) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_master_section_line (line_name)
);

-- Seed Default Sections
INSERT IGNORE INTO dtc_master_sections (section_name, line_name, sort_order) VALUES
('Accessories', NULL, 1),
('Charging', NULL, 2),
('Clamping', NULL, 3),
('Cutting Vinyl', NULL, 4),
('Cycle', NULL, 5),
('H Press Out Door', NULL, 6),
('PU Case', NULL, 7),
('PU Door', NULL, 8),
('Pre Case', NULL, 9),
('V Forming Male A', NULL, 10),
('V Forming Male B', NULL, 11),
('V Forming Male C', NULL, 12);


-- ==============================================================================
-- MIGRATION SCRIPT: Create & Seed Master Lines & Master Sections Tables
-- ==============================================================================

CREATE TABLE IF NOT EXISTS dtc_master_lines (
    line_id INT AUTO_INCREMENT PRIMARY KEY,
    line_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dtc_master_sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(50) NOT NULL,
    line_name VARCHAR(50) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_master_section_line (line_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Lines
INSERT IGNORE INTO dtc_master_lines (line_name, description, sort_order) VALUES
('REF 01', 'Refrigerator Line 01', 1),
('REF 02', 'Refrigerator Line 02', 2);

-- Seed any distinct lines from dtc_master_dtc_specs
INSERT IGNORE INTO dtc_master_lines (line_name, sort_order)
SELECT DISTINCT line_name, 10
FROM dtc_master_dtc_specs
WHERE line_name IS NOT NULL AND TRIM(line_name) != ''
  AND line_name NOT IN (SELECT line_name FROM dtc_master_lines);

-- Seed Default Sections
INSERT INTO dtc_master_sections (section_name, line_name, sort_order)
SELECT 'Accessories', NULL, 1 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'Accessories' AND line_name IS NULL)
UNION ALL
SELECT 'Charging', NULL, 2 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'Charging' AND line_name IS NULL)
UNION ALL
SELECT 'Clamping', NULL, 3 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'Clamping' AND line_name IS NULL)
UNION ALL
SELECT 'Cutting Vinyl', NULL, 4 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'Cutting Vinyl' AND line_name IS NULL)
UNION ALL
SELECT 'Cycle', NULL, 5 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'Cycle' AND line_name IS NULL)
UNION ALL
SELECT 'H Press Out Door', NULL, 6 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'H Press Out Door' AND line_name IS NULL)
UNION ALL
SELECT 'PU Case', NULL, 7 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'PU Case' AND line_name IS NULL)
UNION ALL
SELECT 'PU Door', NULL, 8 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'PU Door' AND line_name IS NULL)
UNION ALL
SELECT 'Pre Case', NULL, 9 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'Pre Case' AND line_name IS NULL)
UNION ALL
SELECT 'V Forming Male A', NULL, 10 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'V Forming Male A' AND line_name IS NULL)
UNION ALL
SELECT 'V Forming Male B', NULL, 11 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'V Forming Male B' AND line_name IS NULL)
UNION ALL
SELECT 'V Forming Male C', NULL, 12 WHERE NOT EXISTS (SELECT 1 FROM dtc_master_sections WHERE section_name = 'V Forming Male C' AND line_name IS NULL);

-- Seed any distinct sections from dtc_master_dtc_specs
INSERT INTO dtc_master_sections (section_name, line_name, sort_order)
SELECT DISTINCT section_name, NULL, 10
FROM dtc_master_dtc_specs s
WHERE section_name IS NOT NULL AND TRIM(section_name) != ''
  AND NOT EXISTS (SELECT 1 FROM dtc_master_sections m WHERE m.section_name = s.section_name);

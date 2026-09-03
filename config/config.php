<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dtc_v1');
// hosting
// define('DB_HOST', 'meccadigital.co.id');
// define('DB_USER', 'meccadig_dtc');
// define('DB_PASS', 'meccadigital@123');
// define('DB_NAME', 'meccadig_dtc');

if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        try {
            // Construct DSN using the DB_HOST provided
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $conn = new PDO($dsn, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
            $conn->exec("SET time_zone = '+07:00'");
            return $conn;
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('getIPAccessFilterSQL')) {
    function getIPAccessFilterSQL($lineField = 'COALESCE(p.line_name, spec.line_name)', $sectionField = 'COALESCE(p.section_name, spec.section_name)') {
        // Admin, Management, and Supervisor roles get access across all IPs
        $role = strtolower(trim($_SESSION['role'] ?? ''));
        if ($role === 'admin' || strpos($role, 'management') !== false || strpos($role, 'supervisor') !== false) {
            return "";
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $ipMap = [
            // REF 01
            '10.221.179.149' => ['line' => 'REF 01', 'section' => ['PRE CASE']],
            '10.221.179.194' => ['line' => 'REF 01', 'section' => ['PU CASE']],
            '10.221.176.36'  => ['line' => 'REF 01', 'section' => ['PU DOOR']],
            '10.221.179.30'  => ['line' => 'REF 01', 'section' => ['Accessories']],
            '10.221.179.234' => ['line' => 'REF 01', 'section' => ['Cycle']],
            
            // REF 02
            '10.221.179.51'  => ['line' => 'REF 02', 'section' => ['Cycle']],
            '10.221.179.28'  => ['line' => 'REF 02', 'section' => ['Accessories']],
            '10.221.179.59'  => ['line' => 'REF 02', 'section' => ['PU DOOR']],
            '10.221.178.81'  => ['line' => 'REF 02', 'section' => ['Pre case & PU Case', 'Pre Case', 'PU Case']] 
        ];

        if (isset($ipMap[$ip])) {
            $rule = $ipMap[$ip];
            $line = addslashes($rule['line']);
            $sections = array_map(function ($s) {
                return "'" . addslashes(strtoupper($s)) . "'";
            }, $rule['section']);
            $sectionIn = implode(',', $sections);

            return " AND UPPER($lineField) = UPPER('$line') AND UPPER($sectionField) IN ($sectionIn) ";
        }
        
        return ""; 
    }
}

if (!function_exists('getUserAccessFilterSQL')) {
    function getUserAccessFilterSQL($lineField = 'COALESCE(p.line_name, spec.line_name)', $sectionField = 'COALESCE(p.section_name, spec.section_name)') {
        // Only apply if user is logged in
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return "";
        }
        
        $role = strtolower(trim($_SESSION['role'] ?? ''));

        // Admin & Management get access to everything across all stations and lines
        if ($role === 'admin' || $role === 'management' || strpos($role, 'management') !== false) {
            return "";
        }

        $sql = "";

        // Check for dynamic multi-section supervisor access (allowed_sections)
        $allowedSectionsStr = $_SESSION['allowed_sections'] ?? '';
        if (!empty($allowedSectionsStr)) {
            $secArray = array_map('trim', explode(',', $allowedSectionsStr));
            $secArray = array_filter($secArray);
            if (!empty($secArray)) {
                $escapedSecs = array_map(function($s) {
                    return "'" . addslashes(strtoupper($s)) . "'";
                }, $secArray);
                $sql .= " AND UPPER($sectionField) IN (" . implode(',', $escapedSecs) . ") ";
            }
        } else if (!empty($_SESSION['section_name'])) {
            $sql .= " AND UPPER($sectionField) = UPPER('" . addslashes($_SESSION['section_name']) . "') ";
        }

        // Filter by Line if assigned
        if (!empty($_SESSION['line_name'])) {
            $sql .= " AND UPPER($lineField) = UPPER('" . addslashes($_SESSION['line_name']) . "') ";
        }
        
        return $sql;
    }
}

if (!function_exists('ensureMasterLinesAndSectionsTables')) {
    function ensureMasterLinesAndSectionsTables(PDO $conn): void {
        static $ensured = false;
        if ($ensured) return;

        $conn->exec("CREATE TABLE IF NOT EXISTS dtc_master_lines (
            line_id INT AUTO_INCREMENT PRIMARY KEY,
            line_name VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS dtc_master_sections (
            section_id INT AUTO_INCREMENT PRIMARY KEY,
            section_name VARCHAR(50) NOT NULL,
            line_name VARCHAR(50) DEFAULT NULL,
            description VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed lines if empty
        $cntLines = $conn->query("SELECT COUNT(*) FROM dtc_master_lines")->fetchColumn();
        if ((int)$cntLines === 0) {
            $conn->exec("INSERT IGNORE INTO dtc_master_lines (line_name, description, sort_order) VALUES
                ('REF 01', 'Refrigerator Line 01', 1),
                ('REF 02', 'Refrigerator Line 02', 2)");

            $conn->exec("INSERT IGNORE INTO dtc_master_lines (line_name, sort_order)
                SELECT DISTINCT line_name, 10
                FROM dtc_master_dtc_specs
                WHERE line_name IS NOT NULL AND TRIM(line_name) != ''");
        }

        // Seed sections if empty
        $cntSections = $conn->query("SELECT COUNT(*) FROM dtc_master_sections")->fetchColumn();
        if ((int)$cntSections === 0) {
            $conn->exec("INSERT INTO dtc_master_sections (section_name, line_name, sort_order) VALUES
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
                ('V Forming Male C', NULL, 12)");

            $conn->exec("INSERT INTO dtc_master_sections (section_name, line_name, sort_order)
                SELECT DISTINCT section_name, NULL, 20
                FROM dtc_master_dtc_specs s
                WHERE section_name IS NOT NULL AND TRIM(section_name) != ''
                  AND NOT EXISTS (SELECT 1 FROM dtc_master_sections m WHERE UPPER(m.section_name) = UPPER(s.section_name))");
        }

        $ensured = true;
    }
}
?>

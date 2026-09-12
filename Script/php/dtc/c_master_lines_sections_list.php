<?php
// c_master_lines_sections_list.php
require_once __DIR__ . '/../../../config/config.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $conn = getDBConnection();
    if (function_exists('ensureMasterLinesAndSectionsTables')) {
        try {
            ensureMasterLinesAndSectionsTables($conn);
        } catch (Throwable $t) {}
    }

    $linesMap = [];
    try {
        $stmtLines = $conn->query("SELECT line_id, line_name, description, sort_order, created_at, updated_at 
                                  FROM dtc_master_lines 
                                  ORDER BY sort_order ASC, line_name ASC");
        if ($stmtLines) {
            while ($r = $stmtLines->fetch(PDO::FETCH_ASSOC)) {
                $name = trim($r['line_name']);
                if ($name !== '') $linesMap[$name] = $r;
            }
        }
    } catch (Throwable $t) {}

    // Fallback/merge from dtc_master_dtc_specs if needed
    try {
        $stmtLinesSpecs = $conn->query("SELECT DISTINCT line_name FROM dtc_master_dtc_specs WHERE line_name IS NOT NULL AND TRIM(line_name) != '' ORDER BY line_name ASC");
        if ($stmtLinesSpecs) {
            $fakeId = 9990;
            while ($r = $stmtLinesSpecs->fetch(PDO::FETCH_ASSOC)) {
                $name = trim($r['line_name']);
                if ($name !== '' && !isset($linesMap[$name])) {
                    $linesMap[$name] = [
                        'line_id' => ++$fakeId,
                        'line_name' => $name,
                        'description' => 'From specs',
                        'sort_order' => 10,
                        'created_at' => null,
                        'updated_at' => null
                    ];
                }
            }
        }
    } catch (Throwable $t) {}

    $lines = array_values($linesMap);

    $sectionsMap = [];
    try {
        $stmtSections = $conn->query("SELECT section_id, section_name, line_name, description, sort_order, created_at, updated_at 
                                     FROM dtc_master_sections 
                                     ORDER BY sort_order ASC, section_name ASC");
        if ($stmtSections) {
            while ($r = $stmtSections->fetch(PDO::FETCH_ASSOC)) {
                $name = trim($r['section_name']);
                if ($name !== '') $sectionsMap[$name] = $r;
            }
        }
    } catch (Throwable $t) {}

    // Fallback/merge from dtc_master_dtc_specs if needed
    try {
        $stmtSecsSpecs = $conn->query("SELECT DISTINCT section_name, line_name FROM dtc_master_dtc_specs WHERE section_name IS NOT NULL AND TRIM(section_name) != '' ORDER BY section_name ASC");
        if ($stmtSecsSpecs) {
            $fakeId = 9990;
            while ($r = $stmtSecsSpecs->fetch(PDO::FETCH_ASSOC)) {
                $name = trim($r['section_name']);
                if ($name !== '' && !isset($sectionsMap[$name])) {
                    $sectionsMap[$name] = [
                        'section_id' => ++$fakeId,
                        'section_name' => $name,
                        'line_name' => $r['line_name'] ?? null,
                        'description' => 'From specs',
                        'sort_order' => 20,
                        'created_at' => null,
                        'updated_at' => null
                    ];
                }
            }
        }
    } catch (Throwable $t) {}

    $sections = array_values($sectionsMap);

    echo json_encode([
        'status' => 'success',
        'lines' => $lines,
        'sections' => $sections
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'lines' => [],
        'sections' => []
    ], JSON_INVALID_UTF8_SUBSTITUTE);
}
?>

<?php
// c_dtc_master_data.php
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
        } catch (Throwable $t) {
            error_log("ensureMasterLinesAndSectionsTables failed: " . $t->getMessage());
        }
    }

    $ipLineFilter = getIPAccessFilterSQL('line_name', 'section_name');
    $userLineFilter = getUserAccessFilterSQL('line_name', 'section_name');

    // 1. Fetch unique lines from dtc_master_lines AND dtc_master_dtc_specs (MERGE)
    $linesMap = [];
    try {
        $stmt_lines = $conn->query("SELECT DISTINCT line_name FROM dtc_master_lines WHERE line_name IS NOT NULL AND TRIM(line_name) != '' ORDER BY sort_order ASC, line_name ASC");
        if ($stmt_lines) {
            while ($row = $stmt_lines->fetch(PDO::FETCH_ASSOC)) {
                $name = trim($row['line_name']);
                if ($name !== '') $linesMap[$name] = ['line_name' => $name];
            }
        }
    } catch (Throwable $t) {}

    try {
        $stmt_lines_fb = $conn->query("SELECT DISTINCT line_name FROM dtc_master_dtc_specs WHERE line_name IS NOT NULL AND TRIM(line_name) != '' ORDER BY line_name ASC");
        if ($stmt_lines_fb) {
            while ($row = $stmt_lines_fb->fetch(PDO::FETCH_ASSOC)) {
                $name = trim($row['line_name']);
                if ($name !== '' && !isset($linesMap[$name])) {
                    $linesMap[$name] = ['line_name' => $name];
                }
            }
        }
    } catch (Throwable $t) {}

    if (empty($linesMap)) {
        $linesMap['REF 01'] = ['line_name' => 'REF 01'];
        $linesMap['REF 02'] = ['line_name' => 'REF 02'];
    }
    $lines = array_values($linesMap);

    // 2. Fetch unique sections from dtc_master_sections AND dtc_master_dtc_specs (MERGE)
    $sectionsMap = [];
    try {
        $stmt_sections = $conn->query("SELECT section_id, section_name, line_name FROM dtc_master_sections WHERE section_name IS NOT NULL AND TRIM(section_name) != '' ORDER BY sort_order ASC, section_name ASC");
        if ($stmt_sections) {
            while ($row = $stmt_sections->fetch(PDO::FETCH_ASSOC)) {
                $sName = trim($row['section_name']);
                if ($sName !== '') {
                    $sectionsMap[$sName] = [
                        'section_name' => $sName,
                        'line_name' => $row['line_name'] ?? null
                    ];
                }
            }
        }
    } catch (Throwable $t) {}

    try {
        $stmt_sections_fb = $conn->query("SELECT DISTINCT section_name, line_name FROM dtc_master_dtc_specs WHERE section_name IS NOT NULL AND TRIM(section_name) != '' ORDER BY section_name ASC");
        if ($stmt_sections_fb) {
            while ($row = $stmt_sections_fb->fetch(PDO::FETCH_ASSOC)) {
                $sName = trim($row['section_name']);
                if ($sName !== '' && !isset($sectionsMap[$sName])) {
                    $sectionsMap[$sName] = [
                        'section_name' => $sName,
                        'line_name' => $row['line_name'] ?? null
                    ];
                }
            }
        }
    } catch (Throwable $t) {}

    if (empty($sectionsMap)) {
        $defaultSections = ['Accessories', 'Charging', 'Clamping', 'Cutting Vinyl', 'Cycle', 'H Press Out Door', 'PU Case', 'PU Door', 'Pre Case', 'V Forming Male A', 'V Forming Male B', 'V Forming Male C'];
        foreach ($defaultSections as $ds) {
            $sectionsMap[$ds] = ['section_name' => $ds, 'line_name' => null];
        }
    }
    $sections = array_values($sectionsMap);

    // 3. Fetch specs
    $specs = [];
    try {
        $stmt = $conn->prepare("SELECT spec_id, model_name, item_check_name, sub_item_check_name, data_type, section_name, line_name, process_name, measuring_item, lsl, usl, target_value, target_zst, target_zlt FROM dtc_master_dtc_specs WHERE 1=1 " . $ipLineFilter . $userLineFilter . " ORDER BY spec_id ASC");
        $stmt->execute();
        $specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        try {
            $stmt = $conn->prepare("SELECT spec_id, model_name, item_check_name, data_type, section_name, line_name, process_name, measuring_item, lsl, usl, target_value, target_zst, target_zlt FROM dtc_master_dtc_specs WHERE 1=1 " . $ipLineFilter . $userLineFilter . " ORDER BY spec_id ASC");
            $stmt->execute();
            $specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $t2) {
            $specs = [];
        }
    }

    // Categories requested by user
    $dtc_categories = [
        ["category_name" => "CTQ"],
        ["category_name" => "CTP"],
        ["category_name" => "Time Check"],
        ["category_name" => "F/Proof"]
    ];

    echo json_encode([
        "status" => "success",
        "lines" => $lines,
        "sections" => $sections,
        "specs" => $specs,
        "dtc_categories" => $dtc_categories
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    // Attempt emergency direct query for lines from dtc_master_lines before falling back
    $emergencyLines = [];
    try {
        if (isset($conn) && $conn) {
            $emStmt = $conn->query("SELECT DISTINCT line_name FROM dtc_master_lines WHERE line_name IS NOT NULL AND TRIM(line_name) != '' ORDER BY sort_order ASC, line_name ASC");
            if ($emStmt) {
                while ($emRow = $emStmt->fetch(PDO::FETCH_ASSOC)) {
                    $emName = trim($emRow['line_name']);
                    if ($emName !== '') $emergencyLines[] = ['line_name' => $emName];
                }
            }
        }
    } catch (Throwable $t) {}

    if (empty($emergencyLines)) {
        try {
            if (isset($conn) && $conn) {
                $emStmt2 = $conn->query("SELECT DISTINCT line_name FROM dtc_master_dtc_specs WHERE line_name IS NOT NULL AND TRIM(line_name) != '' ORDER BY line_name ASC");
                if ($emStmt2) {
                    while ($emRow = $emStmt2->fetch(PDO::FETCH_ASSOC)) {
                        $emName = trim($emRow['line_name']);
                        if ($emName !== '') $emergencyLines[] = ['line_name' => $emName];
                    }
                }
            }
        } catch (Throwable $t) {}
    }

    echo json_encode([
        "status" => "fallback",
        "error" => $e->getMessage(),
        "lines" => !empty($emergencyLines) ? $emergencyLines : [
            ['line_name' => 'REF 01'],
            ['line_name' => 'REF 02']
        ],
        "sections" => !empty($sections) ? $sections : [
            ['section_name' => 'Cycle'],
            ['section_name' => 'PU Door'],
            ['section_name' => 'Pre Case'],
            ['section_name' => 'PU Case'],
            ['section_name' => 'Accessories']
        ],
        "specs" => [],
        "dtc_categories" => [
            ["category_name" => "CTQ"],
            ["category_name" => "CTP"],
            ["category_name" => "Time Check"],
            ["category_name" => "F/Proof"]
        ]
    ], JSON_INVALID_UTF8_SUBSTITUTE);
}
?>

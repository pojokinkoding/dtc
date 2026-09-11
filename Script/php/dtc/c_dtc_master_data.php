<?php
// c_dtc_master_data.php
require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json');

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

    // 1. Fetch unique lines
    $lines = [];
    try {
        $stmt_lines = $conn->query("SELECT DISTINCT line_name FROM dtc_master_lines WHERE line_name IS NOT NULL AND TRIM(line_name) != '' ORDER BY sort_order ASC, line_name ASC");
        if ($stmt_lines) {
            $lines = $stmt_lines->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $t) {
        $lines = [];
    }

    // Fallback if empty or table missing: fetch distinct line_name from dtc_master_dtc_specs
    if (empty($lines)) {
        try {
            $stmt_lines_fb = $conn->query("SELECT DISTINCT line_name FROM dtc_master_dtc_specs WHERE line_name IS NOT NULL AND TRIM(line_name) != '' ORDER BY line_name ASC");
            if ($stmt_lines_fb) {
                $lines = $stmt_lines_fb->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $t) {
            $lines = [];
        }
    }

    // Default hardcoded fallback if still empty
    if (empty($lines)) {
        $lines = [
            ['line_name' => 'REF 01'],
            ['line_name' => 'REF 02']
        ];
    }

    // 2. Fetch unique sections
    $sections = [];
    try {
        $stmt_sections = $conn->query("SELECT DISTINCT section_name FROM dtc_master_sections WHERE section_name IS NOT NULL AND TRIM(section_name) != '' ORDER BY sort_order ASC, section_name ASC");
        if ($stmt_sections) {
            $sections = $stmt_sections->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $t) {
        $sections = [];
    }

    // Fallback if empty or table missing: fetch distinct section_name from dtc_master_dtc_specs
    if (empty($sections)) {
        try {
            $stmt_sections_fb = $conn->query("SELECT DISTINCT section_name FROM dtc_master_dtc_specs WHERE section_name IS NOT NULL AND TRIM(section_name) != '' ORDER BY section_name ASC");
            if ($stmt_sections_fb) {
                $sections = $stmt_sections_fb->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $t) {
            $sections = [];
        }
    }

    // Default hardcoded fallback if still empty
    if (empty($sections)) {
        $defaultSections = ['Accessories', 'Charging', 'Clamping', 'Cutting Vinyl', 'Cycle', 'H Press Out Door', 'PU Case', 'PU Door', 'Pre Case', 'V Forming Male A', 'V Forming Male B', 'V Forming Male C'];
        $sections = array_map(function($s) { return ['section_name' => $s]; }, $defaultSections);
    }

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
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "status" => "fallback",
        "error" => $e->getMessage(),
        "lines" => [
            ['line_name' => 'REF 01'],
            ['line_name' => 'REF 02']
        ],
        "sections" => [
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
    ]);
}
?>

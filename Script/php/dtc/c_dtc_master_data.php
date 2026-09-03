<?php
// c_dtc_master_data.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    ensureMasterLinesAndSectionsTables($conn);
    
    $base_combinations = "
        SELECT line_name, NULL AS section_name FROM dtc_master_lines WHERE line_name IS NOT NULL AND TRIM(line_name) != ''
        UNION
        SELECT NULL AS line_name, section_name FROM dtc_master_sections WHERE section_name IS NOT NULL AND TRIM(section_name) != ''
        UNION
        SELECT line_name, section_name FROM dtc_master_dtc_specs WHERE line_name IS NOT NULL AND section_name IS NOT NULL
    ";

    // Fetch unique lines
    $stmt_lines = $conn->query("SELECT DISTINCT line_name FROM dtc_master_lines WHERE line_name IS NOT NULL AND TRIM(line_name) != '' " . getIPAccessFilterSQL('line_name', "'ALL'") . getUserAccessFilterSQL('line_name', "'ALL'") . " ORDER BY sort_order ASC, line_name ASC");
    $lines = $stmt_lines->fetchAll(PDO::FETCH_ASSOC);
    if (empty($lines)) {
        $stmt_lines_fb = $conn->query("SELECT DISTINCT line_name FROM ($base_combinations) AS all_data WHERE line_name IS NOT NULL AND TRIM(line_name) != '' " . getIPAccessFilterSQL('line_name', 'section_name') . getUserAccessFilterSQL('line_name', 'section_name') . " ORDER BY line_name ASC");
        $lines = $stmt_lines_fb->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Fetch unique sections
    $stmt_sections = $conn->query("SELECT DISTINCT section_name FROM dtc_master_sections WHERE section_name IS NOT NULL AND TRIM(section_name) != '' " . getIPAccessFilterSQL("'ALL'", 'section_name') . getUserAccessFilterSQL("'ALL'", 'section_name') . " ORDER BY sort_order ASC, section_name ASC");
    $sections = $stmt_sections->fetchAll(PDO::FETCH_ASSOC);
    if (empty($sections)) {
        $stmt_sections_fb = $conn->query("SELECT DISTINCT section_name FROM ($base_combinations) AS all_data WHERE section_name IS NOT NULL AND TRIM(section_name) != '' " . getIPAccessFilterSQL('line_name', 'section_name') . getUserAccessFilterSQL('line_name', 'section_name') . " ORDER BY section_name ASC");
        $sections = $stmt_sections_fb->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $stmt = $conn->prepare("SELECT spec_id, model_name, item_check_name, sub_item_check_name, data_type, section_name, line_name, process_name, measuring_item, lsl, usl, target_value, target_zst, target_zlt FROM dtc_master_dtc_specs WHERE 1=1 " . getIPAccessFilterSQL('line_name', 'section_name') . getUserAccessFilterSQL('line_name', 'section_name') . " ORDER BY spec_id ASC");
    $stmt->execute();
    $specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Categories requested by user
    $dtc_categories = [
        ["category_name" => "CTQ"],
        ["category_name" => "CTP"],
        ["category_name" => "Time Check"],
        ["category_name" => "F/Proof"]
    ];
    
    echo json_encode([
        "lines" => $lines,
        "sections" => $sections,
        "specs" => $specs,
        "dtc_categories" => $dtc_categories
    ]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>

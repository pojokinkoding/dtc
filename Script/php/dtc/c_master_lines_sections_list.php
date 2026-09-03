<?php
// c_master_lines_sections_list.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    ensureMasterLinesAndSectionsTables($conn);

    $stmtLines = $conn->query("SELECT line_id, line_name, description, sort_order, created_at, updated_at 
                              FROM dtc_master_lines 
                              ORDER BY sort_order ASC, line_name ASC");
    $lines = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

    $stmtSections = $conn->query("SELECT section_id, section_name, line_name, description, sort_order, created_at, updated_at 
                                 FROM dtc_master_sections 
                                 ORDER BY sort_order ASC, section_name ASC");
    $sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'lines' => $lines,
        'sections' => $sections
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'lines' => [],
        'sections' => []
    ]);
}
?>

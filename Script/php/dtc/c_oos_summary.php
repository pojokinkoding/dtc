<?php
// Script/php/dtc/c_oos_summary.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

try {
    $conn = getDBConnection();
    
    // Find all sessions for the given month where the data is out of spec
    // We check min_value and max_value against LSL and USL
    $sql = "
        SELECT 
            s.session_id,
            DATE_FORMAT(s.inspection_date, '%Y-%m-%d') as inspection_date, 
            p.parameter_id,
            spec.model_name, spec.line_name, spec.section_name, spec.process_name, spec.item_check_name, spec.data_type,
            spec.lsl, spec.usl, spec.target_value,
            s.min_value, s.max_value, s.remarks,
            CASE 
                WHEN s.min_value < spec.lsl THEN 'Below LSL'
                WHEN s.max_value > spec.usl THEN 'Above USL'
                ELSE 'Unknown'
            END as oos_type
        FROM dtc_inspection_sessions s
        JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
        JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
        WHERE s.is_active = 1
          AND DATE_FORMAT(s.inspection_date, '%Y-%m') = :month
          AND spec.measuring_item != 'Qualitative'
          AND (s.min_value < spec.lsl OR s.max_value > spec.usl)
          " . getIPAccessFilterSQL('spec.line_name', 'spec.section_name') . "
          " . getUserAccessFilterSQL('spec.line_name', 'spec.section_name') . "
        ORDER BY s.inspection_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':month' => $month]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'month' => $month,
        'data' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== VERIFY MASTER SPECS FOR H PRESS OUT DOOR ===\n";
$stmt = $conn->query("
    SELECT spec_id, model_name, item_check_name, data_type, line_name, section_name, process_name 
    FROM dtc_master_dtc_specs 
    WHERE section_name = 'H Press Out Door' AND line_name = 'REF 01'
");
$specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($specs, JSON_PRETTY_PRINT) . "\n\n";

echo "=== VERIFY MASTER PARAMETERS & MEASUREMENT COUNTS (2026-01) ===\n";
$stmt2 = $conn->query("
    SELECT p.parameter_id, p.spec_id, p.target_month, spec.model_name, p.item_check_name, p.data_type, p.line_name, p.section_name,
           (SELECT COUNT(*) FROM dtc_inspection_sessions s WHERE s.parameter_id = p.parameter_id) as session_count,
           (SELECT COUNT(*) FROM dtc_measurements m JOIN dtc_inspection_sessions s ON m.session_id = s.session_id WHERE s.parameter_id = p.parameter_id) as meas_count
    FROM dtc_master_parameters p
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    WHERE p.section_name = 'H Press Out Door' AND p.target_month = '2026-01'
    ORDER BY p.parameter_id ASC
");
$params = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($params, JSON_PRETTY_PRINT) . "\n\n";

echo "=== VERIFY RUNNING MODELS (2026-01) ===\n";
$stmtRM = $conn->query("
    SELECT * FROM dtc_running_models 
    WHERE target_month = '2026-01' AND line_name = 'REF 01' AND section_name = 'H Press Out Door'
");
echo json_encode($stmtRM->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";

echo "=== SAMPLE MEASUREMENTS FOR MODEL 'Y-331' ===\n";
$stmtM1 = $conn->query("
    SELECT s.inspection_date, m.sample_label, m.sample_value, c.checkpoint_name
    FROM dtc_measurements m
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
    JOIN dtc_checkpoints c ON m.checkpoint_id = c.checkpoint_id
    JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    WHERE p.section_name = 'H Press Out Door' AND spec.model_name = 'Y-331'
    LIMIT 10
");
echo json_encode($stmtM1->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

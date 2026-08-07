<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmt = $conn->query("
    SELECT p.parameter_id, p.target_month, p.model_name, p.line_name, p.section_name, p.data_type,
           COUNT(DISTINCT cp.checkpoint_id) as checkpoint_cnt,
           COUNT(DISTINCT s.session_id) as session_cnt,
           SUM(CASE WHEN s.is_closed = 1 THEN 1 ELSE 0 END) as closed_session_cnt,
           COUNT(m.measurement_id) as measurement_cnt
    FROM dtc_master_parameters p
    LEFT JOIN dtc_checkpoints cp ON p.parameter_id = cp.parameter_id
    LEFT JOIN dtc_inspection_sessions s ON p.parameter_id = s.parameter_id
    LEFT JOIN dtc_measurements m ON s.session_id = m.session_id
    WHERE p.target_month = '2026-01'
      AND p.section_name = 'Cutting Vinyl'
      AND p.line_name = 'REF 02'
    GROUP BY p.parameter_id
    ORDER BY p.model_name ASC
");
echo "=== VERIFY AUTOVINYL CUTTING IMPORT IN DB ===\n";
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";

// Sample measurement records
$stmt_samp = $conn->query("
    SELECT s.inspection_date, m.sample_label, m.sample_value, cp.checkpoint_name, p.model_name
    FROM dtc_measurements m
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
    JOIN dtc_checkpoints cp ON m.checkpoint_id = cp.checkpoint_id
    JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
    WHERE p.target_month = '2026-01'
      AND p.section_name = 'Cutting Vinyl'
      AND p.line_name = 'REF 02'
    ORDER BY p.model_name ASC, s.inspection_date ASC, m.sample_sequence ASC
    LIMIT 10
");
echo "=== SAMPLE MEASUREMENTS RECORDED ===\n";
echo json_encode($stmt_samp->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

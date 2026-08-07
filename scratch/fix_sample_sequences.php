<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

// Inspect sample_sequences for a checkpoint in H Press Out Door REF 02
$stmt = $conn->query("
    SELECT m.checkpoint_id, cp.checkpoint_name, m.sample_sequence, m.sample_label, m.sample_value, s.inspection_date
    FROM dtc_measurements m
    JOIN dtc_checkpoints cp ON m.checkpoint_id = cp.checkpoint_id
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
    JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
    WHERE p.section_name = 'H Press Out Door'
      AND p.line_name = 'REF 02'
      AND p.target_month = '2026-01'
      AND s.inspection_date = '2026-01-02'
      AND cp.checkpoint_name = 'Bending'
    ORDER BY m.measurement_id ASC
");
echo "=== CURRENT SAMPLE SEQUENCES FOR 'Bending' CHECKPOINT ON 2026-01-02 ===\n";
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

<?php
// verify_measurements.php
require 'config/config.php';
$conn = getDBConnection();

$stmt = $conn->query("
    SELECT p.parameter_id, p.item_check_name, c.checkpoint_name, s.inspection_date, m.sample_label, m.sample_value
    FROM dtc_master_parameters p
    JOIN dtc_checkpoints c ON p.parameter_id = c.parameter_id
    JOIN dtc_inspection_sessions s ON p.parameter_id = s.parameter_id
    JOIN dtc_measurements m ON s.session_id = m.session_id AND c.checkpoint_id = m.checkpoint_id
    WHERE s.inspection_date = '2026-01-01' AND p.item_check_name = 'Time Check Proses MC 1'
    ORDER BY c.checkpoint_id, m.sample_label
    LIMIT 20
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
?>

<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== DTC MASTER SPECS (Sample) ===\n";
$stmt = $conn->query("SELECT spec_id, model_name, item_check_name, data_type, section_name, line_name, process_name FROM dtc_master_dtc_specs LIMIT 20");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";

echo "=== CHECKPOINTS (Sample with Spec info) ===\n";
$stmt2 = $conn->query("
    SELECT c.checkpoint_id, c.parameter_id, c.checkpoint_name, c.checkpoint_type, p.item_check_name, p.data_type, p.line_name, p.section_name, p.process_name, p.target_month
    FROM dtc_checkpoints c
    JOIN dtc_master_parameters p ON c.parameter_id = p.parameter_id
    LIMIT 20
");
echo json_encode($stmt2->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";
?>

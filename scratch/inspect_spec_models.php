<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmt = $conn->query("
    SELECT p.parameter_id, p.target_month, p.spec_id, s.model_name as spec_model_name, p.model_name as param_model_name
    FROM dtc_master_parameters p
    LEFT JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id
    WHERE p.section_name = 'H Press Out Door'
    ORDER BY p.parameter_id ASC
");
echo "=== PARAMETER vs SPEC MODEL NAMES ===\n";
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

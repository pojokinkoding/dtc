<?php
// verify_datatype_branching.php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$sql = "
    SELECT p.parameter_id, spec.data_type, spec.line_name, spec.section_name, spec.model_name, spec.item_check_name,
           (SELECT COUNT(*) FROM dtc_checkpoints cp WHERE cp.parameter_id = p.parameter_id) as cp_count
    FROM dtc_master_parameters p
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    WHERE p.target_month = '2026-06'
";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$byDataType = [];
foreach ($rows as $r) {
    $dt = strtoupper(trim($r['data_type']));
    if (!isset($byDataType[$dt])) {
        $byDataType[$dt] = ['param_count' => 0, 'cp_count' => 0, 'uses_checkpoints' => false];
    }
    $byDataType[$dt]['param_count']++;
    $byDataType[$dt]['cp_count'] += $r['cp_count'];
    if ($r['cp_count'] > 0) {
        $byDataType[$dt]['uses_checkpoints'] = true;
    }
}

echo "Data Type Checkpoint usage in DB for 2026-06:\n";
echo json_encode($byDataType, JSON_PRETTY_PRINT) . "\n";
?>

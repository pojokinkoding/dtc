<?php
// check_all_data_types.php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$sqlSpecs = "SELECT DISTINCT data_type FROM dtc_master_dtc_specs";
$specsTypes = $conn->query($sqlSpecs)->fetchAll(PDO::FETCH_COLUMN);

$sqlParams = "SELECT DISTINCT data_type FROM dtc_master_parameters";
$paramsTypes = $conn->query($sqlParams)->fetchAll(PDO::FETCH_COLUMN);

echo "Specs data_type values in DB: " . json_encode($specsTypes) . "\n";
echo "Params data_type values in DB: " . json_encode($paramsTypes) . "\n";

// Now check Month 2026-06 parameter query output
$month = '2026-06';
$sql = "
    SELECT p.parameter_id, spec.data_type as spec_data_type, p.data_type as param_data_type, 
           spec.line_name, spec.section_name, spec.model_name
    FROM dtc_master_parameters p
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    WHERE p.target_month = '$month'
";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
foreach ($rows as $r) {
    $dt = $r['spec_data_type'];
    if (!isset($counts[$dt])) $counts[$dt] = 0;
    $counts[$dt]++;
}
echo "Month $month parameter count per data_type (spec.data_type):\n" . json_encode($counts, JSON_PRETTY_PRINT) . "\n";
?>

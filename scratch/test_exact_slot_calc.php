<?php
// test_exact_slot_calc.php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$month = '2026-06';

// Run query similar to c_missing_data.php
$sqlParams = "
    SELECT p.parameter_id, p.target_month, spec.model_name, spec.item_check_name, spec.sub_item_check_name, spec.data_type, spec.section_name, spec.line_name, spec.process_name
    FROM dtc_master_parameters p
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    WHERE p.target_month = :month AND spec.section_name LIKE 'V Forming%'
";
$stmt = $conn->prepare($sqlParams);
$stmt->execute([':month' => $month]);
$parameters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Checkpoints
$sqlCp = "SELECT checkpoint_id, parameter_id, checkpoint_name FROM dtc_checkpoints ORDER BY sort_order ASC";
$cps = $conn->query($sqlCp)->fetchAll(PDO::FETCH_ASSOC);
$paramCps = [];
foreach ($cps as $cp) {
    $paramCps[$cp['parameter_id']][] = $cp;
}

echo "V Forming parameters count: " . count($parameters) . "\n";
$totalCheckpoints = 0;
foreach ($parameters as $p) {
    $pid = $p['parameter_id'];
    $cpCount = isset($paramCps[$pid]) ? count($paramCps[$pid]) : 0;
    $totalCheckpoints += $cpCount;
    echo "Param {$p['parameter_id']} ({$p['section_name']} - {$p['model_name']}): $cpCount checkpoints\n";
}
echo "Total Checkpoints for V Forming in $month: $totalCheckpoints\n";
?>

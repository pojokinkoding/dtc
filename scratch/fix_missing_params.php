<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== FIXING NULL MODEL NAMES IN DTC_MASTER_PARAMETERS ===\n";

$affected = $conn->exec("
    UPDATE dtc_master_parameters p
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    SET p.model_name = spec.model_name
    WHERE p.model_name IS NULL OR p.model_name = ''
");
echo "Updated $affected parameter rows with model_name from master spec.\n\n";

// Verify null model_names count
$nullCnt = $conn->query("SELECT COUNT(*) FROM dtc_master_parameters WHERE model_name IS NULL OR model_name = ''")->fetchColumn();
echo "Remaining parameters with null/empty model_name: $nullCnt\n\n";

// Check parameters per month for H Press Out Door
$stmt = $conn->query("
    SELECT target_month, parameter_id, model_name, line_name, section_name
    FROM dtc_master_parameters
    WHERE section_name = 'H Press Out Door'
    ORDER BY target_month ASC, parameter_id ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byMonth = [];
foreach ($rows as $r) {
    $m = $r['target_month'];
    if (!isset($byMonth[$m])) $byMonth[$m] = [];
    $byMonth[$m][] = "Param {$r['parameter_id']}: {$r['model_name']}";
}

echo "=== H PRESS OUTDOOR PARAMETERS PER MONTH ===\n";
foreach ($byMonth as $m => $pList) {
    echo "Month '$m' (" . count($pList) . " models):\n";
    echo "  " . implode(', ', array_slice($pList, 0, 5)) . (count($pList) > 5 ? " ... etc." : "") . "\n";
}
?>

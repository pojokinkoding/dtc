<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== VERIFY FEBRUARY 2026 MASTER PARAMETERS ===\n";
$stmt = $conn->query("
    SELECT parameter_id, spec_id, target_month, item_check_name, data_type, line_name, section_name, process_name 
    FROM dtc_master_parameters 
    WHERE data_type = 'F/Proof' AND target_month = '2026-02' AND line_name = 'REF 01'
");
$params = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($params, JSON_PRETTY_PRINT) . "\n\n";

echo "=== VERIFY CHECKPOINTS & MEASUREMENT COUNTS FOR FEB 2026 ===\n";
foreach ($params as $p) {
    $pid = $p['parameter_id'];
    $sec = $p['section_name'];
    $stmt3 = $conn->query("
        SELECT checkpoint_id, checkpoint_name, checkpoint_type, spec_value, sort_order 
        FROM dtc_checkpoints 
        WHERE parameter_id = $pid 
        ORDER BY sort_order ASC
    ");
    $cps = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    echo "Section '$sec' (PID: $pid) -> " . count($cps) . " checkpoints:\n";
    foreach ($cps as $c) {
        $cpid = $c['checkpoint_id'];
        $mCount = $conn->query("SELECT COUNT(*) FROM dtc_measurements WHERE checkpoint_id = $cpid")->fetchColumn();
        echo "  - CP #{$c['checkpoint_id']} [{$c['checkpoint_name']}] | Type: {$c['checkpoint_type']} | Spec: {$c['spec_value']} | Measurements: $mCount\n";
    }
    echo "\n";
}

echo "=== VERIFY RUNNING MODELS FOR FEB 2026 ===\n";
$stmtRM = $conn->query("
    SELECT * FROM dtc_running_models 
    WHERE target_month = '2026-02' AND line_name = 'REF 01' AND section_name IN ('Clamping', 'Charging')
");
echo json_encode($stmtRM->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

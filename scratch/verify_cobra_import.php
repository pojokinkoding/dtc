<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== VERIFY MASTER SPECS FOR COBRA / F/PROOF ===\n";
$stmt = $conn->query("
    SELECT spec_id, model_name, item_check_name, data_type, line_name, section_name, process_name 
    FROM dtc_master_dtc_specs 
    WHERE data_type = 'F/Proof' AND line_name = 'REF 01' AND section_name IN ('Clamping', 'Charging')
");
$specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($specs, JSON_PRETTY_PRINT) . "\n\n";

echo "=== VERIFY MASTER PARAMETERS ===\n";
$stmt2 = $conn->query("
    SELECT parameter_id, spec_id, target_month, item_check_name, data_type, line_name, section_name, process_name 
    FROM dtc_master_parameters 
    WHERE data_type = 'F/Proof' AND target_month = '2026-01' AND line_name = 'REF 01' AND section_name IN ('Clamping', 'Charging')
");
$params = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($params, JSON_PRETTY_PRINT) . "\n\n";

echo "=== VERIFY CHECKPOINTS PER PARAMETER ===\n";
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
        echo "  - CP #{$c['checkpoint_id']} [{$c['checkpoint_name']}] | Spec: {$c['spec_value']} | Measurements: $mCount\n";
    }
    echo "\n";
}

echo "=== VERIFY RUNNING MODELS ===\n";
$stmtRM = $conn->query("
    SELECT * FROM dtc_running_models 
    WHERE target_month = '2026-01' AND line_name = 'REF 01' AND section_name IN ('Clamping', 'Charging')
");
echo json_encode($stmtRM->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";

echo "=== SAMPLE MEASUREMENTS FOR CLAMPING ===\n";
$stmtM1 = $conn->query("
    SELECT s.inspection_date, m.sample_label, m.sample_value, c.checkpoint_name
    FROM dtc_measurements m
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
    JOIN dtc_checkpoints c ON m.checkpoint_id = c.checkpoint_id
    JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
    WHERE p.section_name = 'Clamping' AND s.inspection_date = '2026-01-02'
    LIMIT 10
");
echo json_encode($stmtM1->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";

echo "=== SAMPLE MEASUREMENTS FOR CHARGING ===\n";
$stmtM2 = $conn->query("
    SELECT s.inspection_date, m.sample_label, m.sample_value, c.checkpoint_name
    FROM dtc_measurements m
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
    JOIN dtc_checkpoints c ON m.checkpoint_id = c.checkpoint_id
    JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
    WHERE p.section_name = 'Charging' AND s.inspection_date = '2026-01-02'
    LIMIT 10
");
echo json_encode($stmtM2->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

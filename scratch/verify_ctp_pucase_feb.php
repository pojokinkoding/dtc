<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "================ VERIFYING CTP PU CASE FEB 2026 IMPORT ================\n";

// 1. Check Master Parameters for CTP Feb 2026
echo "\n--- Master Parameters (dtc_master_parameters) for Feb 2026 & CTP ---\n";
$stmt2 = $conn->query("
    SELECT p.parameter_id, p.spec_id, s.model_name, p.item_check_name, p.process_name, p.line_name, p.section_name 
    FROM dtc_master_parameters p
    JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id
    WHERE p.target_month = '2026-02' AND p.data_type = 'CTP' AND p.line_name = 'REF 01' AND p.section_name = 'PU Case'
");
$params = $stmt2->fetchAll(PDO::FETCH_ASSOC);
foreach ($params as $pr) {
    echo "ParamID: {$pr['parameter_id']} | SpecID: {$pr['spec_id']} | Model: {$pr['model_name']} | Item: {$pr['item_check_name']} | Proc: {$pr['process_name']}\n";
}

// 2. Session & Measurement Counts per Parameter for CTP Feb 2026
echo "\n--- Sessions & Measurements Breakdown (CTP Feb 2026) ---\n";
$total_s = 0;
$total_m = 0;
foreach ($params as $pr) {
    $pid = $pr['parameter_id'];
    $s_cnt = $conn->query("SELECT COUNT(*) FROM dtc_inspection_sessions WHERE parameter_id = $pid")->fetchColumn();
    $m_cnt = $conn->query("SELECT COUNT(*) FROM dtc_measurements m JOIN dtc_inspection_sessions s ON m.session_id = s.session_id WHERE s.parameter_id = $pid")->fetchColumn();
    $total_s += $s_cnt;
    $total_m += $m_cnt;
    echo "ParamID $pid ({$pr['model_name']} - {$pr['process_name']}): $s_cnt sessions, $m_cnt measurements.\n";
}

echo "\nTOTAL CTP FEB SESSIONS: $total_s | TOTAL CTP FEB MEASUREMENTS: $total_m\n";

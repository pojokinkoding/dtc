<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "================ VERIFYING CTP PU CASE JAN 2026 IMPORT ================\n";

// 1. Check Master Specs for CTP
echo "\n--- Master Specs (dtc_master_dtc_specs) for Line REF 01 & Section PU Case & Type CTP ---\n";
$stmt = $conn->query("SELECT spec_id, model_name, item_check_name, process_name, line_name, section_name, lsl, usl, uom FROM dtc_master_dtc_specs WHERE data_type = 'CTP' AND line_name = 'REF 01' AND section_name = 'PU Case'");
$specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($specs as $sp) {
    echo "ID: {$sp['spec_id']} | Model: {$sp['model_name']} | Item: {$sp['item_check_name']} | Proc: {$sp['process_name']} | LSL: {$sp['lsl']} | USL: {$sp['usl']} {$sp['uom']}\n";
}

// 2. Check Master Parameters for CTP
echo "\n--- Master Parameters (dtc_master_parameters) for Jan 2026 & CTP ---\n";
$stmt2 = $conn->query("
    SELECT p.parameter_id, p.spec_id, s.model_name, p.item_check_name, p.process_name, p.line_name, p.section_name 
    FROM dtc_master_parameters p
    JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id
    WHERE p.target_month = '2026-01' AND p.data_type = 'CTP' AND p.line_name = 'REF 01' AND p.section_name = 'PU Case'
");
$params = $stmt2->fetchAll(PDO::FETCH_ASSOC);
foreach ($params as $pr) {
    echo "ParamID: {$pr['parameter_id']} | SpecID: {$pr['spec_id']} | Model: {$pr['model_name']} | Item: {$pr['item_check_name']} | Proc: {$pr['process_name']}\n";
}

// 3. Session & Measurement Counts per Parameter for CTP
echo "\n--- Sessions & Measurements Breakdown (CTP Jan 2026) ---\n";
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

echo "\nTOTAL CTP SESSIONS: $total_s | TOTAL CTP MEASUREMENTS: $total_m\n";

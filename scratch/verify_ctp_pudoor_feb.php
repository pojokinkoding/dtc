<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "================ VERIFYING CTP PU DOOR FEB 2026 IMPORT ================\n";

$stmt2 = $conn->query("
    SELECT p.parameter_id, p.spec_id, s.model_name, p.item_check_name, p.process_name, p.line_name, p.section_name 
    FROM dtc_master_parameters p
    JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id
    WHERE p.target_month = '2026-02' AND p.data_type = 'CTP' AND p.line_name = 'REF 01' AND p.section_name = 'PU Door'
");
$params = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$total_s = 0; $total_m = 0;
foreach ($params as $pr) {
    $pid = $pr['parameter_id'];
    $s_cnt = $conn->query("SELECT COUNT(*) FROM dtc_inspection_sessions WHERE parameter_id = $pid")->fetchColumn();
    $m_cnt = $conn->query("SELECT COUNT(*) FROM dtc_measurements m JOIN dtc_inspection_sessions s ON m.session_id = s.session_id WHERE s.parameter_id = $pid")->fetchColumn();
    $total_s += $s_cnt;
    $total_m += $m_cnt;
}

echo "TOTAL CTP PU DOOR FEB SESSIONS: $total_s | TOTAL CTP PU DOOR FEB MEASUREMENTS: $total_m\n";

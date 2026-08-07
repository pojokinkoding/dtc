<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "================ COMPREHENSIVE CTP PU DOOR RECAP (JAN - JUN 2026) ================\n";

$months = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'];

foreach ($months as $m) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.parameter_id) as param_cnt,
               COUNT(DISTINCT s.session_id) as session_cnt,
               COUNT(m.measurement_id) as measurement_cnt
        FROM dtc_master_parameters p
        LEFT JOIN dtc_inspection_sessions s ON p.parameter_id = s.parameter_id
        LEFT JOIN dtc_measurements m ON s.session_id = m.session_id
        WHERE p.target_month = :m AND p.data_type = 'CTP' AND p.line_name = 'REF 01' AND p.section_name = 'PU Door'
    ");
    $stmt->execute([':m' => $m]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Month $m | Parameters: {$res['param_cnt']} | Sessions: {$res['session_cnt']} | Measurements: {$res['measurement_cnt']}\n";
}

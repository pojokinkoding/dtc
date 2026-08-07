<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmt = $conn->prepare("
    SELECT p.parameter_id, p.target_month, p.model_name, p.line_name, p.section_name,
           COUNT(DISTINCT s.session_id) as session_cnt,
           COUNT(m.measurement_id) as meas_cnt
    FROM dtc_master_parameters p
    LEFT JOIN dtc_inspection_sessions s ON p.parameter_id = s.parameter_id
    LEFT JOIN dtc_measurements m ON s.session_id = m.session_id
    WHERE p.target_month = '2026-02'
      AND p.section_name = 'H Press Out Door'
    GROUP BY p.parameter_id
");
$stmt->execute();
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== FEB 2026 H PRESS OUTDOOR PARAMETERS IN DB ===\n";
echo json_encode($res, JSON_PRETTY_PRINT) . "\n\n";

// Check running models for Feb 2026
$stmt_rm = $conn->query("SELECT * FROM dtc_running_models WHERE target_month = '2026-02' AND section_name = 'H Press Out Door'");
echo "=== FEB 2026 RUNNING MODELS IN DB ===\n";
echo json_encode($stmt_rm->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

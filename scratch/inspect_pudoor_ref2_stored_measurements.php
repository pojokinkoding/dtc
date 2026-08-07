<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== STORED SAMPLE LABELS AND VALUES IN DTC_MEASUREMENTS FOR CTP PU DOOR REF 02 ===\n\n";

$params = [3334, 3335, 3336, 3337, 3338, 3339, 3340, 3341, 3342];

foreach ($params as $pid) {
    $stmt = $conn->prepare("
        SELECT p.parameter_id, sp.model_name, s.inspection_date, m.sample_sequence, m.sample_label, m.sample_value
        FROM dtc_measurements m
        JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
        JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
        JOIN dtc_master_dtc_specs sp ON p.spec_id = sp.spec_id
        WHERE p.parameter_id = :pid AND s.inspection_date = '2026-01-02'
        ORDER BY s.inspection_date ASC, m.sample_sequence ASC
    ");
    $stmt->execute([':pid' => $pid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rows)) {
        echo "ParamID $pid ({$rows[0]['model_name']}) - Date: {$rows[0]['inspection_date']}:\n";
        foreach ($rows as $r) {
            echo "  Seq {$r['sample_sequence']} | Label: '{$r['sample_label']}' | Value: {$r['sample_value']}\n";
        }
        echo "\n";
    }
}

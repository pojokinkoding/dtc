<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$months = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'];

echo "=== SUMMARY OF ALL IMPORTED COBRA F/PROOF MONTHS (2026-01 to 2026-06) ===\n\n";

foreach ($months as $m) {
    $stmt = $conn->prepare("
        SELECT p.parameter_id, p.section_name, p.process_name,
               (SELECT COUNT(*) FROM dtc_checkpoints cp WHERE cp.parameter_id = p.parameter_id) as cp_count,
               (SELECT COUNT(*) FROM dtc_measurements m JOIN dtc_inspection_sessions s ON m.session_id = s.session_id WHERE s.parameter_id = p.parameter_id) as meas_count
        FROM dtc_master_parameters p
        WHERE p.target_month = :m AND p.line_name = 'REF 01' AND p.section_name IN ('Clamping', 'Charging') AND p.data_type = 'F/Proof'
        ORDER BY p.section_name
    ");
    $stmt->execute([':m' => $m]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Month [$m]: " . count($rows) . " parameters\n";
    foreach ($rows as $r) {
        echo "  - Section: '{$r['section_name']}' | Process: '{$r['process_name']}' | Checkpoints: {$r['cp_count']} | Measurements: {$r['meas_count']}\n";
    }
    echo "\n";
}
?>

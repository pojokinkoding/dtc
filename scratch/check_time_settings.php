<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== APP SETTINGS FOR TIME LABELS ===\n";
$stmt = $conn->query("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE '%time%'");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $val = is_resource($r['setting_value']) ? stream_get_contents($r['setting_value']) : $r['setting_value'];
    echo "Key: {$r['setting_key']} | Value: $val\n";
}

echo "\n=== DISTINCT SAMPLE LABELS IN MEASUREMENTS FOR H PRESS OUT DOOR ===\n";
$stmt2 = $conn->query("
    SELECT DISTINCT m.sample_label 
    FROM dtc_measurements m
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
    JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
    WHERE p.section_name = 'H Press Out Door'
    ORDER BY m.measurement_id ASC
");
echo json_encode($stmt2->fetchAll(PDO::FETCH_COLUMN), JSON_PRETTY_PRINT) . "\n";
?>

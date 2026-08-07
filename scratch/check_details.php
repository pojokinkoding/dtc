<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== SPECS (F/Proof) ===\n";
$stmt = $conn->query("
    SELECT spec_id, section_name, line_name, process_name, item_check_name, data_type 
    FROM dtc_master_dtc_specs 
    WHERE data_type = 'F/Proof'
");
$specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total Specs with F/Proof: " . count($specs) . "\n";
foreach ($specs as $s) {
    $keep = ($s['section_name'] === 'Pre Case' && $s['item_check_name'] === 'Screw Hinge Lower') ? "KEEP (F/Proof)" : "CHANGE -> Time Check";
    echo "ID: {$s['spec_id']} | Sec: {$s['section_name']} | Line: {$s['line_name']} | Item: {$s['item_check_name']} | Action: {$keep}\n";
}

echo "\n=== PARAMETERS (F/Proof) ===\n";
$stmt2 = $conn->query("
    SELECT parameter_id, spec_id, target_month, section_name, line_name, process_name, item_check_name, data_type, model_name 
    FROM dtc_master_parameters 
    WHERE data_type = 'F/Proof'
");
$params = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "Total Parameters with F/Proof: " . count($params) . "\n";
$keepCount = 0;
$changeCount = 0;
foreach ($params as $p) {
    if ($p['section_name'] === 'Pre Case' && $p['item_check_name'] === 'Screw Hinge Lower') {
        $keepCount++;
    } else {
        $changeCount++;
    }
}
echo "To Keep as F/Proof: $keepCount\n";
echo "To Change to Time Check: $changeCount\n";

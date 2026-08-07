<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "--- DTC SPECS with F/Proof ---\n";
$stmt = $conn->query("SELECT spec_id, section_name, line_name, process_name, item_check_name, sub_item_check_name, data_type FROM dtc_master_dtc_specs WHERE UPPER(data_type) LIKE '%F%' OR UPPER(data_type) LIKE '%PROOF%'");
$specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($specs as $s) {
    echo "ID: {$s['spec_id']} | Sec: {$s['section_name']} | Line: {$s['line_name']} | Proc: {$s['process_name']} | Item: {$s['item_check_name']} | Data: {$s['data_type']}\n";
}

echo "\n--- DTC PARAMETERS with F/Proof (Distinct Items) ---\n";
$stmt2 = $conn->query("SELECT DISTINCT section_name, line_name, process_name, item_check_name, sub_item_check_name, data_type, model_name FROM dtc_master_parameters WHERE UPPER(data_type) LIKE '%F%' OR UPPER(data_type) LIKE '%PROOF%'");
$params = $stmt2->fetchAll(PDO::FETCH_ASSOC);
foreach ($params as $p) {
    echo "Sec: {$p['section_name']} | Line: {$p['line_name']} | Proc: {$p['process_name']} | Item: {$p['item_check_name']} | Data: {$p['data_type']} | Model: {$p['model_name']}\n";
}

echo "\n--- DISTINCT DATA TYPES in dtc_master_dtc_specs ---\n";
$stmt3 = $conn->query("SELECT DISTINCT data_type FROM dtc_master_dtc_specs");
print_r($stmt3->fetchAll(PDO::FETCH_COLUMN));

echo "\n--- DISTINCT DATA TYPES in dtc_master_parameters ---\n";
$stmt4 = $conn->query("SELECT DISTINCT data_type FROM dtc_master_parameters");
print_r($stmt4->fetchAll(PDO::FETCH_COLUMN));

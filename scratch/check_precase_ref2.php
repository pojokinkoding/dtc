<?php
require 'c:/xampp/htdocs/dtq/config/config.php';
$conn = getDBConnection();

$param_id = 3545;

// Check parameter and spec info
$p = $conn->query("SELECT p.*, s.lsl, s.usl, s.target_value, s.uom, s.model_name, s.item_check_name, s.section_name, s.line_name
    FROM dtc_master_parameters p 
    JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id 
    WHERE p.parameter_id = $param_id")->fetch(PDO::FETCH_ASSOC);
echo "Parameter Info:\n";
print_r($p);

// Check sessions
$sessions = $conn->query("SELECT session_id, inspection_date, x_bar, max_value, min_value, std_dev, range_value, is_active, is_closed FROM dtc_inspection_sessions WHERE parameter_id = $param_id ORDER BY inspection_date ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "\nSample Sessions:\n";
print_r($sessions);

$cnt = $conn->query("SELECT COUNT(*) FROM dtc_inspection_sessions WHERE parameter_id = $param_id")->fetchColumn();
echo "\nTotal sessions: $cnt\n";

// Check measurements for first session
if (!empty($sessions)) {
    $sid = $sessions[0]['session_id'];
    $ms = $conn->query("SELECT * FROM dtc_measurements WHERE session_id = $sid LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nMeasurements for session $sid (date: {$sessions[0]['inspection_date']}):\n";
    print_r($ms);
}

// Check dtc_summary or related table
$tables = $conn->query("SHOW TABLES LIKE 'dtc_%'")->fetchAll(PDO::FETCH_COLUMN);
echo "\nDTC Tables:\n";
print_r($tables);

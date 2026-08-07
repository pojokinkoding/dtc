<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmt = $conn->query("
    SELECT * FROM dtc_master_dtc_specs 
    WHERE section_name IN ('Clamping', 'Charging', 'Cycle') 
       OR process_name LIKE '%cobra%' 
       OR item_check_name LIKE '%cobra%'
       OR model_name LIKE '%cobra%'
       OR section_name LIKE '%cobra%'
");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

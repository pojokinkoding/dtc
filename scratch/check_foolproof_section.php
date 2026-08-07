<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$sql = "
    SELECT spec_id, line_name, section_name, process_name, model_name, item_check_name, sub_item_check_name, data_type
    FROM dtc_master_dtc_specs
    WHERE item_check_name LIKE '%Hinge%' 
       OR sub_item_check_name LIKE '%Hinge%'
       OR model_name LIKE '%Hinge%'
       OR data_type LIKE '%Proof%'
       OR item_check_name LIKE '%Fool%'
";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Found specs matching Hinge/Foolproof:\n";
echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
?>

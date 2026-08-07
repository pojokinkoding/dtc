<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$sql = "
    SELECT spec_id, line_name, section_name, process_name, model_name, item_check_name, sub_item_check_name, data_type
    FROM dtc_master_dtc_specs
    WHERE data_type = 'F/Proof'
";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Existing F/Proof specs in DB:\n";
echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
?>

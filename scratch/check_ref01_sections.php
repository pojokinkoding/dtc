<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$sql = "
    SELECT DISTINCT section_name, process_name
    FROM dtc_master_dtc_specs
    WHERE line_name = 'REF 01'
    ORDER BY section_name, process_name
";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "REF 01 Sections & Processes in DB:\n";
echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
?>

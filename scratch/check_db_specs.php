<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

echo "=== DISTINCT LINES ==:\n";
$lines = $conn->query("SELECT DISTINCT line_name FROM dtc_master_dtc_specs")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($lines) . "\n\n";

echo "=== DISTINCT SECTIONS ==:\n";
$secs = $conn->query("SELECT DISTINCT section_name FROM dtc_master_dtc_specs")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($secs) . "\n\n";

echo "=== DISTINCT DATA TYPES ==:\n";
$dtypes = $conn->query("SELECT DISTINCT data_type FROM dtc_master_dtc_specs")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($dtypes) . "\n\n";

echo "=== F/PROOF MASTERS IN Master DTC Specs ==:\n";
$stmt = $conn->query("SELECT * FROM dtc_master_dtc_specs WHERE data_type LIKE '%proof%' OR data_type LIKE '%f/%'");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";

echo "=== F/PROOF MASTERS IN Master Parameters ==:\n";
$stmt2 = $conn->query("SELECT * FROM dtc_master_parameters WHERE data_type LIKE '%proof%' OR data_type LIKE '%f/%'");
echo json_encode($stmt2->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";
?>

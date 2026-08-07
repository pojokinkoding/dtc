<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmt1 = $conn->query("SELECT DISTINCT model_name FROM dtc_master_dtc_specs WHERE section_name LIKE '%press%' OR section_name LIKE '%door%'");
echo "Specs models for Press/Door:\n" . json_encode($stmt1->fetchAll(PDO::FETCH_COLUMN), JSON_PRETTY_PRINT) . "\n\n";

$stmt2 = $conn->query("SELECT DISTINCT model_name FROM dtc_master_dtc_specs");
echo "All specs models:\n" . json_encode($stmt2->fetchAll(PDO::FETCH_COLUMN), JSON_PRETTY_PRINT) . "\n\n";
?>

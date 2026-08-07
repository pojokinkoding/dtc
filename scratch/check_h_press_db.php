<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== EXISTING SPECS FOR H Press Out Door ===\n";
$stmt1 = $conn->query("SELECT * FROM dtc_master_dtc_specs WHERE section_name LIKE '%H Press%' OR section_name LIKE '%Out Door%' OR section_name LIKE '%Outdoor%'");
echo json_encode($stmt1->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";

echo "=== EXISTING PARAMETERS FOR H Press Out Door (2026-01) ===\n";
$stmt2 = $conn->query("SELECT * FROM dtc_master_parameters WHERE (section_name LIKE '%H Press%' OR section_name LIKE '%Out Door%' OR section_name LIKE '%Outdoor%') AND target_month = '2026-01'");
echo json_encode($stmt2->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

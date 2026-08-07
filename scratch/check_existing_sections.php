<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmt = $conn->query("
    SELECT line_name, section_name, process_name, item_check_name, model_name, data_type, COUNT(*) as cnt
    FROM dtc_master_parameters
    WHERE target_month = '2026-01'
    GROUP BY line_name, section_name, process_name, item_check_name, model_name
    ORDER BY line_name, section_name
");
echo "=== EXISTING SECTIONS IN DB (2026-01) ===\n";
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

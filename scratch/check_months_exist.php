<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmt = $conn->query("
    SELECT target_month, COUNT(*) as param_cnt
    FROM dtc_master_parameters
    WHERE section_name = 'H Press Out Door'
    GROUP BY target_month
    ORDER BY target_month ASC
");
echo "=== CURRENT H PRESS OUTDOOR MONTHS IN DB ===\n";
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

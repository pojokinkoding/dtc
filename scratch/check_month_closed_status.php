<?php
// check_month_closed_status.php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$sql = "
    SELECT DATE_FORMAT(inspection_date, '%Y-%m') as m, is_closed, COUNT(*) as cnt
    FROM dtc_inspection_sessions
    GROUP BY m, is_closed
    ORDER BY m ASC, is_closed ASC
";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Session is_closed stats per month:\n";
echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
?>

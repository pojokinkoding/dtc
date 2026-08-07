<?php
require_once '../config/config.php';
$conn = getDBConnection();
$stmt = $conn->prepare('SELECT * FROM dtc_measurements ORDER BY measurement_id DESC');
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fp = fopen('raw_measurements.csv', 'w');
if(count($data) > 0){
    fputcsv($fp, array_keys($data[0]));
}
foreach($data as $row){
    fputcsv($fp, $row);
}
fclose($fp);
echo "Data exported to raw_measurements.csv";
?>

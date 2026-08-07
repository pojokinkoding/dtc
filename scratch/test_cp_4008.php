<?php
require_once 'config/config.php';
$conn = getDBConnection();

$stmt = $conn->query("SELECT * FROM dtc_checkpoints WHERE parameter_id = 4008");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Checkpoints for param 4008: " . count($rows) . "\n";
echo json_encode($rows, JSON_PRETTY_PRINT);

<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmt = $conn->query("DESCRIBE dtc_checkpoints");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

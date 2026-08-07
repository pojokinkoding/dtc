<?php
require 'config/config.php';
$conn = getDBConnection();
echo "Measurements: " . $conn->query('SELECT COUNT(*) FROM dtc_measurements')->fetchColumn() . "\n";
echo "Checkpoints: " . $conn->query('SELECT COUNT(*) FROM dtc_checkpoints')->fetchColumn() . "\n";
echo "Sessions: " . $conn->query('SELECT COUNT(*) FROM dtc_inspection_sessions')->fetchColumn() . "\n";
?>

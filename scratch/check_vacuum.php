<?php
require 'config/config.php';
$conn = getDBConnection();
$stmt = $conn->query("SELECT sample_value FROM dtc_measurements m JOIN dtc_checkpoints c ON m.checkpoint_id = c.checkpoint_id WHERE c.checkpoint_name = 'Vacuum     Pressure' LIMIT 5");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['sample_value'] . "\n";
}
?>

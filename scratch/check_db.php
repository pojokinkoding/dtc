<?php
require 'config/config.php';
$conn = getDBConnection();
$stmt = $conn->query("SELECT parameter_id, checkpoint_name, checkpoint_type, usl, lsl, spec_value FROM dtc_checkpoints ORDER BY checkpoint_id DESC LIMIT 15");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
?>

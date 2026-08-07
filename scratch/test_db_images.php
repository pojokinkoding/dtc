<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmtP = $conn->query("SELECT parameter_id, item_check_name, reference_image FROM dtc_master_parameters WHERE reference_image IS NOT NULL AND reference_image != '' LIMIT 5");
echo "Master Params with images:\n";
print_r($stmtP->fetchAll(PDO::FETCH_ASSOC));

$stmtC = $conn->query("SELECT checkpoint_id, checkpoint_name, reference_image FROM dtc_checkpoints WHERE reference_image IS NOT NULL AND reference_image != '' LIMIT 5");
echo "\nCheckpoints with images:\n";
print_r($stmtC->fetchAll(PDO::FETCH_ASSOC));

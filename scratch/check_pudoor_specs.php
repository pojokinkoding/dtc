<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== EXISTING MASTER SPECS WHERE section_name = 'PU Door' AND data_type = 'CTP' ===\n";
$stmt = $conn->query("SELECT spec_id, model_name, item_check_name, process_name, line_name, section_name, lsl, usl FROM dtc_master_dtc_specs WHERE section_name = 'PU Door' AND data_type = 'CTP'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "Fixing section_name typo from 'Accessoris' to 'Accessories' in Database...\n";

// 1. Update dtc_master_dtc_specs
$upd1 = $conn->exec("UPDATE dtc_master_dtc_specs SET section_name = 'Accessories' WHERE section_name = 'Accessoris'");
echo "Updated $upd1 master specs in dtc_master_dtc_specs.\n";

// 2. Update dtc_master_parameters
$upd2 = $conn->exec("UPDATE dtc_master_parameters SET section_name = 'Accessories' WHERE section_name = 'Accessoris'");
echo "Updated $upd2 master parameters in dtc_master_parameters.\n";

// 3. Update dtc_running_models
$upd3 = $conn->exec("UPDATE dtc_running_models SET section_name = 'Accessories' WHERE section_name = 'Accessoris'");
echo "Updated $upd3 running models in dtc_running_models.\n";

echo "Typo fix complete!\n";

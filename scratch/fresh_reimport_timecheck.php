<?php
// fresh_reimport_timecheck.php
require_once __DIR__ . '/../config/config.php';

echo "=== STARTING FRESH CLEANUP & RE-IMPORT OF ALL TIME CHECK DATA ===\n";

$conn = getDBConnection();

// 1. Delete all Time Check data from Database to ensure a clean state
echo "Cleaning up existing Time Check parameters, checkpoints, sessions, and measurements...\n";

// Find all spec_ids and parameter_ids for Time Check
$param_ids = $conn->query("
    SELECT parameter_id FROM dtc_master_parameters 
    WHERE UPPER(data_type) = 'TIME CHECK' OR UPPER(item_check_name) LIKE 'TIME CHECK%'
")->fetchAll(PDO::FETCH_COLUMN);

if (!empty($param_ids)) {
    $in_params = implode(',', array_map('intval', $param_ids));
    
    // Delete measurements
    $conn->exec("
        DELETE FROM dtc_measurements 
        WHERE session_id IN (SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params))
           OR checkpoint_id IN (SELECT checkpoint_id FROM dtc_checkpoints WHERE parameter_id IN ($in_params))
    ");
    
    // Delete sessions
    $conn->exec("DELETE FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params)");
    
    // Delete checkpoints
    $conn->exec("DELETE FROM dtc_checkpoints WHERE parameter_id IN ($in_params)");
    
    // Delete master parameters
    $conn->exec("DELETE FROM dtc_master_parameters WHERE parameter_id IN ($in_params)");
}

// Delete master specs for Time Check
$conn->exec("DELETE FROM dtc_master_dtc_specs WHERE UPPER(data_type) = 'TIME CHECK' OR UPPER(item_check_name) LIKE 'TIME CHECK%'");

// Delete running models for Time Check
$conn->exec("DELETE FROM dtc_running_models WHERE UPPER(model_name) LIKE 'TIME CHECK%'");

echo "Cleanup completed successfully!\n\n";

// 2. Run the updated complete import script
require_once __DIR__ . '/import_timecheck_complete.php';

echo "\n=== FRESH RE-IMPORT COMPLETE! ===\n";
?>

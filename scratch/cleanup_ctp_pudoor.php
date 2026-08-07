<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "Cleaning up CTP PU Door data from Database...\n";

// 1. Find all parameter_ids for section_name = 'PU Door' and data_type = 'CTP'
$stmt = $conn->query("
    SELECT parameter_id 
    FROM dtc_master_parameters 
    WHERE section_name = 'PU Door' AND data_type = 'CTP'
");
$param_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($param_ids)) {
    $in_params = implode(',', array_map('intval', $param_ids));

    // Delete measurements
    $deleted_m = $conn->exec("
        DELETE m FROM dtc_measurements m
        JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
        WHERE s.parameter_id IN ($in_params)
    ");
    echo "Deleted $deleted_m measurements.\n";

    // Delete inspection sessions
    $deleted_s = $conn->exec("
        DELETE FROM dtc_inspection_sessions
        WHERE parameter_id IN ($in_params)
    ");
    echo "Deleted $deleted_s inspection sessions.\n";

    // Delete parameters
    $deleted_p = $conn->exec("
        DELETE FROM dtc_master_parameters
        WHERE parameter_id IN ($in_params)
    ");
    echo "Deleted $deleted_p master parameters.\n";
} else {
    echo "No CTP PU Door parameters found in dtc_master_parameters.\n";
}

// 2. Delete running models for section_name = 'PU Door'
$deleted_rm = $conn->exec("
    DELETE FROM dtc_running_models
    WHERE section_name = 'PU Door'
");
echo "Deleted $deleted_rm running models.\n";

// 3. Delete master specs for section_name = 'PU Door' and data_type = 'CTP'
$deleted_specs = $conn->exec("
    DELETE FROM dtc_master_dtc_specs
    WHERE section_name = 'PU Door' AND data_type = 'CTP'
");
echo "Deleted $deleted_specs master specs.\n";

echo "Cleanup complete!\n";

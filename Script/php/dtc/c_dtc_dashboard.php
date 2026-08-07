<?php
// c_dtc_dashboard.php
require_once '../datasource/ds_dtc_dashboard.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'read':
        $parameterId = 1; // Example: hardcoded to OMEGA 6.8.9 Torque Hinge Center
        $date = date('Y-m-d');
        echo json_encode(ds_getSessions($parameterId, $date));
        break;
        
    case 'update':
        $models = isset($_POST['models']) ? json_decode($_POST['models'], true) : [];
        if (!empty($models)) {
            $success = ds_saveMeasurements($models);
            if ($success) {
                echo json_encode(["status" => "success"]);
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Failed to save data"]);
            }
        }
        break;
        
    default:
        echo json_encode(["error" => "Invalid action"]);
        break;
}
?>

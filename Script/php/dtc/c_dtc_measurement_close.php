<?php
// Script/php/dtc/c_dtc_measurement_close.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    $conn = getDBConnection();
    
    $param_id = isset($_POST['parameter_id']) ? intval($_POST['parameter_id']) : 0;
    $inspection_date = isset($_POST['inspection_date']) ? $_POST['inspection_date'] : '';
    
    if (!$param_id || !$inspection_date) {
        throw new Exception("Missing required fields (parameter_id, inspection_date).");
    }
    
    // Check if session exists
    $sql_check = "SELECT session_id, is_closed FROM dtc_inspection_sessions 
                  WHERE parameter_id = :param_id 
                  AND DATE(inspection_date) = :idate";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([':param_id' => $param_id, ':idate' => $inspection_date]);
    $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        throw new Exception("Measurement data not found for this date. Please save data first.");
    }
    
    if ($existing['is_closed'] == 1) {
        throw new Exception("This measurement is already closed.");
    }
    
    $session_id = $existing['session_id'];
    
    $sql_upd = "UPDATE dtc_inspection_sessions SET is_closed = 1 WHERE session_id = :sid";
    $stmt_upd = $conn->prepare($sql_upd);
    $stmt_upd->execute([':sid' => $session_id]);
    
    echo json_encode(["status" => "success", "message" => "Measurement for $inspection_date has been permanently closed."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

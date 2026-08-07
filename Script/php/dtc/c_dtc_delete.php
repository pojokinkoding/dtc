<?php
require_once '../../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['parameter_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter ID is required.']);
    exit;
}

$param_id = intval($_POST['parameter_id']);

try {
    $conn = getDBConnection();
    
    // Past Month Immutability Check
    $stmtMonth = $conn->prepare("SELECT target_month FROM dtc_master_parameters WHERE parameter_id = :pid");
    $stmtMonth->execute([':pid' => $param_id]);
    $target_month = $stmtMonth->fetchColumn();

    if ($target_month && $target_month < date('Y-m')) {
        echo json_encode(['status' => 'error', 'message' => "Parameter dan data periode bulan lalu ($target_month) terkunci total dan tidak dapat dihapus."]);
        exit;
    }
    
    // Explicitly delete from measurements first
    $stmt_sessions = $conn->prepare("SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id = :param_id");
    $stmt_sessions->execute([':param_id' => $param_id]);
    $sessions = $stmt_sessions->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($sessions as $sid) {
        $conn->exec("DELETE FROM dtc_measurements WHERE session_id = " . intval($sid));
    }
    
    // Explicitly delete sessions
    $stmt_del_sess = $conn->prepare("DELETE FROM dtc_inspection_sessions WHERE parameter_id = :param_id");
    $stmt_del_sess->execute([':param_id' => $param_id]);

    // Finally delete the master parameter
    $stmt = $conn->prepare("DELETE FROM dtc_master_parameters WHERE parameter_id = :param_id");
    $stmt->execute([':param_id' => $param_id]);
    
    echo json_encode(['status' => 'success', 'message' => 'Parameter deleted successfully.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

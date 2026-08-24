<?php
// c_master_spec_delete.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDBConnection();
    
    $spec_id = isset($_POST['spec_id']) && $_POST['spec_id'] !== '' ? intval($_POST['spec_id']) : 0;

    if ($spec_id > 0) {
        // Runtime migration does not rely on an FK, so clean checkpoint templates explicitly.
        try {
            $stmtTemplates = $conn->prepare("DELETE FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id");
            $stmtTemplates->execute([':spec_id' => $spec_id]);
        } catch (Exception $e) {
            // Table may not exist yet on an installation that has never saved a checkpoint template.
        }
        $sql = "DELETE FROM dtc_master_dtc_specs WHERE spec_id = :spec_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':spec_id' => $spec_id]);
        
        echo json_encode(["status" => "success", "message" => "Master Spec deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid spec_id"]);
    }
} catch (Exception $e) {
    // Check if it's a constraint violation
    if (strpos($e->getMessage(), 'ORA-02292') !== false) {
        echo json_encode(["status" => "error", "message" => "Cannot delete this spec because it has associated measurement data."]);
    } else {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>

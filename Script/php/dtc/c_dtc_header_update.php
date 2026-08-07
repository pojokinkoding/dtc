<?php
// c_dtc_header_update.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDBConnection();
    
    $parameter_id = isset($_POST['parameter_id']) && $_POST['parameter_id'] !== '' ? intval($_POST['parameter_id']) : 0;
    $target_month = $_POST['target_month'] ?? '';
    $line_name = $_POST['line_name'] ?? '';
    $section_name = $_POST['section_name'] ?? '';
    $process_name = $_POST['process_name'] ?? '';
    $model_name = $_POST['model_name'] ?? '';
    $spec_id = isset($_POST['spec_id']) && $_POST['spec_id'] !== '' ? intval($_POST['spec_id']) : 0;
    $lsl = isset($_POST['lsl']) && $_POST['lsl'] !== '' ? floatval($_POST['lsl']) : 0;
    $usl = isset($_POST['usl']) && $_POST['usl'] !== '' ? floatval($_POST['usl']) : 0;
    // Auto-calculate target value as midpoint (LSL + USL) / 2
    $target_value = ($lsl + $usl) / 2;

    if ($parameter_id <= 0) {
        throw new Exception("Invalid Parameter ID");
    }

    // Check if parameter belongs to a past month
    $stmtCheckMonth = $conn->prepare("SELECT target_month FROM dtc_master_parameters WHERE parameter_id = :pid");
    $stmtCheckMonth->execute([':pid' => $parameter_id]);
    $param_month = $stmtCheckMonth->fetchColumn();

    if (($param_month && $param_month < date('Y-m')) || (!empty($target_month) && $target_month < date('Y-m'))) {
        throw new Exception("DTC Header dan spesifikasi periode bulan lalu ($param_month) terkunci total dan tidak dapat diubah/di-edit.");
    }

    // Handle reference image upload
    $reference_image = null;
    $has_new_image = false;
    if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/dtc/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext = strtolower(pathinfo($_FILES['reference_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            throw new Exception("Invalid image format. Allowed: jpg, jpeg, png, gif, webp");
        }
        
        // Delete old image if exists
        $stmt_old = $conn->prepare("SELECT reference_image FROM dtc_master_parameters WHERE parameter_id = :pid");
        $stmt_old->execute([':pid' => $parameter_id]);
        $old_image = $stmt_old->fetchColumn();
        if ($old_image && file_exists(__DIR__ . '/../../../' . $old_image)) {
            @unlink(__DIR__ . '/../../../' . $old_image);
        }
        
        $filename = 'ref_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $target_path = $upload_dir . $filename;
        if (!move_uploaded_file($_FILES['reference_image']['tmp_name'], $target_path)) {
            throw new Exception("Failed to upload image.");
        }
        $reference_image = 'uploads/dtc/' . $filename;
        $has_new_image = true;
    }

    // If a spec_id was selected, we want to pull its core details to update the historical snapshot
    if ($spec_id > 0) {
        $stmt_spec = $conn->prepare("SELECT item_check_name, sub_item_check_name, data_type, measuring_item, uom FROM dtc_master_dtc_specs WHERE spec_id = :spec_id");
        $stmt_spec->execute([':spec_id' => $spec_id]);
        $specData = $stmt_spec->fetch(PDO::FETCH_ASSOC);
        
        if($specData) {
            $sql = "UPDATE dtc_master_parameters 
                    SET target_month = :target_month,
                        line_name = :line_name, 
                        section_name = :section_name, 
                        process_name = :process_name,
                        spec_id = :spec_id,
                        lsl = :lsl,
                        usl = :usl,
                        target_value = :target_value,
                        model_name = :model_name,
                        item_check_name = :item_check_name,
                        sub_item_check_name = :sub_item_check_name,
                        data_type = :data_type,
                        measuring_item = :measuring_item,
                        uom = :uom" . 
                        ($has_new_image ? ", reference_image = :reference_image" : "") . "
                    WHERE parameter_id = :parameter_id";
            
            $params = [
                ':target_month' => $target_month,
                ':line_name' => $line_name,
                ':section_name' => $section_name,
                ':process_name' => $process_name,
                ':spec_id' => $spec_id,
                ':lsl' => $lsl,
                ':usl' => $usl,
                ':target_value' => $target_value,
                ':model_name' => $model_name,
                ':item_check_name' => $specData['item_check_name'],
                ':sub_item_check_name' => $specData['sub_item_check_name'],
                ':data_type' => $specData['data_type'],
                ':measuring_item' => $specData['measuring_item'],
                ':uom' => $specData['uom'],
                ':parameter_id' => $parameter_id
            ];
            if ($has_new_image) {
                $params[':reference_image'] = $reference_image;
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
    } else {
        // Fallback if no spec selected (should not happen normally)
        $sql = "UPDATE dtc_master_parameters 
                SET target_month = :target_month,
                    line_name = :line_name, 
                    section_name = :section_name, 
                    process_name = :process_name,
                    lsl = :lsl,
                    usl = :usl,
                    target_value = :target_value,
                    model_name = :model_name" . 
                    ($has_new_image ? ", reference_image = :reference_image" : "") . "
                WHERE parameter_id = :parameter_id";
                
        $params = [
            ':target_month' => $target_month,
            ':line_name' => $line_name,
            ':section_name' => $section_name,
            ':process_name' => $process_name,
            ':lsl' => $lsl,
            ':usl' => $usl,
            ':target_value' => $target_value,
            ':model_name' => $model_name,
            ':parameter_id' => $parameter_id
        ];
        if ($has_new_image) {
            $params[':reference_image'] = $reference_image;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    }
    
    echo json_encode(["status" => "success", "message" => "Header updated successfully."]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

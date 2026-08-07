<?php
// c_master_spec_save.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDBConnection();
    
    $spec_id = isset($_POST['spec_id']) && $_POST['spec_id'] !== '' ? intval($_POST['spec_id']) : 0;
    
    $model_name = $_POST['model_name'] ?? 'Default Model';
    $item_check_name = $_POST['item_check_name'] ?? '';
    $sub_item_check_name = $_POST['sub_item_check_name'] ?? '';
    $data_type = $_POST['data_type'] ?? '';
    $line_name = $_POST['line_name'] ?? '';
    $section_name = $_POST['section_name'] ?? '';
    $process_name = $_POST['process_name'] ?? '';
    $measuring_item = $_POST['measuring_item'] ?? '';
    
    $lsl = isset($_POST['lsl']) && $_POST['lsl'] !== '' ? floatval($_POST['lsl']) : 0;
    $usl = isset($_POST['usl']) && $_POST['usl'] !== '' ? floatval($_POST['usl']) : 0;
    $target_value = isset($_POST['target_value']) && $_POST['target_value'] !== '' ? floatval($_POST['target_value']) : 0;
    $uom = $_POST['uom'] ?? '';
    
    $target_zst = isset($_POST['target_zst']) && $_POST['target_zst'] !== '' ? floatval($_POST['target_zst']) : 4.0;
    $target_zlt = isset($_POST['target_zlt']) && $_POST['target_zlt'] !== '' ? floatval($_POST['target_zlt']) : 3.0;

    $isAdmin = (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin');

    if ($spec_id > 0) {
        if (!$isAdmin) {
            // Keep existing measuring_item if not admin
            $stmtCheck = $conn->prepare("SELECT measuring_item FROM dtc_master_dtc_specs WHERE spec_id = :spec_id");
            $stmtCheck->execute([':spec_id' => $spec_id]);
            $existing_measuring_item = $stmtCheck->fetchColumn();
            if ($existing_measuring_item) {
                $measuring_item = $existing_measuring_item;
            }
        }
        // UPDATE
        $sql = "UPDATE dtc_master_dtc_specs SET 
                    model_name = :model_name,
                    item_check_name = :item_check_name,
                    sub_item_check_name = :sub_item_check_name,
                    data_type = :data_type,
                    line_name = :line_name,
                    section_name = :section_name,
                    process_name = :process_name,
                    measuring_item = :measuring_item,
                    lsl = :lsl,
                    usl = :usl,
                    target_value = :target_value,
                    uom = :uom,
                    target_zst = :target_zst,
                    target_zlt = :target_zlt,
                    updated_at = CURRENT_TIMESTAMP
                WHERE spec_id = :spec_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':model_name' => $model_name,
            ':item_check_name' => $item_check_name,
            ':sub_item_check_name' => $sub_item_check_name,
            ':data_type' => $data_type,
            ':line_name' => $line_name,
            ':section_name' => $section_name,
            ':process_name' => $process_name,
            ':measuring_item' => $measuring_item,
            ':lsl' => $lsl,
            ':usl' => $usl,
            ':target_value' => $target_value,
            ':uom' => $uom,
            ':target_zst' => $target_zst,
            ':target_zlt' => $target_zlt,
            ':spec_id' => $spec_id
        ]);
        echo json_encode(["status" => "success", "message" => "Master Spec updated successfully"]);
    } else {
        // INSERT
        $sql = "INSERT INTO dtc_master_dtc_specs (
                    model_name, item_check_name, sub_item_check_name, data_type, line_name, section_name, process_name, measuring_item,
                    lsl, usl, target_value, uom, target_zst, target_zlt
                ) VALUES (
                    :model_name, :item_check_name, :sub_item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item,
                    :lsl, :usl, :target_value, :uom, :target_zst, :target_zlt
                )";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':model_name' => $model_name,
            ':item_check_name' => $item_check_name,
            ':sub_item_check_name' => $sub_item_check_name,
            ':data_type' => $data_type,
            ':line_name' => $line_name,
            ':section_name' => $section_name,
            ':process_name' => $process_name,
            ':measuring_item' => $measuring_item,
            ':lsl' => $lsl,
            ':usl' => $usl,
            ':target_value' => $target_value,
            ':uom' => $uom,
            ':target_zst' => $target_zst,
            ':target_zlt' => $target_zlt
        ]);
        echo json_encode(["status" => "success", "message" => "Master Spec created successfully"]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

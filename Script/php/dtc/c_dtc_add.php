<?php
// c_dtc_add.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // We expect target_month from the form
    $target_month = $_POST['target_month'] ?? date('Y-m');
    $spec_id = isset($_POST['spec_id']) && $_POST['spec_id'] !== '' ? intval($_POST['spec_id']) : 0;
    if ($spec_id <= 0) {
        throw new Exception("Missing required fields (Spec ID).");
    }

    // Check for duplicates
    $sql_check = "SELECT parameter_id FROM dtc_master_parameters WHERE spec_id = :spec_id AND target_month = :target_month";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([':spec_id' => $spec_id, ':target_month' => $target_month]);
    if ($stmt_check->fetchColumn()) {
        throw new Exception("This Parameter has already been added for the selected month.");
    }

    // Fetch spec details from DB
    $sql_spec = "SELECT * FROM dtc_master_dtc_specs WHERE spec_id = :spec_id";
    $stmt_spec = $conn->prepare($sql_spec);
    $stmt_spec->execute([':spec_id' => $spec_id]);
    $spec = $stmt_spec->fetch(PDO::FETCH_ASSOC);

    if (!$spec) {
        throw new Exception("Spec not found.");
    }

    $item_check_name = $spec['item_check_name'];
    $sub_item_check_name = $spec['sub_item_check_name'];
    $data_type = $spec['data_type'];
    $line_name = $spec['line_name'];
    $section_name = $spec['section_name'];
    $process_name = $spec['process_name'];
    $measuring_item = $spec['measuring_item'];
    $target_zst = $spec['target_zst'];
    $target_zlt = $spec['target_zlt'];

    // STEP 1.5: Handle reference image upload
    $reference_image = null;
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
        $filename = 'ref_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $target_path = $upload_dir . $filename;
        if (!move_uploaded_file($_FILES['reference_image']['tmp_name'], $target_path)) {
            throw new Exception("Failed to upload image.");
        }
        $reference_image = 'uploads/dtc/' . $filename;
    }

    // STEP 2: Insert the parameter record
    $sql = "INSERT INTO dtc_master_parameters 
            (spec_id, target_month, item_check_name, sub_item_check_name, data_type, line_name, section_name, process_name, measuring_item, target_zst, target_zlt, reference_image) 
            VALUES (:spec_id, :target_month, :item_check_name, :sub_item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item, :target_zst, :target_zlt, :reference_image)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':spec_id' => $spec_id,
        ':target_month' => $target_month,
        ':item_check_name' => $item_check_name,
        ':sub_item_check_name' => $sub_item_check_name,
        ':data_type' => $data_type,
        ':line_name' => $line_name,
        ':section_name' => $section_name,
        ':process_name' => $process_name,
        ':measuring_item' => $measuring_item,
        ':target_zst' => $target_zst,
        ':target_zlt' => $target_zlt,
        ':reference_image' => $reference_image
    ]);
    
    echo json_encode(["status" => "success", "message" => "DTC successfully added"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

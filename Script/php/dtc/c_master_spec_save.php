<?php
// c_master_spec_save.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

function ensureMasterSpecCheckpointTable(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS dtc_master_spec_checkpoints (
        master_checkpoint_id INT AUTO_INCREMENT PRIMARY KEY,
        spec_id INT NOT NULL,
        checkpoint_name VARCHAR(200) NOT NULL,
        checkpoint_type VARCHAR(50) NOT NULL DEFAULT 'Qualitative',
        spec_value VARCHAR(200) DEFAULT NULL,
        lsl DECIMAL(10,3) DEFAULT NULL,
        target_value DECIMAL(10,3) DEFAULT NULL,
        usl DECIMAL(10,3) DEFAULT NULL,
        reference_image VARCHAR(255) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_master_spec_checkpoint (spec_id)
    )");
}

function saveMasterSpecCheckpoints(PDO $conn, int $specId, array $checkpoints, array $files): void {
    $conn->prepare("DELETE FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id")->execute([':spec_id' => $specId]);
    $stmt = $conn->prepare("INSERT INTO dtc_master_spec_checkpoints
        (spec_id, checkpoint_name, checkpoint_type, spec_value, lsl, target_value, usl, reference_image, sort_order)
        VALUES (:spec_id, :name, :checkpoint_type, :spec_value, :lsl, :target, :usl, :image, :sort_order)");

    foreach ($checkpoints as $index => $checkpoint) {
        $name = trim($checkpoint['checkpoint_name'] ?? '');
        if ($name === '') continue;
        $checkpointType = ($checkpoint['checkpoint_type'] ?? 'Qualitative') === 'Quantitative' ? 'Quantitative' : 'Qualitative';
        $imagePath = $checkpoint['reference_image'] ?? null;
        $imageIndex = (int)($checkpoint['image_index'] ?? $index);
        if (isset($files['name'][$imageIndex]) && $files['error'][$imageIndex] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($files['name'][$imageIndex], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) throw new Exception('Format gambar checkpoint harus JPG, JPEG, PNG, atau GIF.');
            $uploadDir = '../../../uploads/dtc/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) throw new Exception('Folder upload checkpoint tidak dapat dibuat.');
            $filename = 'master_cp_' . $specId . '_' . time() . '_' . $index . '.' . $ext;
            if (!move_uploaded_file($files['tmp_name'][$imageIndex], $uploadDir . $filename)) throw new Exception('Gagal mengunggah gambar checkpoint.');
            $imagePath = 'uploads/dtc/' . $filename;
        }
        $stmt->execute([
            ':spec_id' => $specId,
            ':name' => $name,
            ':checkpoint_type' => $checkpointType,
            ':spec_value' => trim($checkpoint['spec_value'] ?? '') ?: null,
            ':lsl' => ($checkpoint['lsl'] ?? '') !== '' ? (float)$checkpoint['lsl'] : null,
            ':target' => ($checkpoint['target_value'] ?? '') !== '' ? (float)$checkpoint['target_value'] : null,
            ':usl' => ($checkpoint['usl'] ?? '') !== '' ? (float)$checkpoint['usl'] : null,
            ':image' => $imagePath ?: null,
            ':sort_order' => $index
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDBConnection();
    ensureMasterSpecCheckpointTable($conn);
    
    $spec_id = isset($_POST['spec_id']) && $_POST['spec_id'] !== '' ? intval($_POST['spec_id']) : 0;
    
    $model_name = $_POST['model_name'] ?? 'Default Model';
    $item_check_name = $_POST['item_check_name'] ?? '';
    $sub_item_check_name = $_POST['sub_item_check_name'] ?? '';
    $data_type = $_POST['data_type'] ?? '';
    $line_name = $_POST['line_name'] ?? '';
    $section_name = $_POST['section_name'] ?? '';
    $process_name = $_POST['process_name'] ?? '';
    $measuring_item = $_POST['measuring_item'] ?? '';
    $isCheckpointType = in_array(strtoupper(trim($data_type)), ['TIME CHECK', 'F/PROOF'], true);
    $checkpoints = json_decode($_POST['checkpoints'] ?? '[]', true);
    if (!is_array($checkpoints)) $checkpoints = [];
    if ($isCheckpointType && empty($checkpoints)) throw new Exception('Time Check dan F/Proof wajib memiliki minimal satu checkpoint.');
    if ($isCheckpointType) $measuring_item = 'Qualitative';
    
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
        if ($isCheckpointType) {
            saveMasterSpecCheckpoints($conn, $spec_id, $checkpoints, $_FILES['checkpoint_images'] ?? []);
        } else {
            $conn->prepare("DELETE FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id")->execute([':spec_id' => $spec_id]);
        }
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
        $newSpecId = (int)$conn->lastInsertId();
        if ($isCheckpointType) saveMasterSpecCheckpoints($conn, $newSpecId, $checkpoints, $_FILES['checkpoint_images'] ?? []);
        echo json_encode(["status" => "success", "message" => "Master Spec created successfully"]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

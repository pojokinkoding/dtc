<?php
// c_master_spec_copy.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Metode request tidak valid."]);
    exit;
}

try {
    $conn = getDBConnection();

    // Ensure template checkpoints table exists
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $source_model = trim($_POST['source_model'] ?? '');
    $target_model = trim($_POST['target_model'] ?? '');
    $source_line = trim($_POST['source_line'] ?? '');
    $source_section = trim($_POST['source_section'] ?? '');
    $target_line = trim($_POST['target_line'] ?? '');
    $target_section = trim($_POST['target_section'] ?? '');

    if (empty($source_model)) {
        throw new Exception("Harap pilih Model Sumber.");
    }
    if (empty($target_model)) {
        throw new Exception("Harap isi Nama Model Tujuan.");
    }

    // Query all matching specs from source model
    $sql = "SELECT * FROM dtc_master_dtc_specs WHERE UPPER(TRIM(model_name)) = UPPER(TRIM(:source_model))";
    $params = [':source_model' => $source_model];

    if (!empty($source_line)) {
        $sql .= " AND UPPER(TRIM(line_name)) = UPPER(TRIM(:source_line))";
        $params[':source_line'] = $source_line;
    }
    if (!empty($source_section)) {
        $sql .= " AND UPPER(TRIM(section_name)) = UPPER(TRIM(:source_section))";
        $params[':source_section'] = $source_section;
    }

    $sql .= " ORDER BY spec_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $sourceSpecs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sourceSpecs)) {
        throw new Exception("Tidak ditemukan spesifikasi pada model sumber '$source_model' dengan filter yang dipilih.");
    }

    $conn->beginTransaction();

    $stmtInsertSpec = $conn->prepare("INSERT INTO dtc_master_dtc_specs (
        model_name, item_check_name, sub_item_check_name, data_type, line_name, section_name, process_name, measuring_item,
        lsl, usl, target_value, uom, target_zst, target_zlt
    ) VALUES (
        :model_name, :item_check_name, :sub_item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item,
        :lsl, :usl, :target_value, :uom, :target_zst, :target_zlt
    )");

    $stmtGetCheckpoints = $conn->prepare("SELECT * FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id ORDER BY sort_order ASC, master_checkpoint_id ASC");
    $stmtInsertCheckpoint = $conn->prepare("INSERT INTO dtc_master_spec_checkpoints (
        spec_id, checkpoint_name, checkpoint_type, spec_value, lsl, target_value, usl, reference_image, sort_order
    ) VALUES (
        :spec_id, :checkpoint_name, :checkpoint_type, :spec_value, :lsl, :target_value, :usl, :reference_image, :sort_order
    )");

    $copiedSpecsCount = 0;
    $copiedCheckpointsCount = 0;

    foreach ($sourceSpecs as $spec) {
        $destLine = !empty($target_line) ? $target_line : $spec['line_name'];
        $destSection = !empty($target_section) ? $target_section : $spec['section_name'];

        $stmtInsertSpec->execute([
            ':model_name' => $target_model,
            ':item_check_name' => $spec['item_check_name'],
            ':sub_item_check_name' => $spec['sub_item_check_name'] ?? null,
            ':data_type' => $spec['data_type'],
            ':line_name' => $destLine,
            ':section_name' => $destSection,
            ':process_name' => $spec['process_name'],
            ':measuring_item' => $spec['measuring_item'],
            ':lsl' => $spec['lsl'],
            ':usl' => $spec['usl'],
            ':target_value' => $spec['target_value'],
            ':uom' => $spec['uom'],
            ':target_zst' => $spec['target_zst'],
            ':target_zlt' => $spec['target_zlt']
        ]);

        $newSpecId = (int)$conn->lastInsertId();
        $copiedSpecsCount++;

        // Check if there are checkpoints for this spec
        $stmtGetCheckpoints->execute([':spec_id' => $spec['spec_id']]);
        $checkpoints = $stmtGetCheckpoints->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($checkpoints)) {
            foreach ($checkpoints as $cp) {
                $stmtInsertCheckpoint->execute([
                    ':spec_id' => $newSpecId,
                    ':checkpoint_name' => $cp['checkpoint_name'],
                    ':checkpoint_type' => $cp['checkpoint_type'] ?? 'Qualitative',
                    ':spec_value' => $cp['spec_value'] ?? null,
                    ':lsl' => ($cp['lsl'] !== null && $cp['lsl'] !== '') ? (float)$cp['lsl'] : null,
                    ':target_value' => ($cp['target_value'] !== null && $cp['target_value'] !== '') ? (float)$cp['target_value'] : null,
                    ':usl' => ($cp['usl'] !== null && $cp['usl'] !== '') ? (float)$cp['usl'] : null,
                    ':reference_image' => $cp['reference_image'] ?? null,
                    ':sort_order' => (int)($cp['sort_order'] ?? 0)
                ]);
                $copiedCheckpointsCount++;
            }
        }
    }

    $conn->commit();

    $message = "Berhasil menyalin {$copiedSpecsCount} spesifikasi dari model '{$source_model}' ke model '{$target_model}'.";
    if ($copiedCheckpointsCount > 0) {
        $message .= " Termasuk {$copiedCheckpointsCount} sub-checkpoint template.";
    }

    echo json_encode([
        "status" => "success",
        "message" => $message,
        "copied_specs" => $copiedSpecsCount,
        "copied_checkpoints" => $copiedCheckpointsCount
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

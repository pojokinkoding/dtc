<?php
// c_dtc_add.php - Auto Generate DTC Parameters per Line & Section for Current Month
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
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
    )");
    
    // Always target current month
    $target_month = date('Y-m');
    $line_name = trim($_POST['line_name'] ?? '');
    $section_name = trim($_POST['section_name'] ?? '');

    if (empty($line_name) || empty($section_name)) {
        throw new Exception("Harap pilih Line dan Section.");
    }

    // Query specs matching selected Line and Section
    $sql_specs = "SELECT * FROM dtc_master_dtc_specs 
                  WHERE (UPPER(line_name) = UPPER(:line_name) OR :line_all = 'ALL')
                    AND (UPPER(section_name) = UPPER(:section_name) OR :sec_all = 'ALL')
                  ORDER BY spec_id ASC";
    $stmt_specs = $conn->prepare($sql_specs);
    $stmt_specs->execute([
        ':line_name' => $line_name,
        ':line_all' => strtoupper($line_name),
        ':section_name' => $section_name,
        ':sec_all' => strtoupper($section_name)
    ]);
    $specs = $stmt_specs->fetchAll(PDO::FETCH_ASSOC);

    if (empty($specs)) {
        throw new Exception("Tidak ditemukan Master Spec untuk Line '$line_name' dan Section '$section_name'.");
    }

    // Handle reference image upload
    $reference_image = null;
    if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/dtc/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext = strtolower(pathinfo($_FILES['reference_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            throw new Exception("Format gambar tidak valid. Format yang diizinkan: jpg, jpeg, png, gif, webp");
        }
        $filename = 'ref_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $target_path = $upload_dir . $filename;
        if (!move_uploaded_file($_FILES['reference_image']['tmp_name'], $target_path)) {
            throw new Exception("Gagal mengunggah gambar acuan.");
        }
        $reference_image = 'uploads/dtc/' . $filename;
    }

    $sql_check = "SELECT parameter_id FROM dtc_master_parameters 
                  WHERE target_month = :target_month 
                    AND (
                      spec_id = :spec_id 
                      OR (
                        UPPER(line_name) = UPPER(:line_name) 
                        AND UPPER(section_name) = UPPER(:section_name) 
                        AND UPPER(process_name) = UPPER(:process_name) 
                        AND UPPER(item_check_name) = UPPER(:item_check_name) 
                        AND UPPER(COALESCE(sub_item_check_name, '')) = UPPER(:sub_item_check_name)
                      )
                    )
                  LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);

    $sql_insert = "INSERT INTO dtc_master_parameters 
            (spec_id, model_name, target_month, item_check_name, sub_item_check_name, data_type, lsl, usl, target_value, uom, section_name, line_name, process_name, measuring_item, target_zst, target_zlt, reference_image) 
            VALUES (:spec_id, :model_name, :target_month, :item_check_name, :sub_item_check_name, :data_type, :lsl, :usl, :target_value, :uom, :section_name, :line_name, :process_name, :measuring_item, :target_zst, :target_zlt, :reference_image)";
    $stmt_insert = $conn->prepare($sql_insert);

    $stmt_upd_existing = $conn->prepare("UPDATE dtc_master_parameters SET 
            model_name = COALESCE(model_name, :model_name),
            lsl = COALESCE(lsl, :lsl),
            usl = COALESCE(usl, :usl),
            target_value = COALESCE(target_value, :target_value),
            uom = COALESCE(uom, :uom),
            spec_id = COALESCE(spec_id, :spec_id)
        WHERE parameter_id = :pid");

    $addedCount = 0;
    $skippedCount = 0;

    foreach ($specs as $spec) {
        $subItem = $spec['sub_item_check_name'] ?? '';
        $stmt_check->execute([
            ':spec_id' => $spec['spec_id'],
            ':target_month' => $target_month,
            ':line_name' => $spec['line_name'],
            ':section_name' => $spec['section_name'],
            ':process_name' => $spec['process_name'],
            ':item_check_name' => $spec['item_check_name'],
            ':sub_item_check_name' => $subItem
        ]);

        $existingPid = $stmt_check->fetchColumn();
        if ($existingPid) {
            $skippedCount++;
            // Backfill any missing fields if parameter already exists
            $stmt_upd_existing->execute([
                ':model_name' => $spec['model_name'],
                ':lsl' => $spec['lsl'],
                ':usl' => $spec['usl'],
                ':target_value' => $spec['target_value'],
                ':uom' => $spec['uom'],
                ':spec_id' => $spec['spec_id'],
                ':pid' => $existingPid
            ]);
            continue;
        }

        $refImgToUse = $reference_image ? $reference_image : ($spec['reference_image'] ?? null);

        $stmt_insert->execute([
            ':spec_id' => $spec['spec_id'],
            ':model_name' => $spec['model_name'],
            ':target_month' => $target_month,
            ':item_check_name' => $spec['item_check_name'],
            ':sub_item_check_name' => $spec['sub_item_check_name'],
            ':data_type' => $spec['data_type'],
            ':lsl' => $spec['lsl'],
            ':usl' => $spec['usl'],
            ':target_value' => $spec['target_value'],
            ':uom' => $spec['uom'],
            ':section_name' => $spec['section_name'],
            ':line_name' => $spec['line_name'],
            ':process_name' => $spec['process_name'],
            ':measuring_item' => $spec['measuring_item'],
            ':target_zst' => $spec['target_zst'],
            ':target_zlt' => $spec['target_zlt'],
            ':reference_image' => $refImgToUse
        ]);
        $addedCount++;
    }

    // Copy template checkpoints from Master Spec to dtc_checkpoints for all parameters
    // matching this line, section and month that do not have them yet
    $syncCheckpointSql = "
        INSERT INTO dtc_checkpoints
            (parameter_id, checkpoint_name, checkpoint_type, spec_value, lsl, target_value, usl, reference_image, sort_order)
        SELECT p.parameter_id, t.checkpoint_name, t.checkpoint_type, t.spec_value,
               t.lsl, t.target_value, t.usl, t.reference_image, t.sort_order
        FROM dtc_master_parameters p
        LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
        INNER JOIN dtc_master_spec_checkpoints t ON t.spec_id = p.spec_id
        WHERE p.target_month = :target_month
          AND (UPPER(TRIM(COALESCE(p.line_name, spec.line_name))) = UPPER(TRIM(:line_name)) OR :line_all = 'ALL')
          AND (UPPER(TRIM(COALESCE(p.section_name, spec.section_name))) = UPPER(TRIM(:section_name)) OR :sec_all = 'ALL')
          AND NOT EXISTS (
              SELECT 1 FROM dtc_checkpoints c
              WHERE c.parameter_id = p.parameter_id AND BINARY c.checkpoint_name = BINARY t.checkpoint_name
          )
    ";
    $stmtSync = $conn->prepare($syncCheckpointSql);
    $stmtSync->execute([
        ':target_month' => $target_month,
        ':line_name' => $line_name,
        ':line_all' => strtoupper($line_name),
        ':section_name' => $section_name,
        ':sec_all' => strtoupper($section_name)
    ]);
    $syncedCpCount = $stmtSync->rowCount();

    if ($addedCount === 0 && $skippedCount > 0 && $syncedCpCount === 0) {
        echo json_encode([
            "status" => "warning", 
            "message" => "Tidak ada parameter baru yang dibuat. Seluruh $skippedCount DTC Parameter untuk $line_name - $section_name pada bulan $target_month sudah ada sebelumnya dan checkpoint sudah sinkron."
        ]);
        exit;
    }

    $msg = "Berhasil membuat $addedCount DTC Parameter baru untuk $line_name - $section_name (Bulan Ini: $target_month).";
    if ($syncedCpCount > 0) {
        $msg .= " $syncedCpCount sub-checkpoint berhasil disinkronkan dari Master Spec.";
    }
    if ($skippedCount > 0) {
        $msg .= " ($skippedCount item yang sudah ada diabaikan/diperbarui).";
    }

    echo json_encode(["status" => "success", "message" => $msg]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

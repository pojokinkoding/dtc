<?php
// c_master_spec_save.php
require_once __DIR__ . '/../../../config/config.php';
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ensureSpecChangeLogTable(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS dtc_spec_change_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        spec_id INT NOT NULL,
        field_name VARCHAR(100) NOT NULL,
        old_value TEXT,
        new_value TEXT,
        change_reason TEXT,
        changed_by INT,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_spec_change_log_spec (spec_id),
        INDEX idx_spec_change_log_date (changed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function logSpecChange(PDO $conn, int $specId, string $fieldName, $oldValue, $newValue, string $reason, int $userId, bool $force = false): void {
    if (!$force && ($oldValue === $newValue || ($oldValue === null && $newValue === null) || ($oldValue === '' && $newValue === ''))) {
        return;
    }
    $stmt = $conn->prepare("INSERT INTO dtc_spec_change_log (spec_id, field_name, old_value, new_value, change_reason, changed_by) VALUES (:spec_id, :field, :old, :new, :reason, :user)");
    $stmt->execute([
        ':spec_id' => $specId,
        ':field' => $fieldName,
        ':old' => $oldValue ?? '',
        ':new' => $newValue ?? '',
        ':reason' => $reason,
        ':user' => $userId
    ]);
}

function saveMasterSpecCheckpoints(PDO $conn, int $specId, array $checkpoints, array $files): void {
    $files += ['name' => [], 'error' => [], 'tmp_name' => []];
    $conn->prepare("DELETE FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id")->execute([':spec_id' => $specId]);
    $stmt = $conn->prepare("INSERT INTO dtc_master_spec_checkpoints
        (spec_id, checkpoint_name, checkpoint_type, spec_value, lsl, target_value, usl, reference_image, sort_order)
        VALUES (:spec_id, :name, :checkpoint_type, :spec_value, :lsl, :target, :usl, :image, :sort_order)");

    $sortOrder = 0;
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
            $filename = 'master_cp_' . $specId . '_' . time() . '_' . $sortOrder . '.' . $ext;
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
            ':sort_order' => $sortOrder
        ]);
        $sortOrder++;
    }

    // Auto-sync template checkpoints to current month's parameters with this spec_id
    $currentMonth = date('Y-m');
    $stmtSync = $conn->prepare("
        INSERT INTO dtc_checkpoints
            (parameter_id, checkpoint_name, checkpoint_type, spec_value, lsl, target_value, usl, reference_image, sort_order)
        SELECT p.parameter_id, t.checkpoint_name, t.checkpoint_type, t.spec_value,
               t.lsl, t.target_value, t.usl, t.reference_image, t.sort_order
        FROM dtc_master_parameters p
        INNER JOIN dtc_master_spec_checkpoints t ON t.spec_id = p.spec_id
        WHERE p.spec_id = :spec_id
          AND p.target_month = :target_month
          AND NOT EXISTS (
              SELECT 1 FROM dtc_checkpoints c
              WHERE c.parameter_id = p.parameter_id AND BINARY c.checkpoint_name = BINARY t.checkpoint_name
          )
    ");
    $stmtSync->execute([':spec_id' => $specId, ':target_month' => $currentMonth]);
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
        
        // Fetch old values for change logging
        ensureSpecChangeLogTable($conn);
        $userId = $_SESSION['user_id'] ?? 0;
        $stmtOld = $conn->prepare("SELECT * FROM dtc_master_dtc_specs WHERE spec_id = :spec_id");
        $stmtOld->execute([':spec_id' => $spec_id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
        
        // Change reason from POST (evident/remark)
        $changeReason = trim($_POST['change_reason'] ?? '');
        
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
        
        // Log changes
        $loggedCount = 0;
        if ($oldData) {
            $fields = [
                'model_name' => $model_name,
                'item_check_name' => $item_check_name,
                'sub_item_check_name' => $sub_item_check_name,
                'data_type' => $data_type,
                'line_name' => $line_name,
                'section_name' => $section_name,
                'process_name' => $process_name,
                'measuring_item' => $measuring_item,
                'lsl' => $lsl,
                'usl' => $usl,
                'target_value' => $target_value,
                'uom' => $uom,
                'target_zst' => $target_zst,
                'target_zlt' => $target_zlt,
            ];
            foreach ($fields as $field => $newVal) {
                $oldVal = $oldData[$field] ?? null;
                if ((string)$oldVal !== (string)$newVal) {
                    logSpecChange($conn, $spec_id, $field, $oldVal, $newVal, $changeReason, $userId);
                    $loggedCount++;
                }
            }
        }
        
        if ($isCheckpointType) {
            // Catat perubahan template checkpoint (kasus utama Time Check / F/Proof)
            $stmtOldCp = $conn->prepare("SELECT checkpoint_name FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id ORDER BY sort_order, master_checkpoint_id");
            $stmtOldCp->execute([':spec_id' => $spec_id]);
            $oldCpNames = $stmtOldCp->fetchAll(PDO::FETCH_COLUMN);
            saveMasterSpecCheckpoints($conn, $spec_id, $checkpoints, $_FILES['checkpoint_images'] ?? []);
            $stmtNewCp = $conn->prepare("SELECT checkpoint_name FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id ORDER BY sort_order, master_checkpoint_id");
            $stmtNewCp->execute([':spec_id' => $spec_id]);
            $newCpNames = $stmtNewCp->fetchAll(PDO::FETCH_COLUMN);
            if ($oldCpNames !== $newCpNames) {
                logSpecChange($conn, $spec_id, 'checkpoints', implode(', ', $oldCpNames), implode(', ', $newCpNames), $changeReason, $userId);
                $loggedCount++;
            }
        } else {
            $conn->prepare("DELETE FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id")->execute([':spec_id' => $spec_id]);
        }

        // Propagasi ke runtime bulan berjalan: baris checkpoint yang SUDAH ADA di dtc_checkpoints
        // selama ini tidak pernah di-UPDATE (sync hanya INSERT yang belum ada by name), sehingga
        // modal Input Data menampilkan LSL/USL lama yang beda dengan Master. reference_image runtime
        // sengaja tidak ikut (bisa berisi foto aktual, bukan template).
        if ($isCheckpointType) {
            try {
                $conn->prepare("
                    UPDATE dtc_checkpoints c
                    INNER JOIN dtc_master_parameters p ON p.parameter_id = c.parameter_id
                    INNER JOIN dtc_master_spec_checkpoints t ON t.spec_id = p.spec_id
                        AND BINARY t.checkpoint_name = BINARY c.checkpoint_name
                    SET c.checkpoint_type = t.checkpoint_type,
                        c.spec_value = t.spec_value,
                        c.lsl = t.lsl,
                        c.target_value = t.target_value,
                        c.usl = t.usl,
                        c.sort_order = t.sort_order
                    WHERE p.spec_id = :spec_id AND p.target_month = :month
                ")->execute([':spec_id' => $spec_id, ':month' => date('Y-m')]);
            } catch (Throwable $tSync) {
                error_log('runtime checkpoint sync failed: ' . $tSync->getMessage());
            }
        }

        // Alasan tidak boleh hilang: bila diisi tapi tidak ada perubahan terdeteksi, simpan sebagai remark.
        // Cegah duplikat: jangan catat lagi bila sama persis dengan alasan terakhir yang tersimpan.
        if ($changeReason !== '' && $loggedCount === 0) {
            $stmtLast = $conn->prepare("SELECT change_reason FROM dtc_spec_change_log WHERE spec_id = :spec_id ORDER BY log_id DESC LIMIT 1");
            $stmtLast->execute([':spec_id' => $spec_id]);
            $lastReason = trim((string)$stmtLast->fetchColumn());
            if ($lastReason !== $changeReason) {
                logSpecChange($conn, $spec_id, 'remark', null, null, $changeReason, $userId, true);
            }
        }

        // Sync spec changes to current month's running model parameters (dtc_master_parameters)
        $currentMonth = date('Y-m');
        $stmtSyncParams = $conn->prepare("
            UPDATE dtc_master_parameters p
            INNER JOIN dtc_master_dtc_specs s ON s.spec_id = p.spec_id
            SET p.lsl = s.lsl,
                p.usl = s.usl,
                p.target_value = s.target_value,
                p.uom = s.uom,
                p.target_zst = s.target_zst,
                p.target_zlt = s.target_zlt,
                p.measuring_item = s.measuring_item,
                p.data_type = s.data_type,
                p.line_name = s.line_name,
                p.section_name = s.section_name,
                p.process_name = s.process_name,
                p.item_check_name = s.item_check_name,
                p.sub_item_check_name = s.sub_item_check_name,
                p.model_name = s.model_name
            WHERE p.spec_id = :spec_id
              AND p.target_month = :target_month
        ");
        $stmtSyncParams->execute([':spec_id' => $spec_id, ':target_month' => $currentMonth]);

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
        $changeReasonIns = trim($_POST['change_reason'] ?? '');
        if ($changeReasonIns !== '') {
            ensureSpecChangeLogTable($conn);
            logSpecChange($conn, $newSpecId, 'created', null, null, $changeReasonIns, (int)($_SESSION['user_id'] ?? 0), true);
        }
        echo json_encode(["status" => "success", "message" => "Master Spec created successfully"]);
    }
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

<?php
// c_dtc_running_model.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    // Template checkpoint dari Master Spec. Akan dicopy ke checkpoint runtime saat model diaktifkan.
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
    try { $conn->exec("ALTER TABLE dtc_checkpoints ADD COLUMN checkpoint_type VARCHAR(50) DEFAULT 'Qualitative'"); } catch (Exception $e) {}

    // Ensure table exists
    $tableSql = "CREATE TABLE IF NOT EXISTS dtc_running_models (
        running_id INT AUTO_INCREMENT PRIMARY KEY,
        target_month VARCHAR(7) NOT NULL,
        line_name VARCHAR(50) NOT NULL,
        section_name VARCHAR(50) NOT NULL,
        model_name VARCHAR(100) NOT NULL,
        data_type VARCHAR(50) NOT NULL DEFAULT 'General',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_running_model (target_month, line_name, section_name, model_name, data_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($tableSql);
    try { $conn->exec("ALTER TABLE dtc_running_models ADD COLUMN data_type VARCHAR(50) NOT NULL DEFAULT 'General' AFTER model_name"); } catch (Exception $e) {}
    try { $conn->exec("ALTER TABLE dtc_running_models ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER is_active"); } catch (Exception $e) {}
    try { $conn->exec("ALTER TABLE dtc_running_models ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"); } catch (Exception $e) {}
    try { $conn->exec("ALTER TABLE dtc_running_models DROP INDEX uq_running_model, ADD UNIQUE KEY uq_running_model (target_month, line_name, section_name, model_name, data_type)"); } catch (Exception $e) {}

    $action = $_GET['action'] ?? $_POST['action'] ?? 'get';
    $currentMonth = date('Y-m');

    if ($action === 'clear_all') {
        $conn->exec("TRUNCATE TABLE dtc_running_models");
        echo json_encode(['status' => 'success', 'message' => 'Tabel Running Model berhasil dikosongkan.']);
        exit;
    }

    if ($action === 'get') {
        $month = $_GET['month'] ?? $currentMonth;

        $sql = "SELECT running_id, target_month, line_name, section_name, model_name, data_type, is_active, created_at
                FROM dtc_running_models 
                WHERE target_month = :m AND is_active = 1
                " . getIPAccessFilterSQL('line_name', 'section_name') . getUserAccessFilterSQL('line_name', 'section_name') . "
                ORDER BY line_name ASC, section_name ASC, model_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':m' => $month]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    if ($action === 'get_available_models') {
        $line = trim($_GET['line'] ?? '');
        $section = trim($_GET['section'] ?? '');
        $month = trim($_GET['month'] ?? $currentMonth);

        $sql = "SELECT DISTINCT model_name FROM (
                    SELECT COALESCE(p.model_name, spec.model_name) AS model_name,
                           COALESCE(p.line_name, spec.line_name) AS line_name,
                           COALESCE(p.section_name, spec.section_name) AS section_name
                    FROM dtc_master_parameters p
                    LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
                    WHERE p.target_month = :month

                    UNION

                    SELECT model_name, line_name, section_name
                    FROM dtc_master_dtc_specs
                ) AS all_combined
                WHERE model_name IS NOT NULL AND TRIM(model_name) != ''";
        
        $params = [':month' => $month];

        if (!empty($line)) {
            $sql .= " AND UPPER(TRIM(line_name)) = UPPER(TRIM(:line))";
            $params[':line'] = $line;
        }
        if (!empty($section)) {
            $sql .= " AND UPPER(TRIM(section_name)) = UPPER(TRIM(:section))";
            $params[':section'] = $section;
        }

        $sql .= " ORDER BY model_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $models = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode(['status' => 'success', 'models' => $models]);
        exit;
    }

    if ($action === 'add') {
        $month = $_POST['target_month'] ?? $currentMonth;
        $line = $_POST['line_name'] ?? '';
        $section = $_POST['section_name'] ?? '';
        $model = $_POST['model_name'] ?? '';
        $dataType = $_POST['data_type'] ?? '';
        // General means all parameters registered for this model in Master Spec.
        // The actual type remains on every parameter (CTQ/CTP/Time Check/F/Proof).
        $isGeneral = strtoupper(trim($dataType)) === 'GENERAL';

        if (empty($month) || empty($line) || empty($section) || empty($model) || empty($dataType)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields (month, line, section, model, data type)']);
            exit;
        }

        // 1. Insert or Reactivate model record with ON DUPLICATE KEY UPDATE (prevents Error 1062)
        $insertStmt = $conn->prepare("
            INSERT INTO dtc_running_models (target_month, line_name, section_name, model_name, data_type, is_active, created_at)
            VALUES (:m, :line, :section, :model, :datatype, 1, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE is_active = 1, created_at = CURRENT_TIMESTAMP
        ");
        $insertStmt->execute([
            ':m' => $month,
            ':line' => $line,
            ':section' => $section,
            ':model' => $model,
            ':datatype' => $dataType
        ]);

        // 2. Ensure parameter entries exist in dtc_master_parameters for this line, section, model, data type & month
        $paramTypeCondition = $isGeneral ? '' : "\n              AND UPPER(TRIM(COALESCE(p.data_type, spec.data_type))) = UPPER(TRIM(:datatype))";
        $checkParamStmt = $conn->prepare("
            SELECT COUNT(*) FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.target_month = :m 
              AND UPPER(TRIM(COALESCE(p.line_name, spec.line_name))) = UPPER(TRIM(:line)) 
              AND UPPER(TRIM(COALESCE(p.section_name, spec.section_name))) = UPPER(TRIM(:section)) 
              AND UPPER(TRIM(COALESCE(p.model_name, spec.model_name))) = UPPER(TRIM(:model))
              $paramTypeCondition
        ");
        $checkParamArgs = [':m' => $month, ':line' => $line, ':section' => $section, ':model' => $model];
        if (!$isGeneral) $checkParamArgs[':datatype'] = $dataType;
        $checkParamStmt->execute($checkParamArgs);
        $paramCount = $checkParamStmt->fetchColumn();

        if ($paramCount == 0) {
            // Copy matching specs from dtc_master_dtc_specs if parameters don't exist yet
            $specTypeCondition = $isGeneral ? '' : "\n                  AND UPPER(TRIM(data_type)) = UPPER(TRIM(:datatype))";
            $specStmt = $conn->prepare("
                SELECT * FROM dtc_master_dtc_specs 
                WHERE UPPER(TRIM(line_name)) = UPPER(TRIM(:line)) 
                  AND UPPER(TRIM(section_name)) = UPPER(TRIM(:section)) 
                  AND UPPER(TRIM(model_name)) = UPPER(TRIM(:model))
                  $specTypeCondition
            ");
            $specArgs = [':line' => $line, ':section' => $section, ':model' => $model];
            if (!$isGeneral) $specArgs[':datatype'] = $dataType;
            $specStmt->execute($specArgs);
            $specs = $specStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($specs)) {
                $insParamStmt = $conn->prepare("
                    INSERT INTO dtc_master_parameters (
                        spec_id, model_name, target_month, item_check_name, sub_item_check_name, 
                        data_type, lsl, usl, target_value, uom, section_name, line_name, 
                        process_name, measuring_item, target_zst, target_zlt
                    ) VALUES (
                        :spec_id, :model_name, :target_month, :item_check_name, :sub_item_check_name, 
                        :data_type, :lsl, :usl, :target_value, :uom, :section_name, :line_name, 
                        :process_name, :measuring_item, :target_zst, :target_zlt
                    )
                ");
                foreach ($specs as $sp) {
                    $insParamStmt->execute([
                        ':spec_id' => $sp['spec_id'],
                        ':model_name' => $sp['model_name'],
                        ':target_month' => $month,
                        ':item_check_name' => $sp['item_check_name'],
                        ':sub_item_check_name' => $sp['sub_item_check_name'],
                        ':data_type' => $sp['data_type'],
                        ':lsl' => $sp['lsl'],
                        ':usl' => $sp['usl'],
                        ':target_value' => $sp['target_value'],
                        ':uom' => $sp['uom'],
                        ':section_name' => $sp['section_name'],
                        ':line_name' => $sp['line_name'],
                        ':process_name' => $sp['process_name'],
                        ':measuring_item' => $sp['measuring_item'],
                        ':target_zst' => $sp['target_zst'],
                        ':target_zlt' => $sp['target_zlt']
                    ]);
                }
            }
        }

        // Salin template checkpoint Master Spec ke parameter bulan berjalan. Query ini juga
        // menangani parameter yang sudah pernah dibuat tetapi belum punya checkpoint.
        $syncTypeCondition = $isGeneral ? '' : "\n              AND UPPER(TRIM(COALESCE(p.data_type, spec.data_type))) = UPPER(TRIM(:datatype))";
        $syncCheckpointStmt = $conn->prepare("
            INSERT INTO dtc_checkpoints
                (parameter_id, checkpoint_name, checkpoint_type, spec_value, lsl, target_value, usl, reference_image, sort_order)
            SELECT p.parameter_id, t.checkpoint_name, t.checkpoint_type, t.spec_value,
                   t.lsl, t.target_value, t.usl, t.reference_image, t.sort_order
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            INNER JOIN dtc_master_spec_checkpoints t ON t.spec_id = p.spec_id
            WHERE p.target_month = :m
              AND UPPER(TRIM(COALESCE(p.line_name, spec.line_name))) = UPPER(TRIM(:line))
              AND UPPER(TRIM(COALESCE(p.section_name, spec.section_name))) = UPPER(TRIM(:section))
              AND UPPER(TRIM(COALESCE(p.model_name, spec.model_name))) = UPPER(TRIM(:model))
              $syncTypeCondition
              AND NOT EXISTS (
                  SELECT 1 FROM dtc_checkpoints c
                  WHERE c.parameter_id = p.parameter_id AND BINARY c.checkpoint_name = BINARY t.checkpoint_name
              )
        ");
        $syncArgs = [':m' => $month, ':line' => $line, ':section' => $section, ':model' => $model];
        if (!$isGeneral) $syncArgs[':datatype'] = $dataType;
        $syncCheckpointStmt->execute($syncArgs);

        echo json_encode(['status' => 'success', 'message' => "Running model '$model' ($line - $section) successfully added."]);
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['running_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid running model ID.']);
            exit;
        }

        // Verify section permission: Only Admin or user with the SAME section can delete
        $stmtCheck = $conn->prepare("SELECT running_id, line_name, section_name FROM dtc_running_models WHERE running_id = :id");
        $stmtCheck->execute([':id' => $id]);
        $rm = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$rm) {
            echo json_encode(['status' => 'error', 'message' => 'Running model tidak ditemukan.']);
            exit;
        }

        $userRole = strtolower(trim($_SESSION['role'] ?? ''));
        $userSection = strtolower(trim($_SESSION['section_name'] ?? ''));
        $rmSection = strtolower(trim($rm['section_name'] ?? ''));

        if ($userRole !== 'admin') {
            if (empty($userSection) || $userSection !== $rmSection) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Akses ditolak. Penghapusan running model hanya dapat dilakukan oleh user dari section ' . $rm['section_name'] . ' atau Admin.'
                ]);
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE dtc_running_models SET is_active = 0 WHERE running_id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(['status' => 'success', 'message' => 'Running model removed successfully.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

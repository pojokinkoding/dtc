<?php
// c_dtc_running_model.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();

    // Ensure table exists
    $tableSql = "CREATE TABLE IF NOT EXISTS dtc_running_models (
        running_id INT AUTO_INCREMENT PRIMARY KEY,
        target_month VARCHAR(7) NOT NULL,
        line_name VARCHAR(50) NOT NULL,
        section_name VARCHAR(50) NOT NULL,
        model_name VARCHAR(100) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_running_model (target_month, line_name, section_name, model_name)
    )";
    $conn->exec($tableSql);

    $action = $_GET['action'] ?? $_POST['action'] ?? 'get';
    $currentMonth = date('Y-m');

    if ($action === 'clear_all') {
        $conn->exec("TRUNCATE TABLE dtc_running_models");
        echo json_encode(['status' => 'success', 'message' => 'Tabel Running Model berhasil dikosongkan.']);
        exit;
    }

    if ($action === 'get') {
        $month = $_GET['month'] ?? $currentMonth;

        $sql = "SELECT running_id, target_month, line_name, section_name, model_name, is_active, created_at 
                FROM dtc_running_models 
                WHERE target_month = :m AND is_active = 1
                " . getIPAccessFilterSQL('line_name', 'section_name') . getUserAccessFilterSQL('line_name', 'section_name') . "
                ORDER BY line_name ASC, section_name ASC, model_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':m' => $month]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // DEBUG: Check if ALPHA 4.5 exists in master parameters
        $debugStmt = $conn->query("SELECT COUNT(*) FROM dtc_master_parameters WHERE model_name = 'ALPHA 4.5'");
        $alphaCount = $debugStmt->fetchColumn();

        echo json_encode(['status' => 'success', 'data' => $data, 'debug_alpha_count' => $alphaCount]);
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
        $line = trim($_POST['line_name'] ?? '');
        $section = trim($_POST['section_name'] ?? '');
        $model = trim($_POST['model_name'] ?? '');

        if (empty($line) || empty($section) || empty($model)) {
            echo json_encode(['status' => 'error', 'message' => 'Line, Section, and Model Name are required.']);
            exit;
        }

        // 1. Check if model record already exists FOR THIS EXACT LINE, SECTION, MODEL, MONTH
        $checkStmt = $conn->prepare("SELECT running_id, is_active FROM dtc_running_models 
                                     WHERE target_month = :m 
                                       AND UPPER(TRIM(line_name)) = UPPER(TRIM(:line)) 
                                       AND UPPER(TRIM(section_name)) = UPPER(TRIM(:section)) 
                                       AND UPPER(TRIM(model_name)) = UPPER(TRIM(:model))");
        $checkStmt->execute([
            ':m' => $month,
            ':line' => $line,
            ':section' => $section,
            ':model' => $model
        ]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['is_active'] == 1) {
                echo json_encode(['status' => 'info', 'message' => "Model '$model' sudah aktif di list Running Model untuk Line $line & Section $section."]);
                exit;
            } else {
                // Update to active and update created_at timestamp to CURRENT_TIMESTAMP
                $updateStmt = $conn->prepare("UPDATE dtc_running_models SET is_active = 1, created_at = CURRENT_TIMESTAMP WHERE running_id = :id");
                $updateStmt->execute([':id' => $existing['running_id']]);
            }
        } else {
            // Insert new record with created_at = CURRENT_TIMESTAMP
            $insertStmt = $conn->prepare("INSERT INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active, created_at)
                                         VALUES (:m, :line, :section, :model, 1, CURRENT_TIMESTAMP)");
            $insertStmt->execute([
                ':m' => $month,
                ':line' => $line,
                ':section' => $section,
                ':model' => $model
            ]);
        }

        // 2. Ensure parameter entries exist in dtc_master_parameters for this line, section, model & month
        $checkParamStmt = $conn->prepare("
            SELECT COUNT(*) FROM dtc_master_parameters 
            WHERE target_month = :m 
              AND UPPER(TRIM(line_name)) = UPPER(TRIM(:line)) 
              AND UPPER(TRIM(section_name)) = UPPER(TRIM(:section)) 
              AND UPPER(TRIM(model_name)) = UPPER(TRIM(:model))
        ");
        $checkParamStmt->execute([':m' => $month, ':line' => $line, ':section' => $section, ':model' => $model]);
        $paramCount = $checkParamStmt->fetchColumn();

        if ($paramCount == 0) {
            // Copy matching specs from dtc_master_dtc_specs if parameters don't exist yet
            $specStmt = $conn->prepare("
                SELECT * FROM dtc_master_dtc_specs 
                WHERE UPPER(TRIM(line_name)) = UPPER(TRIM(:line)) 
                  AND UPPER(TRIM(section_name)) = UPPER(TRIM(:section)) 
                  AND UPPER(TRIM(model_name)) = UPPER(TRIM(:model))
            ");
            $specStmt->execute([':line' => $line, ':section' => $section, ':model' => $model]);
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

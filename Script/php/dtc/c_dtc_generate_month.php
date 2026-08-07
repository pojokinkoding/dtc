<?php
// c_dtc_generate_month.php
// Feature: Generate Running Models, Parameters, and Checkpoints for target month based on source month (bulan lalu).
// Rule: Jika data pada bulan tujuan (target month) sudah ada/dibuat, JANGAN di-insert atau di-replace (dilewati saja untuk mencegah duplikasi).

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = getDBConnection();

    $user_role = strtolower(trim($_SESSION['role'] ?? ''));
    if ($user_role !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Hanya Admin yang diizinkan melakukan Generate Bulan Ini.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan.']);
        exit;
    }

    $sourceMonth = trim($_POST['source_month'] ?? '');
    $targetMonth = trim($_POST['target_month'] ?? '');

    if (empty($sourceMonth) || empty($targetMonth)) {
        echo json_encode(['status' => 'error', 'message' => 'Bulan asal dan bulan tujuan wajib diisi.']);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}$/', $sourceMonth) || !preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
        echo json_encode(['status' => 'error', 'message' => 'Format bulan tidak valid. Format harus YYYY-MM.']);
        exit;
    }

    if ($sourceMonth === $targetMonth) {
        echo json_encode(['status' => 'error', 'message' => 'Bulan asal (bulan lalu) dan bulan tujuan (bulan ini) tidak boleh sama.']);
        exit;
    }

    $conn->beginTransaction();

    // -------------------------------------------------------------
    // STEP 1: Generate / Copy Running Models from Source Month
    // -------------------------------------------------------------
    $stmtRM = $conn->prepare("
        SELECT line_name, section_name, model_name 
        FROM dtc_running_models 
        WHERE target_month = :sm AND is_active = 1
    ");
    $stmtRM->execute([':sm' => $sourceMonth]);
    $runningModels = $stmtRM->fetchAll(PDO::FETCH_ASSOC);

    $countRMNew = 0;
    $countRMExist = 0;

    $stmtInsertRM = $conn->prepare("
        INSERT INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active)
        VALUES (:tm, :line, :sec, :model, 1)
    ");

    foreach ($runningModels as $rm) {
        $stmtCheckRM = $conn->prepare("
            SELECT running_id FROM dtc_running_models 
            WHERE target_month = :tm 
              AND TRIM(UPPER(line_name)) = TRIM(UPPER(:line)) 
              AND TRIM(UPPER(section_name)) = TRIM(UPPER(:sec)) 
              AND TRIM(UPPER(model_name)) = TRIM(UPPER(:model))
            LIMIT 1
        ");
        $stmtCheckRM->execute([
            ':tm' => $targetMonth,
            ':line' => $rm['line_name'],
            ':sec' => $rm['section_name'],
            ':model' => $rm['model_name']
        ]);
        $existRM = $stmtCheckRM->fetchColumn();

        if (!$existRM) {
            $stmtInsertRM->execute([
                ':tm' => $targetMonth,
                ':line' => $rm['line_name'],
                ':sec' => $rm['section_name'],
                ':model' => $rm['model_name']
            ]);
            $countRMNew++;
        } else {
            // Sudah ada di bulan tujuan: SKIP / lewatkan (jangan insert / update / replace)
            $countRMExist++;
        }
    }

    // -------------------------------------------------------------
    // STEP 2: Generate / Copy Master Parameters & Checkpoints
    // -------------------------------------------------------------
    $stmtParam = $conn->prepare("
        SELECT * FROM dtc_master_parameters 
        WHERE target_month = :sm
    ");
    $stmtParam->execute([':sm' => $sourceMonth]);
    $sourceParams = $stmtParam->fetchAll(PDO::FETCH_ASSOC);

    $countParamNew = 0;
    $countParamExist = 0;
    $countCpNew = 0;
    $countCpExist = 0;

    $sqlInsParam = "
        INSERT INTO dtc_master_parameters (
            spec_id, model_name, target_month, item_check_name, sub_item_check_name, 
            data_type, lsl, usl, target_value, uom, section_name, line_name, 
            process_name, measuring_item, target_zst, target_zlt, reference_image
        ) VALUES (
            :spec_id, :model_name, :target_month, :item_check_name, :sub_item_check_name, 
            :data_type, :lsl, :usl, :target_value, :uom, :section_name, :line_name, 
            :process_name, :measuring_item, :target_zst, :target_zlt, :reference_image
        )
    ";
    $stmtInsParam = $conn->prepare($sqlInsParam);

    $sqlInsCp = "
        INSERT INTO dtc_checkpoints (
            parameter_id, checkpoint_name, checkpoint_type, spec_value, 
            lsl, target_value, usl, reference_image, sort_order
        ) VALUES (
            :pid, :cp_name, :cp_type, :spec_val, 
            :lsl, :target_val, :usl, :ref_img, :sort_order
        )
    ";
    $stmtInsCp = $conn->prepare($sqlInsCp);

    foreach ($sourceParams as $sp) {
        $sourceParamId = $sp['parameter_id'];
        $targetParamId = null;

        // Check 1: Match by spec_id if available
        if (!empty($sp['spec_id']) && intval($sp['spec_id']) > 0) {
            $stmtChkP = $conn->prepare("
                SELECT parameter_id FROM dtc_master_parameters 
                WHERE target_month = :tm AND spec_id = :spec_id
                LIMIT 1
            ");
            $stmtChkP->execute([':tm' => $targetMonth, ':spec_id' => $sp['spec_id']]);
            $targetParamId = $stmtChkP->fetchColumn();
        }

        // Check 2: Match by exact parameter fields (line, section, process, item, sub_item, data_type, measuring_item)
        if (!$targetParamId) {
            $stmtChkP2 = $conn->prepare("
                SELECT parameter_id FROM dtc_master_parameters 
                WHERE target_month = :tm 
                  AND TRIM(UPPER(COALESCE(line_name, ''))) = TRIM(UPPER(COALESCE(:line, '')))
                  AND TRIM(UPPER(COALESCE(section_name, ''))) = TRIM(UPPER(COALESCE(:sec, '')))
                  AND TRIM(UPPER(COALESCE(process_name, ''))) = TRIM(UPPER(COALESCE(:proc, '')))
                  AND TRIM(UPPER(COALESCE(item_check_name, ''))) = TRIM(UPPER(COALESCE(:item, '')))
                  AND TRIM(UPPER(COALESCE(sub_item_check_name, ''))) = TRIM(UPPER(COALESCE(:subitem, '')))
                  AND TRIM(UPPER(COALESCE(data_type, ''))) = TRIM(UPPER(COALESCE(:dtype, '')))
                  AND TRIM(UPPER(COALESCE(measuring_item, ''))) = TRIM(UPPER(COALESCE(:mitem, '')))
                  AND (
                        TRIM(UPPER(COALESCE(model_name, ''))) = TRIM(UPPER(COALESCE(:model, '')))
                     OR COALESCE(:model, '') = ''
                     OR COALESCE(model_name, '') = ''
                  )
                LIMIT 1
            ");
            $stmtChkP2->execute([
                ':tm' => $targetMonth,
                ':line' => $sp['line_name'] ?? '',
                ':sec' => $sp['section_name'] ?? '',
                ':proc' => $sp['process_name'] ?? '',
                ':item' => $sp['item_check_name'] ?? '',
                ':subitem' => $sp['sub_item_check_name'] ?? '',
                ':dtype' => $sp['data_type'] ?? '',
                ':mitem' => $sp['measuring_item'] ?? '',
                ':model' => $sp['model_name'] ?? ''
            ]);
            $targetParamId = $stmtChkP2->fetchColumn();
        }

        if (!$targetParamId) {
            // Belum ada di bulan tujuan: Insert parameter baru
            $stmtInsParam->execute([
                ':spec_id' => $sp['spec_id'] ?? null,
                ':model_name' => $sp['model_name'] ?? null,
                ':target_month' => $targetMonth,
                ':item_check_name' => $sp['item_check_name'] ?? null,
                ':sub_item_check_name' => $sp['sub_item_check_name'] ?? null,
                ':data_type' => $sp['data_type'] ?? null,
                ':lsl' => $sp['lsl'] ?? null,
                ':usl' => $sp['usl'] ?? null,
                ':target_value' => $sp['target_value'] ?? null,
                ':uom' => $sp['uom'] ?? null,
                ':section_name' => $sp['section_name'] ?? null,
                ':line_name' => $sp['line_name'] ?? null,
                ':process_name' => $sp['process_name'] ?? null,
                ':measuring_item' => $sp['measuring_item'] ?? null,
                ':target_zst' => $sp['target_zst'] ?? null,
                ':target_zlt' => $sp['target_zlt'] ?? null,
                ':reference_image' => $sp['reference_image'] ?? null
            ]);
            $targetParamId = $conn->lastInsertId();
            $countParamNew++;
        } else {
            // Sudah ada di bulan tujuan: SKIP / lewatkan (jangan insert atau replace parameter)
            $countParamExist++;
        }

        // Copy checkpoints linked to sourceParamId if checkpoint does not already exist
        $stmtCpSource = $conn->prepare("
            SELECT * FROM dtc_checkpoints 
            WHERE parameter_id = :spid 
            ORDER BY sort_order ASC, checkpoint_id ASC
        ");
        $stmtCpSource->execute([':spid' => $sourceParamId]);
        $sourceCps = $stmtCpSource->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sourceCps as $scp) {
            $stmtChkCp = $conn->prepare("
                SELECT checkpoint_id FROM dtc_checkpoints 
                WHERE parameter_id = :tpid 
                  AND TRIM(UPPER(checkpoint_name)) = TRIM(UPPER(:cpname))
                LIMIT 1
            ");
            $stmtChkCp->execute([
                ':tpid' => $targetParamId,
                ':cpname' => trim($scp['checkpoint_name'])
            ]);
            $existingCpId = $stmtChkCp->fetchColumn();

            if (!$existingCpId) {
                $stmtInsCp->execute([
                    ':pid' => $targetParamId,
                    ':cp_name' => $scp['checkpoint_name'],
                    ':cp_type' => $scp['checkpoint_type'] ?? 'Qualitative',
                    ':spec_val' => $scp['spec_value'] ?? null,
                    ':lsl' => $scp['lsl'] ?? null,
                    ':target_val' => $scp['target_value'] ?? null,
                    ':usl' => $scp['usl'] ?? null,
                    ':ref_img' => $scp['reference_image'] ?? null,
                    ':sort_order' => $scp['sort_order'] ?? 0
                ]);
                $countCpNew++;
            } else {
                // Checkpoint sudah ada: SKIP / lewatkan (jangan insert / replace)
                $countCpExist++;
            }
        }
    }

    $conn->commit();

    if (count($sourceParams) === 0 && count($runningModels) === 0) {
        echo json_encode([
            'status' => 'warning',
            'message' => "Tidak ditemukan data Model/Parameter pada bulan asal ($sourceMonth)."
        ]);
        exit;
    }

    $msg = "Berhasil meng-generate data untuk bulan $targetMonth (berdasarkan $sourceMonth):\n" .
           "• Running Model: $countRMNew baru (" . ($countRMNew + $countRMExist) . " total, $countRMExist dilewati)\n" .
           "• Master Parameter: $countParamNew baru (" . ($countParamNew + $countParamExist) . " total, $countParamExist dilewati)\n" .
           "• Checkpoint: $countCpNew baru (" . ($countCpNew + $countCpExist) . " total, $countCpExist dilewati)\n\n" .
           "Catatan: Data yang sudah ada pada bulan tujuan dilewati (tidak di-insert/di-replace) untuk mencegah duplikasi.";

    echo json_encode([
        'status' => 'success',
        'message' => $msg,
        'summary' => [
            'running_models_new' => $countRMNew,
            'running_models_exist' => $countRMExist,
            'parameters_new' => $countParamNew,
            'parameters_exist' => $countParamExist,
            'checkpoints_new' => $countCpNew,
            'checkpoints_exist' => $countCpExist
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}


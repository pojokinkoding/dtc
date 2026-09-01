<?php
// Script/php/dtc/c_dtc_checkpoint_manage.php
require_once '../../../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $conn = getDBConnection();
    
    // Auto-migrate columns if missing
    try { $conn->exec("ALTER TABLE dtc_checkpoints ADD COLUMN lsl DECIMAL(10,3) DEFAULT NULL"); } catch (Exception $e) {}
    try { $conn->exec("ALTER TABLE dtc_checkpoints ADD COLUMN target_value DECIMAL(10,3) DEFAULT NULL"); } catch (Exception $e) {}
    try { $conn->exec("ALTER TABLE dtc_checkpoints ADD COLUMN usl DECIMAL(10,3) DEFAULT NULL"); } catch (Exception $e) {}

    if (in_array($action, ['add', 'add_multiple', 'edit', 'delete', 'reorder'])) {
        $param_id_check = $_POST['parameter_id'] ?? $_GET['parameter_id'] ?? 0;
        if (!$param_id_check && isset($_POST['checkpoint_id'])) {
            $stmtC = $conn->prepare("SELECT parameter_id FROM dtc_checkpoints WHERE checkpoint_id = :cid");
            $stmtC->execute([':cid' => intval($_POST['checkpoint_id'])]);
            $param_id_check = $stmtC->fetchColumn();
        }
        if ($param_id_check) {
            $stmtM = $conn->prepare("SELECT target_month FROM dtc_master_parameters WHERE parameter_id = :pid");
            $stmtM->execute([':pid' => intval($param_id_check)]);
            $target_month = $stmtM->fetchColumn();
            if ($target_month && $target_month < date('Y-m')) {
                echo json_encode(['status' => 'error', 'message' => "Checkpoint dan parameter periode bulan lalu ($target_month) terkunci total dan tidak dapat diubah/dihapus."]);
                exit;
            }
        }
    }

    switch ($action) {
        case 'list':
            $param_id = $_GET['parameter_id'] ?? 0;
            if (!$param_id) {
                echo json_encode(['status' => 'error', 'message' => 'Missing parameter_id']);
                exit;
            }
            
            $stmt = $conn->prepare("SELECT * FROM dtc_checkpoints WHERE parameter_id = :pid ORDER BY sort_order ASC, checkpoint_id ASC");
            $stmt->execute([':pid' => $param_id]);
            $checkpoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => 'success', 'data' => $checkpoints]);
            break;
            
        case 'add':
            $param_id = $_POST['parameter_id'] ?? 0;
            $name = trim($_POST['checkpoint_name'] ?? '');
            $spec = trim($_POST['spec_value'] ?? '');
            $lsl = (isset($_POST['lsl']) && $_POST['lsl'] !== '') ? (float)$_POST['lsl'] : null;
            $target = (isset($_POST['target_value']) && $_POST['target_value'] !== '') ? (float)$_POST['target_value'] : null;
            $usl = (isset($_POST['usl']) && $_POST['usl'] !== '') ? (float)$_POST['usl'] : null;
            $checkpoint_type = $_POST['checkpoint_type'] ?? 'Qualitative';
            
            if (!$param_id || empty($name)) {
                echo json_encode(['status' => 'error', 'message' => 'Validasi Gagal: Parameter ID dan Nama Checkpoint wajib diisi.']);
                exit;
            }

            // Validasi 1: Consistency LSL vs USL
            if ($lsl !== null && $usl !== null && $lsl > $usl) {
                echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Nilai LSL ({$lsl}) tidak boleh lebih besar dari USL ({$usl})."]);
                exit;
            }

            // Validasi 2: Target Bounds Check
            if ($target !== null) {
                if ($lsl !== null && $target < $lsl) {
                    echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Nilai Target ({$target}) berada di bawah LSL ({$lsl})."]);
                    exit;
                }
                if ($usl !== null && $target > $usl) {
                    echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Nilai Target ({$target}) berada di atas USL ({$usl})."]);
                    exit;
                }
            }
            
            // Check duplicate
            $stmtChk = $conn->prepare("SELECT checkpoint_id FROM dtc_checkpoints WHERE parameter_id = :pid AND checkpoint_name = :name");
            $stmtChk->execute([':pid' => $param_id, ':name' => $name]);
            if ($stmtChk->fetch()) {
                echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Checkpoint dengan nama \"{$name}\" sudah ada pada parameter ini."]);
                exit;
            }
            
            // Get max sort_order
            $stmtMax = $conn->prepare("SELECT MAX(sort_order) as mx FROM dtc_checkpoints WHERE parameter_id = :pid");
            $stmtMax->execute([':pid' => $param_id]);
            $maxRow = $stmtMax->fetch(PDO::FETCH_ASSOC);
            $nextOrder = ($maxRow['mx'] !== null) ? (int)$maxRow['mx'] + 1 : 0;
            
            $stmtIns = $conn->prepare("INSERT INTO dtc_checkpoints (parameter_id, checkpoint_name, spec_value, lsl, target_value, usl, sort_order, checkpoint_type) VALUES (:pid, :name, :spec, :lsl, :target, :usl, :sort, :ctype)");
            $stmtIns->execute([
                ':pid' => $param_id,
                ':name' => $name,
                ':spec' => empty($spec) ? null : $spec,
                ':lsl' => $lsl,
                ':target' => $target,
                ':usl' => $usl,
                ':sort' => $nextOrder,
                ':ctype' => $checkpoint_type
            ]);
            
            $newId = $conn->lastInsertId();
            echo json_encode(['status' => 'success', 'message' => 'Checkpoint berhasil ditambahkan.', 'checkpoint_id' => $newId]);
            break;

        case 'add_multiple':
            $param_id = intval($_POST['parameter_id'] ?? 0);
            $rawCheckpoints = $_POST['checkpoints'] ?? [];

            if (!$param_id || empty($rawCheckpoints)) {
                echo json_encode(['status' => 'error', 'message' => 'Validasi Gagal: Parameter ID dan daftar checkpoint wajib diisi.']);
                exit;
            }

            if (is_string($rawCheckpoints)) {
                $rawCheckpoints = json_decode($rawCheckpoints, true);
            }

            if (!is_array($rawCheckpoints) || empty($rawCheckpoints)) {
                echo json_encode(['status' => 'error', 'message' => 'Validasi Gagal: Format data checkpoint tidak valid.']);
                exit;
            }

            $conn->beginTransaction();
            try {
                $stmtMax = $conn->prepare("SELECT MAX(sort_order) as mx FROM dtc_checkpoints WHERE parameter_id = :pid");
                $stmtMax->execute([':pid' => $param_id]);
                $maxRow = $stmtMax->fetch(PDO::FETCH_ASSOC);
                $nextOrder = ($maxRow['mx'] !== null) ? (int)$maxRow['mx'] + 1 : 0;

                $insertedCount = 0;
                $skippedCount = 0;
                $stmtChk = $conn->prepare("SELECT checkpoint_id FROM dtc_checkpoints WHERE parameter_id = :pid AND checkpoint_name = :name");
                $stmtIns = $conn->prepare("INSERT INTO dtc_checkpoints (parameter_id, checkpoint_name, spec_value, lsl, target_value, usl, sort_order, checkpoint_type) VALUES (:pid, :name, :spec, :lsl, :target, :usl, :sort, :ctype)");

                foreach ($rawCheckpoints as $cp) {
                    $name = trim($cp['checkpoint_name'] ?? $cp['name'] ?? '');
                    if (empty($name)) continue;

                    $spec = trim($cp['spec_value'] ?? $cp['spec'] ?? '');
                    $ctype = $cp['checkpoint_type'] ?? $cp['type'] ?? 'Qualitative';
                    $lsl = (isset($cp['lsl']) && $cp['lsl'] !== '') ? (float)$cp['lsl'] : null;
                    $target = (isset($cp['target_value']) && $cp['target_value'] !== '') ? (float)$cp['target_value'] : null;
                    $usl = (isset($cp['usl']) && $cp['usl'] !== '') ? (float)$cp['usl'] : null;

                    $stmtChk->execute([':pid' => $param_id, ':name' => $name]);
                    if ($stmtChk->fetch()) {
                        $skippedCount++;
                        continue;
                    }

                    $stmtIns->execute([
                        ':pid' => $param_id,
                        ':name' => $name,
                        ':spec' => empty($spec) ? null : $spec,
                        ':lsl' => $lsl,
                        ':target' => $target,
                        ':usl' => $usl,
                        ':sort' => $nextOrder++,
                        ':ctype' => $ctype
                    ]);
                    $insertedCount++;
                }

                $conn->commit();
                if ($insertedCount > 0) {
                    $msg = "Berhasil menambahkan {$insertedCount} checkpoint baru!";
                    if ($skippedCount > 0) {
                        $msg .= " ({$skippedCount} checkpoint diabaikan karena nama sudah ada).";
                    }
                    echo json_encode(['status' => 'success', 'message' => $msg, 'count' => $insertedCount, 'skipped' => $skippedCount]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Tidak ada checkpoint baru yang ditambahkan (mungkin semua nama checkpoint sudah ada).']);
                }
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan batch checkpoint: ' . $e->getMessage()]);
            }
            break;

        case 'update':
        case 'edit':
            $checkpoint_id = $_POST['checkpoint_id'] ?? 0;
            $name = trim($_POST['checkpoint_name'] ?? '');
            $spec = trim($_POST['spec_value'] ?? '');
            $lsl = (isset($_POST['lsl']) && $_POST['lsl'] !== '') ? (float)$_POST['lsl'] : null;
            $target = (isset($_POST['target_value']) && $_POST['target_value'] !== '') ? (float)$_POST['target_value'] : null;
            $usl = (isset($_POST['usl']) && $_POST['usl'] !== '') ? (float)$_POST['usl'] : null;
            $checkpoint_type = $_POST['checkpoint_type'] ?? 'Qualitative';
            
            if (!$checkpoint_id || empty($name)) {
                echo json_encode(['status' => 'error', 'message' => 'Validasi Gagal: Checkpoint ID dan nama checkpoint wajib diisi.']);
                exit;
            }

            // Validasi 1: Consistency LSL vs USL
            if ($lsl !== null && $usl !== null && $lsl > $usl) {
                echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Nilai LSL ({$lsl}) tidak boleh lebih besar dari USL ({$usl})."]);
                exit;
            }

            // Validasi 2: Target Bounds Check
            if ($target !== null) {
                if ($lsl !== null && $target < $lsl) {
                    echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Nilai Target ({$target}) berada di bawah LSL ({$lsl})."]);
                    exit;
                }
                if ($usl !== null && $target > $usl) {
                    echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Nilai Target ({$target}) berada di atas USL ({$usl})."]);
                    exit;
                }
            }

            // Check duplicate name for edit mode (excluding current checkpoint_id)
            $stmtChkEdit = $conn->prepare("SELECT checkpoint_id FROM dtc_checkpoints WHERE parameter_id = (SELECT parameter_id FROM dtc_checkpoints WHERE checkpoint_id = :cid) AND checkpoint_name = :name AND checkpoint_id != :cid");
            $stmtChkEdit->execute([':cid' => $checkpoint_id, ':name' => $name]);
            if ($stmtChkEdit->fetch()) {
                echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Checkpoint dengan nama \"{$name}\" sudah ada pada parameter ini."]);
                exit;
            }
            
            $stmtUpd = $conn->prepare("
                UPDATE dtc_checkpoints 
                SET checkpoint_name = :name, spec_value = :spec, lsl = :lsl, target_value = :target, usl = :usl, checkpoint_type = :ctype 
                WHERE checkpoint_id = :cid
            ");
            $stmtUpd->execute([
                ':name' => $name,
                ':spec' => empty($spec) ? null : $spec,
                ':lsl' => $lsl,
                ':target' => $target,
                ':usl' => $usl,
                ':ctype' => $checkpoint_type,
                ':cid' => $checkpoint_id
            ]);
            
            echo json_encode(['status' => 'success', 'message' => 'Checkpoint berhasil diperbarui.']);
            break;
            
        case 'delete':
            $checkpoint_id = $_POST['checkpoint_id'] ?? 0;
            if (!$checkpoint_id) {
                echo json_encode(['status' => 'error', 'message' => 'Missing checkpoint_id']);
                exit;
            }
            
            // Measurements with this checkpoint_id will have it set to NULL (ON DELETE SET NULL)
            $stmtDel = $conn->prepare("DELETE FROM dtc_checkpoints WHERE checkpoint_id = :cid");
            $stmtDel->execute([':cid' => $checkpoint_id]);
            
            echo json_encode(['status' => 'success', 'message' => 'Checkpoint deleted']);
            break;
            
        case 'upload_image':
            $checkpoint_id = $_POST['checkpoint_id'] ?? 0;
            if (!$checkpoint_id) {
                echo json_encode(['status' => 'error', 'message' => 'Missing checkpoint_id']);
                exit;
            }
            
            if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../../uploads/dtc/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = strtolower(pathinfo($_FILES['reference_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid file format. Only JPG, JPEG, PNG, GIF allowed.']);
                    exit;
                }
                
                // Get old image to delete it if exists
                $stmt_old = $conn->prepare("SELECT reference_image FROM dtc_checkpoints WHERE checkpoint_id = :cid");
                $stmt_old->execute([':cid' => $checkpoint_id]);
                $old_img = $stmt_old->fetchColumn();
                if ($old_img && file_exists('../../../' . $old_img)) {
                    @unlink('../../../' . $old_img);
                }
                
                $filename = 'cp_' . $checkpoint_id . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['reference_image']['tmp_name'], $target_path)) {
                    $db_path = 'uploads/dtc/' . $filename;
                    $stmt_upd = $conn->prepare("UPDATE dtc_checkpoints SET reference_image = :img WHERE checkpoint_id = :cid");
                    $stmt_upd->execute([':img' => $db_path, ':cid' => $checkpoint_id]);
                    
                    echo json_encode(['status' => 'success', 'message' => 'Image uploaded successfully', 'image_path' => $db_path]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No file uploaded or file upload error.']);
            }
            break;
            
        case 'toggle_close_day':
            $param_id = $_POST['parameter_id'] ?? 0;
            $date = $_POST['date'] ?? '';
            
            if (!$param_id || empty($date)) {
                echo json_encode(['status' => 'error', 'message' => 'Missing parameter_id or date']);
                exit;
            }
            
            $role = strtolower(trim($_SESSION['role'] ?? ''));
            if ($role !== 'admin') {
                echo json_encode(['status' => 'error', 'message' => 'Hanya Admin yang memiliki wewenang untuk mengunci/membuka hari.']);
                exit;
            }
            
            $stmt_sess = $conn->prepare("SELECT session_id, is_closed FROM dtc_inspection_sessions WHERE parameter_id = :pid AND inspection_date = :idate");
            $stmt_sess->execute([':pid' => $param_id, ':idate' => $date]);
            $session = $stmt_sess->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                $new_status = $session['is_closed'] == 1 ? 0 : 1;
                $stmt_upd = $conn->prepare("UPDATE dtc_inspection_sessions SET is_closed = :status WHERE session_id = :sid");
                $stmt_upd->execute([':status' => $new_status, ':sid' => $session['session_id']]);
                echo json_encode(['status' => 'success', 'message' => $new_status == 1 ? 'Hari berhasil dikunci.' : 'Hari berhasil dibuka kembali.']);
            } else {
                $user_id = $_SESSION['user_id'] ?? 2;
                $stmt_ins = $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, is_closed) VALUES (:pid, :idate, :uid, 1)");
                $stmt_ins->execute([':pid' => $param_id, ':idate' => $date, ':uid' => $user_id]);
                echo json_encode(['status' => 'success', 'message' => 'Hari berhasil dikunci.']);
            }
            break;

        case 'sync_from_master':
            $param_id = intval($_POST['parameter_id'] ?? $_GET['parameter_id'] ?? 0);
            if (!$param_id) {
                echo json_encode(['status' => 'error', 'message' => 'Missing parameter_id']);
                exit;
            }

            $stmtSync = $conn->prepare("
                INSERT INTO dtc_checkpoints
                    (parameter_id, checkpoint_name, checkpoint_type, spec_value, lsl, target_value, usl, reference_image, sort_order)
                SELECT p.parameter_id, t.checkpoint_name, t.checkpoint_type, t.spec_value,
                       t.lsl, t.target_value, t.usl, t.reference_image, t.sort_order
                FROM dtc_master_parameters p
                INNER JOIN dtc_master_spec_checkpoints t ON t.spec_id = p.spec_id
                WHERE p.parameter_id = :pid
                  AND NOT EXISTS (
                      SELECT 1 FROM dtc_checkpoints c
                      WHERE c.parameter_id = p.parameter_id AND BINARY c.checkpoint_name = BINARY t.checkpoint_name
                  )
            ");
            $stmtSync->execute([':pid' => $param_id]);
            $count = $stmtSync->rowCount();
            echo json_encode(['status' => 'success', 'message' => "Berhasil menyinkronkan $count checkpoint dari Master Spec.", 'synced_count' => $count]);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action. Use: list, add, delete, upload_image, toggle_close_day, sync_from_master']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

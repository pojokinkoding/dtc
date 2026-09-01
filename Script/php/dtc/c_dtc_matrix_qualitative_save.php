<?php
// Script/php/dtc/c_dtc_matrix_qualitative_save.php
// Supports both single qualitative cell saving and batch quantitative sample saving
require_once '../../../config/config.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$param_id = $_POST['parameter_id'] ?? 0;
$checkpoint_id = $_POST['checkpoint_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 2; // Fallback

if (!function_exists('isUnmeasuredValue')) {
    function isUnmeasuredValue($val) {
        return ($val === '' || $val === null || $val === '-');
    }
}

try {
    $conn = getDBConnection();
    
    $user_role = strtolower(trim($_SESSION['role'] ?? ''));
    $is_admin = ($user_role === 'admin');

    // Past Month Immutability Check
    $date_check = $_POST['inspection_date'] ?? $_POST['input_date'] ?? '';
    if (!empty($date_check) && substr($date_check, 0, 7) < date('Y-m')) {
        echo json_encode(['status' => 'error', 'message' => "Data periode bulan lalu terkunci total dan tidak dapat diubah atau diisi."]);
        exit;
    }

    // --- BATCH SAVING MODE (From Quantitative Input Modal) ---
    if (isset($_POST['inspection_date'])) {
        $date = $_POST['inspection_date'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        
        if (!$param_id || !$checkpoint_id || !$date) {
            echo json_encode(['status' => 'error', 'message' => 'Missing parameter_id, checkpoint_id, or inspection_date']);
            exit;
        }

        $today = date('Y-m-d');
        if ($date > $today) {
            echo json_encode(['status' => 'error', 'message' => 'Belum masuk tanggal pengisian untuk hari tersebut.']);
            exit;
        }

        $stmtLine = $conn->prepare("
            SELECT COALESCE(p.line_name, spec.line_name) as line_name
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.parameter_id = :pid
        ");
        $stmtLine->execute([':pid' => $param_id]);
        $paramLine = $stmtLine->fetchColumn() ?: 'REF 01';

        $setting_key = 'time_matrix_labels_' . $paramLine;
        $stmtLabel = $conn->prepare("SELECT setting_value FROM dtc_app_settings WHERE setting_key = :key OR setting_key = 'time_matrix_labels' ORDER BY (setting_key = :exact_key) DESC LIMIT 1");
        $stmtLabel->execute([':key' => $setting_key, ':exact_key' => $setting_key]);
        $rawLabels = $stmtLabel->fetchColumn();
        $time_labels = [];
        if ($rawLabels) {
            $time_labels = json_decode($rawLabels, true);
        }
        if (empty($time_labels) || !is_array($time_labels)) {
            $time_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
        }

        $sql_sess = "SELECT session_id, is_closed FROM dtc_inspection_sessions WHERE parameter_id = :pid AND inspection_date = :idate";
        $stmt_sess = $conn->prepare($sql_sess);
        $stmt_sess->execute([':pid' => $param_id, ':idate' => $date]);
        $session = $stmt_sess->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            if ($session['is_closed'] == 1 && !$is_admin) {
                echo json_encode(['status' => 'error', 'message' => 'Hari ini telah di-close. Hanya Admin yang dapat mengubah data.']);
                exit;
            }
            $session_id = $session['session_id'];
        } else {
            $sql_ins = "INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, remarks) VALUES (:pid, :idate, :uid, :rem)";
            $stmt_ins = $conn->prepare($sql_ins);
            $stmt_ins->execute([':pid' => $param_id, ':idate' => $date, ':uid' => $user_id, ':rem' => empty($remarks) ? null : $remarks]);
            $session_id = $conn->lastInsertId();
        }

        $samples_map = [];
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'sample_val_') === 0) {
                $idx = str_replace('sample_val_', '', $k);
                $lbl = $_POST['sample_label_' . $idx] ?? ('S' . $idx);
                $samples_map[$lbl] = $v;
            }
        }

        $stmt_spec = $conn->prepare("
            SELECT COALESCE(p.lsl, s.lsl) as lsl, COALESCE(p.usl, s.usl) as usl
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id
            WHERE p.parameter_id = :pid
        ");
        $stmt_spec->execute([':pid' => $param_id]);
        // Check if active running model creation timestamp restricts earlier slots
        $stmtPModel = $conn->prepare("
            SELECT COALESCE(p.model_name, spec.model_name) as model_name
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.parameter_id = :pid
        ");
        $stmtPModel->execute([':pid' => $param_id]);
        $pModelRow = $stmtPModel->fetch(PDO::FETCH_ASSOC);

        $rmCreatedAt = null;
        if ($pModelRow && !empty($pModelRow['model_name'])) {
            $stmtRM = $conn->prepare("
                SELECT created_at FROM dtc_running_models 
                WHERE target_month = :month 
                  AND UPPER(TRIM(model_name)) = UPPER(TRIM(:mname)) 
                  AND is_active = 1 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmtRM->execute([':month' => substr($date, 0, 7), ':mname' => $pModelRow['model_name']]);
            $rmCreatedAt = $stmtRM->fetchColumn() ?: null;
            if (!$rmCreatedAt) {
                $stmtPDate = $conn->prepare("SELECT created_at FROM dtc_master_parameters WHERE parameter_id = :pid");
                $stmtPDate->execute([':pid' => $param_id]);
                $rmCreatedAt = $stmtPDate->fetchColumn() ?: null;
            }
        }

        $createdMinsFrom7 = null;
        $cTimeDisplay = '';
        if ($rmCreatedAt) {
            $createdParts = explode(' ', trim($rmCreatedAt));
            $cDate = $createdParts[0] ?? '';
            $cTime = $createdParts[1] ?? '';

            if ($cDate === $date && !empty($cTime)) {
                $tp = explode(':', $cTime);
                $cH = (int)($tp[0] ?? 0);
                $cM = (int)($tp[1] ?? 0);
                if ($cH >= 7) {
                    $createdMinsFrom7 = ($cH - 7) * 60 + $cM;
                    $cTimeDisplay = substr($cTime, 0, 5);
                }
            }
        }

        $seq = 1;
        foreach ($samples_map as $lbl => $val) {
            if (isUnmeasuredValue($val)) continue;

            // Validasi Slot Sebelum Waktu Naiknya Running Model
            if ($createdMinsFrom7 !== null && preg_match('/^(\d{1,2})[:\.](\d{2})$/', $lbl, $sMatches)) {
                $sH = (int)$sMatches[1];
                $sM = (int)$sMatches[2];
                if ($sH < 7) $sH += 24;
                $slotMinsFrom7 = $sH * 60 + $sM;

                $idxInLabels = array_search($lbl, $time_labels);
                $nextSlotMinsFrom7 = null;
                if ($idxInLabels !== false && isset($time_labels[$idxInLabels + 1])) {
                    if (preg_match('/^(\d{1,2})[:\.](\d{2})$/', $time_labels[$idxInLabels + 1], $nMatches)) {
                        $nH = (int)$nMatches[1];
                        $nM = (int)$nMatches[2];
                        if ($nH < 7) $nH += 24;
                        $nextSlotMinsFrom7 = $nH * 60 + $nM;
                    }
                }
                if (!$nextSlotMinsFrom7) {
                    $nextSlotMinsFrom7 = $slotMinsFrom7 + 120;
                }

                if ($createdMinsFrom7 >= $nextSlotMinsFrom7 && !$is_admin) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Model '{$pModelRow['model_name']}' baru di-add pada jam {$cTimeDisplay}. Slot jam '{$lbl}' (sesi jam sebelumnya) tidak boleh diisi."
                    ]);
                    exit;
                }
            }

            // Validasi Future Time Slot (Berlaku untuk semua user termasuk Admin)
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $lbl, $matches)) {
                $hours = intval($matches[1]);
                $minutes = intval($matches[2]);
                $offsetDay = 0;
                if ($hours >= 24) {
                    $offsetDay = (int)floor($hours / 24);
                    $hours = $hours % 24;
                } else if ($hours < 7) {
                    $offsetDay = 1;
                }
                $tz = new DateTimeZone('Asia/Jakarta');
                $target_dt = new DateTime($date, $tz);
                if ($offsetDay > 0) {
                    $target_dt->modify("+$offsetDay days");
                }
                $target_dt->setTime($hours, $minutes, 0);
                if ($target_dt->getTimestamp() > time()) {
                    echo json_encode(['status' => 'error', 'message' => 'Belum masuk waktu pengisian untuk slot jam ' . $lbl . ' pada tanggal ' . $date]);
                    exit;
                }
            }

            // Validasi Kuantitatif: Pastikan berupa angka & dalam rentang wajar (-100,000 s/d 100,000)
            if (!is_numeric($val)) {
                echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Sample {$lbl} ('{$val}') harus berupa angka desimal yang valid."]);
                exit;
            }
            $val_num = (float)$val;
            if ($val_num < -100000 || $val_num > 100000) {
                echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Nilai sampel {$lbl} ({$val_num}) di luar batas fisik wajar (-100,000 s/d 100,000)."]);
                exit;
            }

            $sql_meas = "SELECT measurement_id, sample_value FROM dtc_measurements WHERE session_id = :sid AND checkpoint_id = :cpid AND sample_label = :lbl";
            $stmt_meas = $conn->prepare($sql_meas);
            $stmt_meas->execute([':sid' => $session_id, ':cpid' => $checkpoint_id, ':lbl' => $lbl]);
            $meas = $stmt_meas->fetch(PDO::FETCH_ASSOC);

            if ($meas) {
                if (!empty($meas['sample_value']) && !$is_admin) {
                    continue;
                }
                $stmt_upd = $conn->prepare("UPDATE dtc_measurements SET sample_value = :val, modified_by = :uid, modified_date = NOW() WHERE measurement_id = :mid");
                $stmt_upd->execute([':val' => $val, ':uid' => $user_id, ':mid' => $meas['measurement_id']]);
            } else {
                $stmt_ins_m = $conn->prepare("INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by) VALUES (:sid, :cpid, :seq, :lbl, :val, :uid)");
                $stmt_ins_m->execute([':sid' => $session_id, ':cpid' => $checkpoint_id, ':seq' => $seq, ':lbl' => $lbl, ':val' => $val, ':uid' => $user_id]);
            }
            $seq++;
        }

        echo json_encode(['status' => 'success', 'message' => 'Data sampel berhasil disimpan.']);
        exit;
    }

    // --- SINGLE ITEM SAVING MODE (Qualitative / Single Cell) ---
    $date = $_POST['date'] ?? '';
    $time_label = $_POST['time_label'] ?? '';
    $result = trim($_POST['result'] ?? '');
    $remarks = $_POST['remarks'] ?? '';

    if (!$param_id || !$checkpoint_id || !$date || !$time_label || $result === '') {
        echo json_encode(['status' => 'error', 'message' => 'Validasi Gagal: Field parameter_id, checkpoint_id, date, time_label, dan result wajib diisi.']);
        exit;
    }

    // Validasi Status Kualitatif (Hanya Boleh 'OK', 'NG', atau Kosong/CLEAR)
    $upper_res = strtoupper($result);
    if (!in_array($upper_res, ['OK', 'NG', 'CLEAR', ''])) {
        echo json_encode(['status' => 'error', 'message' => "Validasi Gagal: Status inspeksi kualitatif '{$result}' tidak valid. Hanya diizinkan OK atau NG."]);
        exit;
    }

    // Validation: Time slot future check
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $time_label, $matches)) {
        $hours = intval($matches[1]);
        $minutes = intval($matches[2]);
        $offsetDay = 0;

        if ($hours >= 24) {
            $offsetDay = (int)floor($hours / 24);
            $hours = $hours % 24;
        } else if ($hours < 7) {
            $offsetDay = 1;
        }

        $tz = new DateTimeZone('Asia/Jakarta');
        $target_dt = new DateTime($date, $tz);
        if ($offsetDay > 0) {
            $target_dt->modify("+$offsetDay days");
        }
        $target_dt->setTime($hours, $minutes, 0);

        if ($target_dt->getTimestamp() > time()) {
            echo json_encode(['status' => 'error', 'message' => 'Belum masuk waktu pengisian untuk slot jam ' . $time_label]);
            exit;
        }
    } else {
        $today = date('Y-m-d');
        if ($date > $today) {
            echo json_encode(['status' => 'error', 'message' => 'Belum masuk tanggal pengisian untuk hari tersebut.']);
            exit;
        }
    }
    
    // Check session
    $sql_sess = "SELECT session_id, is_closed FROM dtc_inspection_sessions WHERE parameter_id = :pid AND inspection_date = :idate";
    $stmt_sess = $conn->prepare($sql_sess);
    $stmt_sess->execute([':pid' => $param_id, ':idate' => $date]);
    $session = $stmt_sess->fetch(PDO::FETCH_ASSOC);
    
    $session_id = 0;
    
    if ($session) {
        if ($session['is_closed'] == 1 && !$is_admin) {
            echo json_encode(['status' => 'error', 'message' => 'Hari ini telah di-close. Hanya Admin yang dapat mengubah data.']);
            exit;
        }
        $session_id = $session['session_id'];
    } else {
        $sql_ins = "INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, remarks) VALUES (:pid, :idate, :uid, :rem)";
        $stmt_ins = $conn->prepare($sql_ins);
        $stmt_ins->execute([
            ':pid' => $param_id, 
            ':idate' => $date, 
            ':uid' => $user_id,
            ':rem' => empty($remarks) ? null : ($time_label . ': ' . $remarks)
        ]);
        $session_id = $conn->lastInsertId();
    }
    
    $sql_meas = "SELECT measurement_id, sample_value FROM dtc_measurements WHERE session_id = :sid AND checkpoint_id = :cpid AND sample_label = :lbl";
    $stmt_meas = $conn->prepare($sql_meas);
    $stmt_meas->execute([':sid' => $session_id, ':cpid' => $checkpoint_id, ':lbl' => $time_label]);
    $meas = $stmt_meas->fetch(PDO::FETCH_ASSOC);
    
    if ($meas) {
        if (!empty($meas['sample_value']) && !$is_admin) {
            echo json_encode(['status' => 'error', 'message' => 'Hanya Admin yang dapat mengubah data yang sudah diisi.']);
            exit;
        }
        
        $sql_upd_m = "UPDATE dtc_measurements SET sample_value = :val, modified_by = :uid, modified_date = NOW() WHERE measurement_id = :mid";
        $stmt_upd_m = $conn->prepare($sql_upd_m);
        $stmt_upd_m->execute([
            ':val' => $result,
            ':uid' => $user_id,
            ':mid' => $meas['measurement_id']
        ]);
    } else {
        $sql_seq = "SELECT MAX(sample_sequence) as mseq FROM dtc_measurements WHERE session_id = :sid";
        $stmt_seq = $conn->prepare($sql_seq);
        $stmt_seq->execute([':sid' => $session_id]);
        $seq_row = $stmt_seq->fetch(PDO::FETCH_ASSOC);
        $next_seq = ($seq_row['mseq'] ? (int)$seq_row['mseq'] + 1 : 1);
        
        $sql_ins_m = "INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by) VALUES (:sid, :cpid, :seq, :lbl, :val, :uid)";
        $stmt_ins_m = $conn->prepare($sql_ins_m);
        $stmt_ins_m->execute([
            ':sid' => $session_id,
            ':cpid' => $checkpoint_id,
            ':seq' => $next_seq,
            ':lbl' => $time_label,
            ':val' => $result,
            ':uid' => $user_id
        ]);
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Data saved successfully']);

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

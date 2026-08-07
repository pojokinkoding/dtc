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
        $spec_info = $stmt_spec->fetch(PDO::FETCH_ASSOC);

        $seq = 1;
        $seq = 1;
        foreach ($samples_map as $lbl => $val) {
            if ($val === '' || $val === null) continue;

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

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

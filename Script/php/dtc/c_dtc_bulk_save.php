<?php
// Script/php/dtc/c_dtc_bulk_save.php
// Bulk save endpoint for measurements of all checkpoints and measuring items of a running model.
require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan.']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);

    $inspection_date = trim($_POST['inspection_date'] ?? $jsonData['inspection_date'] ?? date('Y-m-d'));
    $time_label = trim($_POST['time_label'] ?? $jsonData['time_label'] ?? '');
    $model_name = trim($_POST['model_name'] ?? $jsonData['model_name'] ?? '');
    $items = $_POST['items'] ?? $jsonData['items'] ?? [];

    $current_month = date('Y-m');
    if (!empty($inspection_date) && substr($inspection_date, 0, 7) < $current_month) {
        echo json_encode(['status' => 'error', 'message' => "Data periode bulan lalu terkunci total dan tidak dapat diubah atau diisi."]);
        exit;
    }

    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    if ($user_id) {
        $check_op = $conn->prepare("SELECT user_id FROM dtc_users WHERE user_id = ?");
        $check_op->execute([$user_id]);
        if (!$check_op->fetchColumn()) {
            $user_id = null;
        }
    }
    if (!$user_id) {
        $user_id = $conn->query("SELECT user_id FROM dtc_users ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;
    }

    $user_role = strtolower(trim($_SESSION['role'] ?? ''));
    $is_admin = ($user_role === 'admin');

    if (empty($inspection_date) || empty($time_label)) {
        echo json_encode(['status' => 'error', 'message' => 'Tanggal inspeksi dan slot jam wajib diisi.']);
        exit;
    }

    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada data pengukuran yang dikirim.']);
        exit;
    }

    // 1. Validasi Future Time Slot
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
        $target_dt = new DateTime($inspection_date, $tz);
        if ($offsetDay > 0) {
            $target_dt->modify("+$offsetDay days");
        }
        $target_dt->setTime($hours, $minutes, 0);

        if ($target_dt->getTimestamp() > time()) {
            echo json_encode([
                'status' => 'error', 
                'message' => "Belum masuk waktu pengisian untuk slot jam '$time_label' pada tanggal $inspection_date."
            ]);
            exit;
        }
    } else {
        $today = date('Y-m-d');
        if ($inspection_date > $today) {
            echo json_encode([
                'status' => 'error', 
                'message' => "Belum masuk tanggal pengisian untuk hari tersebut ($inspection_date)."
            ]);
            exit;
        }
    }

    // 1b. Validasi Slot Sebelum Waktu Pembuatan/Aktivasi Running Model
    if (!empty($model_name)) {
        $stmtCheckRM = $conn->prepare("
            SELECT created_at FROM dtc_running_models 
            WHERE target_month = :m AND UPPER(TRIM(model_name)) = UPPER(TRIM(:mname)) AND is_active = 1 
            LIMIT 1
        ");
        $stmtCheckRM->execute([':m' => substr($inspection_date, 0, 7), ':mname' => $model_name]);
        $rmCreatedAt = $stmtCheckRM->fetchColumn();

        if ($rmCreatedAt) {
            $createdParts = explode(' ', trim($rmCreatedAt));
            $cDate = $createdParts[0] ?? '';
            $cTime = $createdParts[1] ?? '';

            if ($cDate === $inspection_date && !empty($cTime)) {
                $tp = explode(':', $cTime);
                $cH = (int)($tp[0] ?? 0);
                $cM = (int)($tp[1] ?? 0);
                if ($cH >= 7) {
                    $createdMinsFrom7 = ($cH - 7) * 60 + $cM;

                    if (preg_match('/^(\d{1,2}):(\d{2})$/', $time_label, $sMatches)) {
                        $sH = (int)$sMatches[1];
                        $sM = (int)$sMatches[2];
                        if ($sH >= 24) $sH -= 24;
                        $sHShift = $sH < 7 ? $sH + 24 : $sH;
                        $slotMinsFrom7 = ($sHShift - 7) * 60 + $sM;

                        $defaultTimeLabels = ['07:30','09:40','12:40','14:40','16:40','18:40','20:05','22:30','24:30','02:30','04:30'];
                        $idxInLabels = array_search($time_label, $defaultTimeLabels);
                        $nextSlotMinsFrom7 = null;
                        if ($idxInLabels !== false && isset($defaultTimeLabels[$idxInLabels + 1])) {
                            if (preg_match('/^(\d{1,2}):(\d{2})$/', $defaultTimeLabels[$idxInLabels + 1], $nMatches)) {
                                $nH = (int)$nMatches[1];
                                $nM = (int)$nMatches[2];
                                if ($nH >= 24) $nH -= 24;
                                $nHShift = $nH < 7 ? $nH + 24 : $nH;
                                $nextSlotMinsFrom7 = ($nHShift - 7) * 60 + $nM;
                            }
                        }
                        if (!$nextSlotMinsFrom7) {
                            $nextSlotMinsFrom7 = $slotMinsFrom7 + 120;
                        }

                        if ($createdMinsFrom7 >= $nextSlotMinsFrom7 && !$is_admin) {
                            $timeDisplay = substr($cTime, 0, 5);
                            echo json_encode([
                                'status' => 'error',
                                'message' => "Model '$model_name' baru di-add pada jam $timeDisplay. Slot jam '$time_label' (jam sebelumnya) tidak perlu diisi."
                            ]);
                            exit;
                        }
                    }
                }
            }
        }
    }

    if (!function_exists('isUnmeasuredValue')) {
        function isUnmeasuredValue($val) {
            return ($val === '' || $val === null || $val === '-');
        }
    }

    // 2. Pre-validate values
    $validCount = 0;
    foreach ($items as $idx => $item) {
        $val = trim((string)($item['value'] ?? ''));
        $ctype = $item['checkpoint_type'] ?? 'Quantitative';

        if (isUnmeasuredValue($val)) continue;

        $validCount++;
        $itemLabel = $item['name'] ?? ("Item #" . ($idx + 1));

        if ($val === '__DELETE__') {
            // Bypass validation for delete command
        } else if (strcasecmp($ctype, 'Quantitative') === 0) {
            if (!is_numeric($val)) {
                echo json_encode([
                    'status' => 'error', 
                    'message' => "Validasi Gagal: Nilai untuk '$itemLabel' ('$val') harus berupa angka."
                ]);
                exit;
            }
            $val_num = (float)$val;
            if ($val_num < -100000 || $val_num > 100000) {
                echo json_encode([
                    'status' => 'error', 
                    'message' => "Validasi Gagal: Nilai '$itemLabel' ($val_num) di luar batas fisik wajar (-100,000 s/d 100,000)."
                ]);
                exit;
            }
        } else {
            $upperVal = strtoupper($val);
            if (!in_array($upperVal, ['OK', 'NG', 'CLEAR', ''])) {
                echo json_encode([
                    'status' => 'error', 
                    'message' => "Validasi Gagal: Status kualitatif untuk '$itemLabel' ('$val') tidak valid. Harus OK atau NG."
                ]);
                exit;
            }
        }
    }

    if ($validCount === 0) {
        echo json_encode([
            'status' => 'warning', 
            'message' => 'Tidak ada nilai pengukuran yang diisi. Harap isi minimal 1 item.'
        ]);
        exit;
    }

    $conn->beginTransaction();

    $savedCount = 0;
    $updatedCount = 0;
    $sessionCache = []; // param_id => session_id

    foreach ($items as $item) {
        $param_id = intval($item['parameter_id'] ?? 0);
        $checkpoint_id = intval($item['checkpoint_id'] ?? 0);
        $val = trim((string)($item['value'] ?? ''));
        $remarks = trim((string)($item['remarks'] ?? ''));

        if (!$param_id || isUnmeasuredValue($val)) continue;

        // Fetch or create session for this parameter_id & inspection_date
        if (!isset($sessionCache[$param_id])) {
            // Verify IP & User Section Access permissions
            if (!$is_admin) {
                $sqlAccess = "SELECT p.parameter_id FROM dtc_master_parameters p LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id WHERE p.parameter_id = :pid " . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)');
                $stmtAccess = $conn->prepare($sqlAccess);
                $stmtAccess->execute([':pid' => $param_id]);
                if (!$stmtAccess->fetch()) {
                    throw new Exception("Akses Ditolak: Alamat IP komputer atau wewenang Section akun Anda tidak diizinkan untuk menyimpan data pada parameter ID $param_id.");
                }
            }

            $sql_sess = "SELECT session_id, is_closed FROM dtc_inspection_sessions WHERE parameter_id = :pid AND DATE(inspection_date) = :idate AND is_active = 1";
            $stmt_sess = $conn->prepare($sql_sess);
            $stmt_sess->execute([':pid' => $param_id, ':idate' => $inspection_date]);
            $session = $stmt_sess->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                if ($session['is_closed'] == 1 && !$is_admin) {
                    throw new Exception("Inspeksi untuk parameter ID $param_id pada tanggal $inspection_date telah dikunci (closed).");
                }
                $session_id = (int)$session['session_id'];
            } else {
                $sql_ins = "INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, remarks, is_active) VALUES (:pid, :idate, :uid, :rem, 1)";
                $stmt_ins = $conn->prepare($sql_ins);
                $stmt_ins->execute([
                    ':pid' => $param_id, 
                    ':idate' => $inspection_date, 
                    ':uid' => $user_id, 
                    ':rem' => !empty($remarks) ? ($time_label . ': ' . $remarks) : null
                ]);
                $session_id = (int)$conn->lastInsertId();
            }
            $sessionCache[$param_id] = $session_id;
        } else {
            $session_id = $sessionCache[$param_id];
        }

        $itemSlot = trim((string)($item['sample_label'] ?? $time_label));
        if (empty($itemSlot)) $itemSlot = $time_label;

        // Check existing measurement for session_id, checkpoint_id, sample_label
        $cpIdDb = $checkpoint_id > 0 ? $checkpoint_id : null;

        if ($cpIdDb !== null) {
            $sql_meas = "SELECT measurement_id, sample_value FROM dtc_measurements WHERE session_id = :sid AND checkpoint_id = :cpid AND sample_label = :lbl";
            $stmt_meas = $conn->prepare($sql_meas);
            $stmt_meas->execute([':sid' => $session_id, ':cpid' => $cpIdDb, ':lbl' => $itemSlot]);
        } else {
            $sql_meas = "SELECT measurement_id, sample_value FROM dtc_measurements WHERE session_id = :sid AND checkpoint_id IS NULL AND sample_label = :lbl";
            $stmt_meas = $conn->prepare($sql_meas);
            $stmt_meas->execute([':sid' => $session_id, ':lbl' => $itemSlot]);
        }
        $existingMeas = $stmt_meas->fetch(PDO::FETCH_ASSOC);

        if ($existingMeas) {
            $oldVal = trim((string)($existingMeas['sample_value'] ?? ''));
            
            if ($val === '__DELETE__') {
                $sql_del = "DELETE FROM dtc_measurements WHERE measurement_id = :mid";
                $stmt_del = $conn->prepare($sql_del);
                $stmt_del->execute([':mid' => $existingMeas['measurement_id']]);
                $updatedCount++;
                continue;
            }

            if ($oldVal !== $val) {
                $sql_upd = "UPDATE dtc_measurements SET sample_value = :val, modified_by = :uid, modified_date = NOW() WHERE measurement_id = :mid";
                $stmt_upd = $conn->prepare($sql_upd);
                $stmt_upd->execute([':val' => $val, ':uid' => $user_id, ':mid' => $existingMeas['measurement_id']]);
                $updatedCount++;
            }
        } else {
            if ($val === '__DELETE__') continue;
            
            // Determine next sample sequence
            $sql_seq = "SELECT COALESCE(MAX(sample_sequence), 0) + 1 FROM dtc_measurements WHERE session_id = :sid";
            $stmt_seq = $conn->prepare($sql_seq);
            $stmt_seq->execute([':sid' => $session_id]);
            $next_seq = (int)$stmt_seq->fetchColumn();

            $sql_ins_m = "INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by) VALUES (:sid, :cpid, :seq, :lbl, :val, :uid)";
            $stmt_ins_m = $conn->prepare($sql_ins_m);
            $stmt_ins_m->execute([
                ':sid' => $session_id,
                ':cpid' => $cpIdDb,
                ':seq' => $next_seq,
                ':lbl' => $itemSlot,
                ':val' => $val,
                ':uid' => $user_id
            ]);
            $savedCount++;
        }

        // If remarks provided, update session remarks
        if (!empty($remarks)) {
            $stmtRem = $conn->prepare("UPDATE dtc_inspection_sessions SET remarks = CONCAT(COALESCE(remarks, ''), '; ', :rem) WHERE session_id = :sid");
            $stmtRem->execute([':rem' => "$time_label: $remarks", ':sid' => $session_id]);
        }
    }

    $conn->commit();

    $totalProcessed = $savedCount + $updatedCount;
    $modelText = (!empty($model_name) && strtoupper($model_name) !== 'ALL') ? "Model '$model_name'" : "seluruh Running Model";
    $slotText = (!empty($time_label) && strtoupper($time_label) !== 'ALL') ? "slot $time_label" : "seluruh slot jam";
    $msg = "Berhasil menyimpan $totalProcessed data pengukuran untuk $modelText pada $slotText ($inspection_date).";
    if ($updatedCount > 0) {
        $msg .= " ($savedCount baru, $updatedCount diperbarui)";
    }

    echo json_encode([
        'status' => 'success',
        'message' => $msg,
        'saved_count' => $savedCount,
        'updated_count' => $updatedCount
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

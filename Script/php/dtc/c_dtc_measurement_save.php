<?php
// Script/php/dtc/c_dtc_measurement_save.php
ob_start();
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    $conn = getDBConnection();
    
    // Retrieve inputs
    $param_id = isset($_POST['parameter_id']) ? intval($_POST['parameter_id']) : (isset($_POST['spec_id']) ? intval($_POST['spec_id']) : 0);
    $inspection_date = isset($_POST['inspection_date']) ? $_POST['inspection_date'] : '';
    
    $current_month = date('Y-m');
    if (!empty($inspection_date) && substr($inspection_date, 0, 7) < $current_month) {
        echo json_encode(['status' => 'error', 'message' => "Data periode bulan lalu terkunci total dan tidak dapat diubah atau diisi."]);
        exit;
    }
    
    $operator_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    
    // Validate that the user_id exists in the database
    if ($operator_id) {
        $check_op = $conn->prepare("SELECT user_id FROM dtc_users WHERE user_id = ?");
        $check_op->execute([$operator_id]);
        if (!$check_op->fetchColumn()) {
            $operator_id = null;
        }
    }

    if (!$operator_id) {
        $operator_id = $conn->query("SELECT user_id FROM dtc_users ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;
    }
    
    // Retrieve samples 1-N dynamically (UI still sends sample_1 .. sample_N in horizontal grid)
    $sample_inputs = [];
    
    // Determine Line Name to fetch correct labels
    $stmtLine = $conn->prepare("
        SELECT spec.line_name 
        FROM dtc_master_dtc_specs spec
        JOIN dtc_master_parameters p ON spec.spec_id = p.spec_id 
        WHERE p.parameter_id = :pid
    ");
    $stmtLine->execute([':pid' => $param_id]);
    $line_name = $stmtLine->fetchColumn() ?: 'REF 01';
    
    $setting_key = 'time_matrix_labels_' . $line_name;
    $stmtSetting = $conn->prepare("SELECT setting_value FROM dtc_app_settings WHERE setting_key = :key");
    $stmtSetting->execute([':key' => $setting_key]);
    $rowSetting = $stmtSetting->fetch(PDO::FETCH_ASSOC);
    $time_labels = [];
    if ($rowSetting && $rowSetting['setting_value']) {
        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
        $time_labels = json_decode($val, true);
    }
    if (empty($time_labels)) {
        $time_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30'];
    }
    
    // Check for existing labels across the entire parameter to lock in the pattern
    $stmtExistingLabels = $conn->prepare("
        SELECT m.sample_sequence, m.sample_label 
        FROM dtc_measurements m 
        JOIN dtc_inspection_sessions s ON m.session_id = s.session_id 
        WHERE s.parameter_id = :pid AND s.is_active = 1
        ORDER BY s.inspection_date ASC, m.measurement_id ASC
    ");
    $stmtExistingLabels->execute([':pid' => $param_id]);
    $existing_labels = [];
    while ($r = $stmtExistingLabels->fetch(PDO::FETCH_ASSOC)) {
        $seq = intval($r['sample_sequence']);
        $lbl = trim($r['sample_label'] ?? '');
        if ($lbl && strtolower($lbl) !== 'null' && !isset($existing_labels[$seq])) {
            $existing_labels[$seq] = $lbl;
        }
    }
    
    // Merge existing labels to ensure newly inserted data uses the locked pattern
    $max_slots = count($time_labels);
    for ($i = 0; $i < $max_slots; $i++) {
        if (isset($existing_labels[$i + 1])) {
            $time_labels[$i] = $existing_labels[$i + 1];
        }
    }
    
    for ($i = 1; $i <= $max_slots; $i++) {
        $raw_val = isset($_POST["sample_$i"]) ? trim($_POST["sample_$i"]) : '';
        $sample_inputs["sample_$i"] = $raw_val;
        
        // Backend validation for future time
        if ($raw_val !== '') {
            $label = isset($time_labels[$i - 1]) ? trim($time_labels[$i - 1]) : '';
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $label, $matches)) {
                $hours = intval($matches[1]);
                $minutes = intval($matches[2]);
                $offsetDay = 0;
                
                if ($hours >= 24) {
                    $offsetDay = floor($hours / 24);
                    $hours = $hours % 24;
                } else if ($hours < 7) {
                    // Times before 07:00 belong to the next day relative to shift date
                    $offsetDay = 1;
                }
                
                $tz = new DateTimeZone('Asia/Jakarta');
                $dateObj = new DateTime($inspection_date, $tz);
                if ($offsetDay > 0) {
                    $dateObj->modify("+$offsetDay days");
                }
                $dateObj->setTime($hours, $minutes, 0);
                
                if ($dateObj->getTimestamp() > time()) {
                    throw new Exception("Waktu Input Belum Masuk! Anda tidak dapat menginput data untuk jadwal $label karena waktu saat ini belum mencapainya.");
                }
            }
        }
    }
    
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    if (!$param_id || !$inspection_date) {
        throw new Exception("Missing required fields (parameter_id, inspection_date).");
    }
    
    // Validate that at least one measurement is provided
    $has_data = false;
    foreach ($sample_inputs as $val) {
        if ($val !== '') {
            $has_data = true;
            break;
        }
    }
    if (!$has_data) {
        throw new Exception("Minimal harus ada satu data pengukuran yang diisi.");
    }
    
    // Get Specs via parameter_id
    $sql_spec = "SELECT spec.lsl, spec.usl 
                 FROM dtc_master_dtc_specs spec
                 JOIN dtc_master_parameters p ON spec.spec_id = p.spec_id 
                 WHERE p.parameter_id = :param_id";
    $stmt_spec = $conn->prepare($sql_spec);
    $stmt_spec->execute([':param_id' => $param_id]);
    $spec = $stmt_spec->fetch(PDO::FETCH_ASSOC);
    
    if (!$spec) {
        throw new Exception("Spec not found for parameter.");
    }
    
    // Block Saturday (6) and Sunday (0) logic removed per user request
    
    $lsl = floatval($spec['lsl']);
    $usl = floatval($spec['usl']);
    
    // Mathematical Calculations (Only for numeric samples)
    $numeric_samples = [];
    foreach ($sample_inputs as $val) {
        if ($val !== '' && is_numeric($val)) {
            $numeric_samples[] = floatval($val);
        }
    }
    
    $n = count($numeric_samples);
    $max_val = null;
    $min_val = null;
    $x_bar = null;
    $range_val = null;
    $std_dev = null;
    
    if ($n > 0) {
        $max_val = max($numeric_samples);
        $min_val = min($numeric_samples);
        
        $sum = array_sum($numeric_samples);
        $x_bar = $sum / $n;
        
        $range_val = $max_val - $min_val;
        
        $std_dev = 0;
        if ($n > 1) {
            $sum_sq = 0;
            foreach($numeric_samples as $val) {
                $sum_sq += pow(($val - $x_bar), 2);
            }
            $std_dev = sqrt($sum_sq / ($n - 1));
        }
    }
    
    // Check if session exists
    $sql_check = "SELECT session_id, is_closed FROM dtc_inspection_sessions 
                  WHERE parameter_id = :param_id 
                  AND DATE(inspection_date) = :idate";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([':param_id' => $param_id, ':idate' => $inspection_date]);
    $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    $isAdmin = (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin');
    
    if ($existing && $existing['is_closed'] == 1 && !$isAdmin) {
        throw new Exception("This measurement has been closed and cannot be edited.");
    }
    
    $conn->beginTransaction();
    
    if ($existing) {
        $session_id = $existing['session_id'];
        
        // Update Session
        $sql_upd_s = "UPDATE dtc_inspection_sessions 
                      SET remarks = :remarks, is_active = 1,
                          max_value = :mx, min_value = :mn, x_bar = :xb, range_value = :rng, std_dev = :std
                      WHERE session_id = :sid";
        $stmt_upd_s = $conn->prepare($sql_upd_s);
        $stmt_upd_s->execute([
            ':remarks' => $remarks,
            ':mx' => $max_val, ':mn' => $min_val, ':xb' => $x_bar, ':rng' => $range_val, ':std' => $std_dev,
            ':sid' => $session_id
        ]);
        
        // For Vertical Updates: Delete existing and re-insert
        $stmt_del = $conn->prepare("DELETE FROM dtc_measurements WHERE session_id = :sid");
        $stmt_del->execute([':sid' => $session_id]);
        
        $sql_ins_m = "INSERT INTO dtc_measurements (session_id, sample_sequence, sample_label, sample_value, created_by, modified_by, modified_date)
                      VALUES (:sid, :seq, :lbl, :val, :cb, :mb, CURRENT_TIMESTAMP)";
        $stmt_ins_m = $conn->prepare($sql_ins_m);
        
        $seq = 1;
        foreach ($sample_inputs as $key => $raw_val) {
            if ($raw_val !== '') { // Only insert if not empty
                $stmt_ins_m->execute([
                    ':sid' => $session_id,
                    ':seq' => $seq,
                    ':lbl' => isset($time_labels[$seq - 1]) ? $time_labels[$seq - 1] : "Sample $seq",
                    ':val' => (string)$raw_val,
                    ':cb' => $operator_id,
                    ':mb' => $operator_id
                ]);
            }
            $seq++;
        }
        
        $msg = "Data successfully updated for $inspection_date.";
    } else {
        // Insert Session
        $sql_ins_s = "INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, remarks, is_active, max_value, min_value, x_bar, range_value, std_dev)
                      VALUES (:param_id, STR_TO_DATE(:idate, '%Y-%m-%d'), :op_id, :remarks, 1, :mx, :mn, :xb, :rng, :std)";
        $stmt_ins_s = $conn->prepare($sql_ins_s);
        $stmt_ins_s->execute([
            ':param_id' => $param_id,
            ':idate' => $inspection_date,
            ':op_id' => $operator_id,
            ':remarks' => $remarks,
            ':mx' => $max_val, ':mn' => $min_val, ':xb' => $x_bar, ':rng' => $range_val, ':std' => $std_dev
        ]);
        
        // Fetch new session ID
        $stmt_get_sid = $conn->prepare("SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id = :param_id AND DATE(inspection_date) = :idate ORDER BY session_id DESC");
        $stmt_get_sid->execute([':param_id' => $param_id, ':idate' => $inspection_date]);
        $session_id = $stmt_get_sid->fetchColumn();
        
        // Insert Measurements vertically
        $sql_ins_m = "INSERT INTO dtc_measurements (session_id, sample_sequence, sample_label, sample_value, created_by)
                      VALUES (:sid, :seq, :lbl, :val, :cb)";
        $stmt_ins_m = $conn->prepare($sql_ins_m);
        
        $seq = 1;
        foreach ($sample_inputs as $key => $raw_val) {
            if ($raw_val !== '') {
                $stmt_ins_m->execute([
                    ':sid' => $session_id,
                    ':seq' => $seq,
                    ':lbl' => isset($time_labels[$seq - 1]) ? $time_labels[$seq - 1] : "Sample $seq",
                    ':val' => (string)$raw_val,
                    ':cb' => $operator_id
                ]);
            }
            $seq++;
        }
        
        $msg = "New data successfully saved for $inspection_date.";
    }
    
    $conn->commit();
    ob_clean();
    echo json_encode(["status" => "success", "message" => $msg]);

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    ob_clean();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

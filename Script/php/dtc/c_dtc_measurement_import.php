<?php
// Script/php/dtc/c_dtc_measurement_import.php
ob_start();
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }
    
    if (!isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'admin') {
        throw new Exception("Unauthorized access. Only Admin can import data.");
    }

    $inputJSON = file_get_contents('php://input');
    $payload = json_decode($inputJSON, true);

    if (!$payload || !isset($payload['spec_id']) || !isset($payload['target_month']) || !isset($payload['rows'])) {
        throw new Exception("Invalid JSON payload.");
    }

    $spec_id = intval($payload['spec_id']);
    $target_month = $payload['target_month'];
    $rows = $payload['rows'];
    $operator_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $conn = getDBConnection();
    
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
    $conn->beginTransaction();

    $is_custom = isset($payload['is_custom']) ? $payload['is_custom'] : false;
    $custom_spec = isset($payload['custom_spec']) ? $payload['custom_spec'] : null;

    if ($is_custom && $custom_spec) {
        $sql_ins_spec = "INSERT INTO dtc_master_dtc_specs (
                    model_name, item_check_name, sub_item_check_name, data_type, line_name, section_name, process_name, measuring_item, 
                    lsl, usl, target_value, uom, target_zst, target_zlt
                ) VALUES (
                    :model_name, :item_check_name, :sub_item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item,
                    :lsl, :usl, :target_value, :uom, 4.0, 3.0
                )";
        $stmt_ins_spec = $conn->prepare($sql_ins_spec);
        $stmt_ins_spec->execute([
            ':model_name' => $custom_spec['model_name'] ?? '-',
            ':item_check_name' => $custom_spec['item_check_name'] ?? '',
            ':sub_item_check_name' => $custom_spec['sub_item_check_name'] ?? '',
            ':data_type' => $custom_spec['data_type'] ?? '',
            ':line_name' => $custom_spec['line_name'] ?? '',
            ':section_name' => $custom_spec['section_name'] ?? '',
            ':process_name' => $custom_spec['process_name'] ?? '',
            ':measuring_item' => $custom_spec['measuring_item'] ?? 'CTQ',
            ':lsl' => isset($custom_spec['lsl']) && $custom_spec['lsl'] !== '' ? floatval($custom_spec['lsl']) : 0,
            ':usl' => isset($custom_spec['usl']) && $custom_spec['usl'] !== '' ? floatval($custom_spec['usl']) : 0,
            ':target_value' => isset($custom_spec['target_value']) && $custom_spec['target_value'] !== '' ? floatval($custom_spec['target_value']) : 0,
            ':uom' => $custom_spec['uom'] ?? ''
        ]);
        $spec_id = $conn->lastInsertId();
    } else {
        if ($spec_id <= 0) {
            throw new Exception("Invalid Specification ID.");
        }
    }

    // 1. Prepare Spec Details
    $sql_spec_info = "SELECT * FROM dtc_master_dtc_specs WHERE spec_id = :spec_id";
    $stmt_spec_info = $conn->prepare($sql_spec_info);
    $stmt_spec_info->execute([':spec_id' => $spec_id]);
    $spec_info = $stmt_spec_info->fetch(PDO::FETCH_ASSOC);

    if (!$spec_info) {
        throw new Exception("Master Spec not found.");
    }
    
    $lsl = floatval($spec_info['lsl']);
    $usl = floatval($spec_info['usl']);

    // 2. Prepare Global Time Labels
    $stmtSetting = $conn->prepare("SELECT setting_value FROM dtc_app_settings WHERE setting_key = 'time_matrix_labels'");
    $stmtSetting->execute();
    $rowSetting = $stmtSetting->fetch(PDO::FETCH_ASSOC);
    $global_time_labels = [];
    if ($rowSetting && $rowSetting['setting_value']) {
        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
        $global_time_labels = json_decode($val, true);
    }
    if (empty($global_time_labels)) {
        $global_time_labels = ['07:30', '09:40', '12:40', '14:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
    }

    // Cache structure: $param_cache[$row_month] = ['param_id' => X, 'time_labels' => [...]];
    $param_cache = [];


    $imported_count = 0;

    foreach ($rows as $row) {
        $inspection_date = trim($row['date']);
        if (!$inspection_date) continue;

        $row_month = substr($inspection_date, 0, 7);
        if (!$row_month) continue;

        // Resolve parameter for this row_month
        if (!isset($param_cache[$row_month])) {
            $sql_check_param = "SELECT parameter_id FROM dtc_master_parameters WHERE spec_id = :spec_id AND target_month = :target_month";
            $stmt_check_param = $conn->prepare($sql_check_param);
            $stmt_check_param->execute([':spec_id' => $spec_id, ':target_month' => $row_month]);
            $param_id = $stmt_check_param->fetchColumn();

            if (!$param_id) {
                $sql_ins_param = "INSERT INTO dtc_master_parameters 
                        (spec_id, target_month, item_check_name, sub_item_check_name, data_type, line_name, section_name, process_name, measuring_item, target_zst, target_zlt) 
                        VALUES (:spec_id, :target_month, :item_check_name, :sub_item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item, :target_zst, :target_zlt)";
                $stmt_ins_param = $conn->prepare($sql_ins_param);
                $stmt_ins_param->execute([
                    ':spec_id' => $spec_id,
                    ':target_month' => $row_month,
                    ':item_check_name' => $spec_info['item_check_name'],
                    ':sub_item_check_name' => $spec_info['sub_item_check_name'],
                    ':data_type' => $spec_info['data_type'],
                    ':line_name' => $spec_info['line_name'],
                    ':section_name' => $spec_info['section_name'],
                    ':process_name' => $spec_info['process_name'],
                    ':measuring_item' => $spec_info['measuring_item'],
                    ':target_zst' => $spec_info['target_zst'],
                    ':target_zlt' => $spec_info['target_zlt']
                ]);
                $param_id = $conn->lastInsertId();
            }

            // Get existing time labels for this param
            $time_labels = $global_time_labels;
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
            for ($i = 0; $i < 10; $i++) {
                if (isset($existing_labels[$i + 1])) {
                    $time_labels[$i] = $existing_labels[$i + 1];
                }
            }

            $param_cache[$row_month] = [
                'param_id' => $param_id,
                'time_labels' => $time_labels
            ];
        }

        $param_id = $param_cache[$row_month]['param_id'];
        $time_labels = $param_cache[$row_month]['time_labels'];


        $sample_inputs = $row['samples']; // array of 10
        $remarks = trim($row['remarks'] ?? '');
        $is_closed = isset($row['is_closed']) ? intval($row['is_closed']) : 0;

        $numeric_samples = [];
        foreach ($sample_inputs as $val) {
            $val = trim((string)$val);
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

        // Check existing session
        $sql_check = "SELECT session_id, is_closed FROM dtc_inspection_sessions 
                      WHERE parameter_id = :param_id 
                      AND DATE(inspection_date) = :idate";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([':param_id' => $param_id, ':idate' => $inspection_date]);
        $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existing && $existing['is_closed'] == 1) {
            continue; // Skip closed sessions instead of throwing error for bulk
        }

        if ($existing) {
            $session_id = $existing['session_id'];
            $sql_upd_s = "UPDATE dtc_inspection_sessions 
                          SET remarks = :remarks, is_active = 1,
                              max_value = :mx, min_value = :mn, x_bar = :xb, range_value = :rng, std_dev = :std, is_closed = :is_closed
                          WHERE session_id = :sid";
            $stmt_upd_s = $conn->prepare($sql_upd_s);
            $stmt_upd_s->execute([
                ':remarks' => $remarks,
                ':mx' => $max_val, ':mn' => $min_val, ':xb' => $x_bar, ':rng' => $range_val, ':std' => $std_dev,
                ':is_closed' => $is_closed,
                ':sid' => $session_id
            ]);

            $stmt_del = $conn->prepare("DELETE FROM dtc_measurements WHERE session_id = :sid");
            $stmt_del->execute([':sid' => $session_id]);
        } else {
            $sql_ins_s = "INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, remarks, is_active, max_value, min_value, x_bar, range_value, std_dev, is_closed)
                          VALUES (:param_id, STR_TO_DATE(:idate, '%Y-%m-%d'), :op_id, :remarks, 1, :mx, :mn, :xb, :rng, :std, :is_closed)";
            $stmt_ins_s = $conn->prepare($sql_ins_s);
            $stmt_ins_s->execute([
                ':param_id' => $param_id,
                ':idate' => $inspection_date,
                ':op_id' => $operator_id,
                ':remarks' => $remarks,
                ':mx' => $max_val, ':mn' => $min_val, ':xb' => $x_bar, ':rng' => $range_val, ':std' => $std_dev,
                ':is_closed' => $is_closed
            ]);
            $session_id = $conn->lastInsertId();
        }

        $sql_ins_m = "INSERT INTO dtc_measurements (session_id, sample_sequence, sample_label, sample_value, created_by, modified_by, modified_date)
                      VALUES (:sid, :seq, :lbl, :val, :cb, :mb, CURRENT_TIMESTAMP)";
        $stmt_ins_m = $conn->prepare($sql_ins_m);

        $seq = 1;
        for ($i = 0; $i < 10; $i++) {
            $raw_val = trim((string)($sample_inputs[$i] ?? ''));
            if ($raw_val !== '') {
                $stmt_ins_m->execute([
                    ':sid' => $session_id,
                    ':seq' => $seq,
                    ':lbl' => isset($time_labels[$seq - 1]) ? $time_labels[$seq - 1] : "Sample $seq",
                    ':val' => $raw_val,
                    ':cb' => $operator_id,
                    ':mb' => $operator_id
                ]);
            }
            $seq++;
        }
        $imported_count++;
    }

    $conn->commit();
    ob_clean();
    echo json_encode(["status" => "success", "message" => "Successfully imported $imported_count rows of data."]);

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    ob_clean();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

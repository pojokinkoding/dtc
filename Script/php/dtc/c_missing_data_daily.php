<?php
// c_missing_data_daily.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$month = date('Y-m', strtotime($date));

$timestamp = strtotime($date);
$dayOfWeek = date('N', $timestamp);
$is_weekend = ($dayOfWeek == 6 || $dayOfWeek == 7) ? true : false;

try {
    $conn = getDBConnection();
    
    // 0. Fetch active running models for this month filtered by IP & User Section
    $sqlRM = "
        SELECT line_name, section_name, model_name 
        FROM dtc_running_models 
        WHERE target_month = :month AND is_active = 1
        " . getIPAccessFilterSQL('line_name', 'section_name') . "
        " . getUserAccessFilterSQL('line_name', 'section_name') . "
    ";
    $stmtRM = $conn->prepare($sqlRM);
    $stmtRM->execute([':month' => $month]);
    $activeRMs = $stmtRM->fetchAll(PDO::FETCH_ASSOC);

    $runningSet = [];
    foreach ($activeRMs as $rm) {
        $k = strtolower(trim($rm['line_name'])) . '|' . strtolower(trim($rm['section_name'])) . '|' . strtolower(trim($rm['model_name']));
        $runningSet[$k] = true;
    }

    // 1. Fetch all active parameters for the month filtered by IP & User Section
    $sqlParams = "
        SELECT p.parameter_id, p.target_month,
               COALESCE(p.model_name, spec.model_name) as model_name,
               COALESCE(p.item_check_name, spec.item_check_name) as item_check_name,
               COALESCE(p.sub_item_check_name, spec.sub_item_check_name) as sub_item_check_name,
               COALESCE(p.data_type, spec.data_type) as data_type,
               COALESCE(p.section_name, spec.section_name) as section_name,
               COALESCE(p.line_name, spec.line_name) as line_name,
               COALESCE(p.process_name, spec.process_name) as process_name,
               COALESCE(p.measuring_item, spec.measuring_item) as measuring_item,
               (SELECT MAX(CAST(m.sample_sequence AS UNSIGNED)) 
                FROM dtc_measurements m 
                JOIN dtc_inspection_sessions s2 ON m.session_id = s2.session_id 
                WHERE s2.parameter_id = p.parameter_id AND m.sample_value != '') as max_seq
        FROM dtc_master_parameters p
        LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
        WHERE p.target_month = :month 
        " . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
        " . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
        ORDER BY COALESCE(p.line_name, spec.line_name), COALESCE(p.section_name, spec.section_name), COALESCE(p.process_name, spec.process_name)
    ";
    $stmtParams = $conn->prepare($sqlParams);
    $stmtParams->execute([':month' => $month]);
    $parameters = $stmtParams->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all time labels for different lines
    $stmtLabel = $conn->prepare("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE 'time_matrix_labels_%'");
    $stmtLabel->execute();
    
    $line_labels = [];
    $global_max_slots = 10;
    while ($rowSetting = $stmtLabel->fetch(PDO::FETCH_ASSOC)) {
        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
        $decoded = json_decode($val, true);
        if ($decoded) {
            $line_name = str_replace('time_matrix_labels_', '', $rowSetting['setting_key']);
            $line_labels[$line_name] = $decoded;
            if (count($decoded) > $global_max_slots) {
                $global_max_slots = count($decoded);
            }
        }
    }
    
    $default_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
    
    $time_labels = [];
    for ($i = 1; $i <= $global_max_slots; $i++) {
        $time_labels[] = "S$i";
    }

    // 2. Fetch inspection sessions for exactly this date
    $sqlSessions = "
        SELECT s.parameter_id, s.is_closed,
               (SELECT GROUP_CONCAT(m.sample_sequence) FROM dtc_measurements m WHERE m.session_id = s.session_id AND m.sample_value != '') as filled_sequences
        FROM dtc_inspection_sessions s
        WHERE DATE(s.inspection_date) = :date_val
        AND s.is_active = 1
    ";
    $stmtSessions = $conn->prepare($sqlSessions);
    $stmtSessions->execute([':date_val' => $date]);
    $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);
    
    // Lookup array
    $hasData = [];
    foreach ($sessions as $session) {
        $pid = $session['parameter_id'];
        $filled = [];
        if (!empty($session['filled_sequences'])) {
            $filled = explode(',', $session['filled_sequences']);
        }
        $hasData[$pid] = [
            'is_closed' => intval($session['is_closed']),
            'filled' => $filled
        ];
    }

    // Set of parameter IDs that have checkpoints defined
    $stmtHasCp = $conn->query("SELECT DISTINCT parameter_id FROM dtc_checkpoints");
    $paramsWithCheckpoints = array_flip($stmtHasCp->fetchAll(PDO::FETCH_COLUMN));
    
    // 3. Format the final output and calculate counts per data type
    $data = [];
    $counts = ['All' => 0, 'CTQ' => 0, 'CTP' => 0, 'Time Check' => 0, 'F/Proof' => 0];

    foreach ($parameters as $param) {
        $pid = $param['parameter_id'];
        $line_name = $param['line_name'] ?? '';
        $section_name = $param['section_name'] ?? '';
        $model_name = $param['model_name'] ?? '';

        // If running models exist for this month/section/line, filter out non-running models
        if (!empty($runningSet)) {
            $k = strtolower(trim($line_name)) . '|' . strtolower(trim($section_name)) . '|' . strtolower(trim($model_name));
            if (!isset($runningSet[$k])) {
                continue;
            }
        }

        $row = [
            'parameter_id' => $pid,
            'line_name' => $line_name,
            'section_name' => $section_name,
            'process_name' => $param['process_name'],
            'model_name' => $model_name,
            'item_check_name' => $param['item_check_name'],
            'sub_item_check_name' => $param['sub_item_check_name'],
            'data_type' => $param['data_type'],
            'slots' => []
        ];
        
        $is_closed = 0;
        $filled = [];
        if (isset($hasData[$pid])) {
            $is_closed = $hasData[$pid]['is_closed'];
            $filled = $hasData[$pid]['filled'];
        }

        // Skip this parameter if it's already closed/locked for the day
        if ($is_closed == 1) {
            continue;
        }

        // Rule for Qualitative (Time Check / F/Proof): skip if no checkpoints added yet
        $measuringItem = strtolower(trim($param['measuring_item'] ?? 'quantitative'));
        if ($measuringItem === 'qualitative' && !isset($paramsWithCheckpoints[$pid])) {
            continue;
        }

        $current_line_labels = $line_labels[$line_name] ?? $default_labels;
        
        $param_max_seq = intval($param['max_seq']);
        $param_allowed_slots = ($param_max_seq > 0) ? $param_max_seq : count($current_line_labels);
        
        // Ensure we provide labels up to param_allowed_slots
        $row['time_labels'] = array_slice($current_line_labels, 0, $param_allowed_slots);

        $hasMissingSlot = false;

        // Loop through the max slots
        for ($seq = 1; $seq <= $global_max_slots; $seq++) {
            if ($seq > $param_allowed_slots) {
                $status = 4; // Not applicable for this parameter
            } else if ($is_closed == 1) {
                $status = 2; // Closed
            } else if (in_array((string)$seq, $filled)) {
                $status = 1; // Filled
            } else if ($is_weekend) {
                $status = 3; // Weekend
            } else {
                $status = 0; // Missing
                $hasMissingSlot = true;
            }
            $row['slots'][] = $status;
        }
        
        $data[] = $row;

        if ($hasMissingSlot) {
            $counts['All']++;
            $type = strtoupper(trim($param['data_type'] ?? ''));
            if ($type === 'CTQ') $counts['CTQ']++;
            else if ($type === 'CTP') $counts['CTP']++;
            else if ($type === 'TIME CHECK') $counts['Time Check']++;
            else if ($type === 'F/PROOF') $counts['F/Proof']++;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'date' => $date,
        'is_weekend' => $is_weekend,
        'time_labels' => $time_labels,
        'data' => $data,
        'counts' => $counts
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

<?php
// c_missing_data_daily.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

$prodHour = (int)date('H');
$defaultProdDate = ($prodHour < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$date = isset($_GET['date']) ? $_GET['date'] : $defaultProdDate;
$month = date('Y-m', strtotime($date));
$line_filter = isset($_GET['line']) ? trim($_GET['line']) : '';
$section_filter = isset($_GET['section']) ? trim($_GET['section']) : '';

$nowH = $prodHour;
$nowM = (int)date('i');
$todayStr = $defaultProdDate;

if (!function_exists('isSlotPast')) {
    function isSlotPast($timeStr, $nowH, $nowM) {
        if (!$timeStr) return false;
        $tp = explode(':', trim($timeStr));
        if (count($tp) < 2) return false;
        $h = (int)$tp[0]; $m = (int)$tp[1];
        if ($h >= 24) $h = $h - 24;

        // Production day runs from 07:00 AM to 07:00 AM next morning.
        $slotMinutesFrom7 = ($h < 7 ? $h + 24 : $h) * 60 + $m;
        $nowMinutesFrom7 = ($nowH < 7 ? $nowH + 24 : $nowH) * 60 + $nowM;

        return $slotMinutesFrom7 <= $nowMinutesFrom7;
    }
}

if (!function_exists('isSlotBeforeCreationHelper')) {
    function isSlotBeforeCreationHelper($slotTime, $timeLabels, $slotIdx, $createdAtStr, $dateStr) {
        if (empty($createdAtStr) || empty($dateStr) || empty($slotTime)) return false;
        
        $parts = explode(' ', trim($createdAtStr));
        $createdDate = $parts[0] ?? '';
        $createdTime = $parts[1] ?? '';
        
        if ($createdDate < $dateStr) return false;
        if ($createdDate > $dateStr) return true;
        
        // Same date
        $tp = explode(':', $createdTime);
        $cH = (int)($tp[0] ?? 0);
        $cM = (int)($tp[1] ?? 0);

        // If created before 07:00 AM on the same calendar day -> active before shift start!
        if ($cH < 7) return false;
        
        $createdMinutesFrom7 = ($cH - 7) * 60 + $cM;
        
        $parseMinsFrom7 = function($t) {
            $parts = explode(':', trim($t));
            $h = (int)($parts[0] ?? 0);
            $m = (int)($parts[1] ?? 0);
            if ($h >= 24) $h -= 24;
            $hShift = ($h < 7 ? $h + 24 : $h);
            return ($hShift - 7) * 60 + $m;
        };
        
        $curSlotMins = $parseMinsFrom7($slotTime);
        
        $nextSlotTime = $timeLabels[$slotIdx + 1] ?? null;
        if ($nextSlotTime) {
            $nextSlotMins = $parseMinsFrom7($nextSlotTime);
        } else {
            $nextSlotMins = $curSlotMins + 120; // fallback to 2 hours
        }
        
        // If the model was created on or after this slot's window ended, then this slot is before creation
        return $createdMinutesFrom7 >= $nextSlotMins;
    }
}

$timestamp = strtotime($date);
$dayOfWeek = date('N', $timestamp);
$is_weekend = ($dayOfWeek == 6 || $dayOfWeek == 7) ? true : false;

try {
    $conn = getDBConnection();
    
    // 0. Fetch active running models for this month filtered by IP & User Section
    $sqlRM = "
        SELECT line_name, section_name, model_name, created_at 
        FROM dtc_running_models 
        WHERE target_month = :month AND is_active = 1
        " . getIPAccessFilterSQL('line_name', 'section_name') . "
        " . getUserAccessFilterSQL('line_name', 'section_name') . "
    ";
    $stmtRM = $conn->prepare($sqlRM);
    $stmtRM->execute([':month' => $month]);
    $activeRMs = $stmtRM->fetchAll(PDO::FETCH_ASSOC);

    $runningSet = [];
    $runningModels = [];
    $sectionHasRunning = [];
    foreach ($activeRMs as $rm) {
        $lName = strtolower(trim($rm['line_name']));
        $sName = strtolower(trim($rm['section_name']));
        $mName = strtolower(trim($rm['model_name']));
        
        $k = $lName . '|' . $sName . '|' . $mName;
        $runningSet[$k] = $rm['created_at'] ?? true;
        if (!isset($runningModels[$mName])) {
            $runningModels[$mName] = $rm['created_at'] ?? true;
        }
        
        $secKey = $lName . '|' . $sName;
        $sectionHasRunning[$secKey] = true;
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
               (SELECT GROUP_CONCAT(m.sample_label) FROM dtc_measurements m WHERE m.session_id = s.session_id AND m.sample_value != '') as filled_sequences
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

    $model_filter = isset($_GET['model']) ? trim($_GET['model']) : '';
    $running_models_param = isset($_GET['running_models']) ? trim($_GET['running_models']) : '';

    foreach ($parameters as $param) {
        $pid = $param['parameter_id'];
        $line_name = $param['line_name'] ?? '';
        $section_name = $param['section_name'] ?? '';
        $model_name = $param['model_name'] ?? '';

        // Filter by Line & Section if provided in request
        if (!empty($line_filter) && strtolower(trim($line_name)) !== strtolower(trim($line_filter))) {
            continue;
        }
        if (!empty($section_filter) && strtolower(trim($section_name)) !== strtolower(trim($section_filter))) {
            continue;
        }

        // Filter by Model if explicitly passed
        if (!empty($model_filter) && strtolower(trim($model_name)) !== strtolower(trim($model_filter))) {
            continue;
        }

        $lNameLower = strtolower(trim($line_name));
        $sNameLower = strtolower(trim($section_name));
        $mNameLower = strtolower(trim($model_name));
        $comboKey = $lNameLower . '|' . $sNameLower . '|' . $mNameLower;

        if (!empty($running_models_param)) {
            $rArr = json_decode($running_models_param, true);
            if (!is_array($rArr)) {
                if (strpos($running_models_param, '||') !== false) {
                    $rArr = explode('||', $running_models_param);
                } else {
                    $rArr = explode(',', $running_models_param);
                }
            }
            if (is_array($rArr)) {
                $matched = false;
                foreach ($rArr as $rmObj) {
                    if (is_array($rmObj)) {
                        $rmL = strtolower(trim($rmObj['line_name'] ?? ''));
                        $rmS = strtolower(trim($rmObj['section_name'] ?? ''));
                        $rmM = strtolower(trim($rmObj['model_name'] ?? ''));
                        if (($rmL === '' || $rmL === $lNameLower) && ($rmS === '' || $rmS === $sNameLower) && $rmM === $mNameLower) {
                            $matched = true;
                            break;
                        }
                    } else if (is_string($rmObj)) {
                        if (strtolower(trim($rmObj)) === $mNameLower) {
                            $matched = true;
                            break;
                        }
                    }
                }
                if (!$matched) continue;
            }
        } else if (!empty($runningSet)) {
            // Strictly match line_name | section_name | model_name
            if (!isset($runningSet[$comboKey])) {
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
        $param_allowed_slots = count($current_line_labels);
        
        // Ensure we provide labels up to param_allowed_slots
        $row['time_labels'] = array_slice($current_line_labels, 0, $param_allowed_slots);

        $overdueCount = 0;
        $modelCreatedAt = is_string($runningSet[$comboKey] ?? null) ? $runningSet[$comboKey] : (is_string($runningModels[$mNameLower] ?? null) ? $runningModels[$mNameLower] : null);

        // Loop through the max slots
        for ($seq = 1; $seq <= $global_max_slots; $seq++) {
            $slotTime = $current_line_labels[$seq - 1] ?? null;
            $isBeforeCreation = isSlotBeforeCreationHelper($slotTime, $current_line_labels, $seq - 1, $modelCreatedAt, $date);

            if ($seq > $param_allowed_slots || $isBeforeCreation) {
                $status = 4; // Not applicable (session occurred before running model was raised)
            } else if ($is_closed == 1) {
                $status = 2; // Closed
            } else if (in_array($slotTime, $filled)) {
                $status = 1; // Filled
            } else {
                $isPast = ($date < $todayStr) || ($date == $todayStr && isSlotPast($slotTime, $nowH, $nowM));
                if ($isPast) {
                    $status = 0; // Missing (slot time has already passed)
                    $overdueCount++;
                } else if ($is_weekend) {
                    $status = 3; // Weekend future slot
                } else {
                    $status = 0; // Unfilled future slot
                }
            }
            $row['slots'][] = $status;
        }
        
        $data[] = $row;

        if ($overdueCount > 0) {
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

<?php
// c_missing_data.php - Missing Data Tracker (Today's Shift & Active Running Models Focus)
require_once '../../../config/config.php';
header('Content-Type: application/json');

$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if ($userRole !== 'admin' && strpos($userRole, 'supervisor') === false) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Data Monitoring is restricted to Supervisor and Admin.'
    ]);
    exit;
}

$prodHour = (int)date('H');
$prodToday = ($prodHour < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

$date = isset($_GET['date']) ? $_GET['date'] : $prodToday;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m', strtotime($date));

$nowH = $prodHour;
$nowM = (int)date('i');
$todayStr = $prodToday;

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
        $createdMinutesFrom7 = ($cH < 7 ? $cH + 24 : $cH) * 60 + $cM;
        
        $parseMins = function($t) {
            $parts = explode(':', trim($t));
            $h = (int)($parts[0] ?? 0);
            $m = (int)($parts[1] ?? 0);
            if ($h >= 24) $h -= 24;
            $mins = ($h < 7 ? $h + 24 : $h) * 60 + $m;
            return $mins;
        };
        
        $curSlotMins = $parseMins($slotTime);
        
        $nextSlotTime = $timeLabels[$slotIdx + 1] ?? null;
        if ($nextSlotTime) {
            $nextSlotMins = $parseMins($nextSlotTime);
        } else {
            $nextSlotMins = $curSlotMins + 120; // fallback to 2 hours
        }
        
        // If the model was created on or after this slot's window ended, then this slot is before creation
        return $createdMinutesFrom7 >= $nextSlotMins;
    }
}

try {
    $conn = getDBConnection();
    
    // 0. Fetch active running models for this month filtered by IP & User Access SQL
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
    $sectionHasRunning = [];
    foreach ($activeRMs as $rm) {
        $lName = strtolower(trim($rm['line_name']));
        $sName = strtolower(trim($rm['section_name']));
        $mName = strtolower(trim($rm['model_name']));
        
        $k = $lName . '|' . $sName . '|' . $mName;
        $runningSet[$k] = $rm['created_at'] ?? true;
        
        $secKey = $lName . '|' . $sName;
        $sectionHasRunning[$secKey] = true;
    }

    // 1. Fetch parameters for target month filtered by User & IP access
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
    
    // Fetch time labels per line
    $stmtLabel = $conn->prepare("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE 'time_matrix_labels_%'");
    $stmtLabel->execute();
    $line_labels = [];
    while ($rowSetting = $stmtLabel->fetch(PDO::FETCH_ASSOC)) {
        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
        $decoded = json_decode($val, true);
        if ($decoded) {
            $ln = str_replace('time_matrix_labels_', '', $rowSetting['setting_key']);
            $line_labels[$ln] = $decoded;
        }
    }
    $default_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];

    // Load checkpoints for qualitative params
    $stmtHasCp = $conn->query("SELECT DISTINCT parameter_id FROM dtc_checkpoints");
    $paramsWithCheckpoints = array_flip($stmtHasCp->fetchAll(PDO::FETCH_COLUMN));

    // Fetch today's inspection sessions
    $sqlSessions = "
        SELECT s.parameter_id, s.is_closed, s.session_id,
               (SELECT GROUP_CONCAT(m.sample_label) FROM dtc_measurements m WHERE m.session_id = s.session_id AND m.sample_value != '') as filled_sequences
        FROM dtc_inspection_sessions s
        WHERE DATE(s.inspection_date) = :date_val AND s.is_active = 1
    ";
    $stmtSessions = $conn->prepare($sqlSessions);
    $stmtSessions->execute([':date_val' => $date]);
    $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

    $hasData = [];
    foreach ($sessions as $session) {
        $pid = $session['parameter_id'];
        $filled = !empty($session['filled_sequences']) ? explode(',', $session['filled_sequences']) : [];
        $hasData[$pid] = [
            'is_closed' => intval($session['is_closed']),
            'filled' => $filled
        ];
    }

    $timestamp = strtotime($date);
    $is_weekend = (date('N', $timestamp) >= 6);

    $data = [];
    $daysInMonth = (int)date('t', strtotime($month . '-01'));

    foreach ($parameters as $param) {
        $pid = $param['parameter_id'];
        $line_name = $param['line_name'] ?? 'REF 01';
        $section_name = $param['section_name'] ?? '';
        $model_name = $param['model_name'] ?? '';

        $secKey = strtolower(trim($line_name)) . '|' . strtolower(trim($section_name));
        $k = $secKey . '|' . strtolower(trim($model_name));

        // If active running models exist, filter out parameters that are not active running models
        if (!empty($runningSet)) {
            if (!isset($runningSet[$k])) {
                continue;
            }
        }

        // Qualitative rule: skip if no checkpoints
        $measuringItem = strtolower(trim($param['measuring_item'] ?? 'quantitative'));
        if ($measuringItem === 'qualitative' && !isset($paramsWithCheckpoints[$pid])) {
            continue;
        }

        $current_line_labels = $line_labels[$line_name] ?? $default_labels;
        $param_allowed_slots = count($current_line_labels);

        $is_closed = 0;
        $filled = [];
        if (isset($hasData[$pid])) {
            $is_closed = $hasData[$pid]['is_closed'];
            $filled = $hasData[$pid]['filled'];
        }

        $slots = [];
        $overdueCount = 0;
        $filledCount = 0;
        $expectedCount = 0;

        $modelCreatedAt = is_string($runningSet[$k] ?? null) ? $runningSet[$k] : null;

        for ($seq = 1; $seq <= 11; $seq++) {
            $slotTime = $current_line_labels[$seq - 1] ?? null;
            $isBeforeCreation = isSlotBeforeCreationHelper($slotTime, $current_line_labels, $seq - 1, $modelCreatedAt, $date);

            if ($seq > $param_allowed_slots || $isBeforeCreation) {
                $status = 4;
            } else if ($is_closed == 1) {
                $status = 2;
                $filledCount++;
                $expectedCount++;
            } else if (in_array($slotTime, $filled)) {
                $status = 1;
                $filledCount++;
                $expectedCount++;
            } else {
                $isPast = ($date < $todayStr) || ($date == $todayStr && isSlotPast($slotTime, $nowH, $nowM));
                $expectedCount++;
                if ($isPast) {
                    $status = 0;
                    $overdueCount++;
                } else if ($is_weekend) {
                    $status = 3;
                } else {
                    $status = 0;
                }
            }
            $slots[] = $status;
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
            'slots_per_day' => $param_allowed_slots,
            'is_closed' => $is_closed,
            'slots' => $slots,
            'overdue_slots_today' => $overdueCount,
            'filled_slots_today' => $filledCount,
            'expected_slots_today' => $expectedCount,
            'time_labels' => array_slice($current_line_labels, 0, $param_allowed_slots)
        ];

        // For backward compatibility with monthly grid structure:
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayStr = str_pad($d, 2, '0', STR_PAD_LEFT);
            $targetDateStr = $month . '-' . $dayStr;
            if ($targetDateStr === $date) {
                $row['day_' . $d] = ($is_closed == 1 ? 2 : (count($filled) > 0 ? 1 : 0));
                $row['day_' . $d . '_missing_slots'] = $overdueCount;
            } else if ($targetDateStr > $todayStr) {
                $row['day_' . $d] = 3;
                $row['day_' . $d . '_missing_slots'] = 0;
            } else {
                $row['day_' . $d] = 0;
                $row['day_' . $d . '_missing_slots'] = 0;
            }
        }

        $data[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'date' => $date,
        'month' => $month,
        'today_formatted' => date('d M Y', strtotime($date)),
        'is_weekend' => $is_weekend,
        'days_count' => $daysInMonth,
        'data' => $data
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

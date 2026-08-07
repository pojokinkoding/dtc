<?php
// c_missing_data.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

try {
    $conn = getDBConnection();
    
    // 1. Fetch all active parameters for the month (All sections across the line)
    $sqlParams = "
        SELECT p.parameter_id, p.target_month, spec.model_name, spec.item_check_name, spec.sub_item_check_name, spec.data_type, spec.section_name, spec.line_name, spec.process_name,
        (SELECT MAX(CAST(m.sample_sequence AS UNSIGNED)) 
         FROM dtc_measurements m 
         JOIN dtc_inspection_sessions s2 ON m.session_id = s2.session_id 
         WHERE s2.parameter_id = p.parameter_id AND m.sample_value != '') as max_seq
        FROM dtc_master_parameters p
        JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
        WHERE p.target_month = :month
        ORDER BY spec.line_name, spec.section_name, spec.process_name
    ";
    $stmtParams = $conn->prepare($sqlParams);
    $stmtParams->execute([':month' => $month]);
    $parameters = $stmtParams->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all time labels for different lines
    $stmtLabel = $conn->prepare("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE 'time_matrix_labels_%'");
    $stmtLabel->execute();
    
    $line_labels = [];
    while ($rowSetting = $stmtLabel->fetch(PDO::FETCH_ASSOC)) {
        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
        $decoded = json_decode($val, true);
        if ($decoded) {
            $line_name = str_replace('time_matrix_labels_', '', $rowSetting['setting_key']);
            $line_labels[$line_name] = $decoded;
        }
    }
    
    $default_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];

    // 2. Fetch all checkpoints for Checkpoint Method parameters (Time Check / F/Proof)
    $sqlCheckpoints = "
        SELECT checkpoint_id, parameter_id, checkpoint_name, checkpoint_type, sort_order 
        FROM dtc_checkpoints 
        ORDER BY parameter_id, sort_order ASC
    ";
    $allCheckpoints = $conn->query($sqlCheckpoints)->fetchAll(PDO::FETCH_ASSOC);
    $paramCheckpoints = [];
    foreach ($allCheckpoints as $cp) {
        $pid = $cp['parameter_id'];
        if (!isset($paramCheckpoints[$pid])) $paramCheckpoints[$pid] = [];
        $paramCheckpoints[$pid][] = $cp;
    }

    // Fetch parameter-level distinct sample_labels for Time Check / F/Proof
    $sqlParamTimeLabels = "
        SELECT s.parameter_id, m.checkpoint_id, m.sample_label
        FROM dtc_measurements m
        JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
        WHERE DATE_FORMAT(s.inspection_date, '%Y-%m') = :month AND m.sample_label != ''
        GROUP BY s.parameter_id, m.checkpoint_id, m.sample_label
        ORDER BY m.measurement_id ASC
    ";
    $stmtPTL = $conn->prepare($sqlParamTimeLabels);
    $stmtPTL->execute([':month' => $month]);
    $param_time_labels = [];
    $cp_time_labels = [];
    while ($rowPTL = $stmtPTL->fetch(PDO::FETCH_ASSOC)) {
        $pid = $rowPTL['parameter_id'];
        $cpid = intval($rowPTL['checkpoint_id']);
        $lbl = $rowPTL['sample_label'];

        if (!isset($param_time_labels[$pid])) $param_time_labels[$pid] = [];
        if (!in_array($lbl, $param_time_labels[$pid])) $param_time_labels[$pid][] = $lbl;

        if ($cpid > 0) {
            if (!isset($cp_time_labels[$cpid])) $cp_time_labels[$cpid] = [];
            if (!in_array($lbl, $cp_time_labels[$cpid])) $cp_time_labels[$cpid][] = $lbl;
        }
    }

    // 3. Fetch inspection sessions and measurements
    $sqlSessions = "
        SELECT s.parameter_id, DATE_FORMAT(s.inspection_date, '%d') as day_of_month, s.is_closed, s.session_id
        FROM dtc_inspection_sessions s
        WHERE DATE_FORMAT(s.inspection_date, '%Y-%m') = :month
        AND s.is_active = 1
    ";
    $stmtSessions = $conn->prepare($sqlSessions);
    $stmtSessions->execute([':month' => $month]);
    $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

    $sessionInfo = [];
    $sessionIds = [];
    $paramSessionsByDay = [];
    foreach ($sessions as $session) {
        $sid = $session['session_id'];
        $pid = $session['parameter_id'];
        $day = intval($session['day_of_month']);
        $sessionInfo[$sid] = $session;
        $sessionIds[] = $sid;

        if (!isset($paramSessionsByDay[$pid])) $paramSessionsByDay[$pid] = [];
        $paramSessionsByDay[$pid][$day] = [
            'status' => intval($session['is_closed']) === 1 ? 2 : 1,
            'session_id' => $sid
        ];
    }

    $filledByCheckpoint = [];
    $filledByParam = [];
    if (!empty($sessionIds)) {
        $inClause = implode(',', array_map('intval', $sessionIds));
        $sqlMeas = "
            SELECT m.session_id, m.checkpoint_id, 
                   GROUP_CONCAT(DISTINCT m.sample_sequence) as filled_seqs,
                   GROUP_CONCAT(DISTINCT m.sample_label) as filled_labels
            FROM dtc_measurements m
            WHERE m.session_id IN ($inClause) AND m.sample_value != ''
            GROUP BY m.session_id, m.checkpoint_id
        ";
        $measRows = $conn->query($sqlMeas)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($measRows as $mr) {
            $sid = $mr['session_id'];
            $cpid = intval($mr['checkpoint_id']);
            if (!isset($sessionInfo[$sid])) continue;
            $pid = $sessionInfo[$sid]['parameter_id'];
            $day = intval($sessionInfo[$sid]['day_of_month']);
            $isClosed = intval($sessionInfo[$sid]['is_closed']) === 1 ? 2 : 1;

            $fl = !empty($mr['filled_labels']) ? explode(',', $mr['filled_labels']) : [];
            $fs = !empty($mr['filled_seqs']) ? explode(',', $mr['filled_seqs']) : [];

            if ($cpid > 0) {
                $filledByCheckpoint[$cpid][$day] = [
                    'status' => $isClosed,
                    'filled_labels' => $fl,
                    'filled_seqs' => $fs
                ];
            }

            if (!isset($filledByParam[$pid][$day])) {
                $filledByParam[$pid][$day] = [
                    'status' => $isClosed,
                    'filled_labels' => [],
                    'filled_seqs' => []
                ];
            }
            $filledByParam[$pid][$day]['filled_labels'] = array_values(array_unique(array_merge($filledByParam[$pid][$day]['filled_labels'], $fl)));
            $filledByParam[$pid][$day]['filled_seqs'] = array_values(array_unique(array_merge($filledByParam[$pid][$day]['filled_seqs'], $fs)));
        }
    }
    
    $daysInMonth = (int)date('t', strtotime($month . '-01'));
    
    // 4. Format the final output
    $data = [];
    foreach ($parameters as $param) {
        $pid = $param['parameter_id'];
        $dataType = strtoupper(trim($param['data_type']));
        $isTimeBased = ($dataType === 'TIME CHECK' || $dataType === 'F/PROOF');
        $line_name = $param['line_name'] ?? 'REF 01';

        if ($isTimeBased && !empty($paramCheckpoints[$pid])) {
            // Expand into 1 row per Checkpoint for precision tracking
            foreach ($paramCheckpoints[$pid] as $cp) {
                $cpid = $cp['checkpoint_id'];
                $cpName = $cp['checkpoint_name'];

                $current_labels = $line_labels[$line_name] ?? ($line_labels['default'] ?? $default_labels);
                $max_slots = count($current_labels);

                $row = [
                    'parameter_id' => $pid,
                    'checkpoint_id' => $cpid,
                    'line_name' => $param['line_name'],
                    'section_name' => $param['section_name'],
                    'process_name' => $param['process_name'],
                    'model_name' => $param['model_name'],
                    'item_check_name' => $cpName,
                    'sub_item_check_name' => $param['item_check_name'],
                    'data_type' => $param['data_type'],
                    'slots_per_day' => $max_slots
                ];

                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $ts = strtotime($month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT));
                    $isWeekend = (date('N', $ts) >= 6);

                    if (isset($filledByCheckpoint[$cpid][$i])) {
                        $status = $filledByCheckpoint[$cpid][$i]['status'];
                        $filledLbl = $filledByCheckpoint[$cpid][$i]['filled_labels'];
                        
                        // If no measurement labels were recorded for this checkpoint, treat as unfilled
                        if (empty($filledLbl)) {
                            $row["day_$i"] = $isWeekend ? 3 : 0;
                            if (!$isWeekend) {
                                $row["day_{$i}_label"] = $current_labels[0] ?? "S1";
                                $row["day_{$i}_missing_slots"] = $max_slots;
                            } else {
                                $row["day_{$i}_missing_slots"] = 0;
                            }
                        } else {
                            $row["day_$i"] = $status;
                            $missingCount = 0;
                            if ($status === 1) {
                                $missingLabel = '';
                                for ($seq = 1; $seq <= $max_slots; $seq++) {
                                    $slotLabel = $current_labels[$seq - 1] ?? "S$seq";
                                    if (!in_array($slotLabel, $filledLbl)) {
                                        if ($missingLabel === '') $missingLabel = $slotLabel;
                                        $missingCount++;
                                    }
                                }
                                $row["day_{$i}_label"] = $missingLabel;
                            }
                            $row["day_{$i}_missing_slots"] = $missingCount;
                        }
                    } else {
                        $row["day_$i"] = $isWeekend ? 3 : 0;
                        if (!$isWeekend) {
                            $row["day_{$i}_label"] = $current_labels[0] ?? "S1";
                            $row["day_{$i}_missing_slots"] = $max_slots;
                        } else {
                            $row["day_{$i}_missing_slots"] = 0;
                        }
                    }
                }
                $data[] = $row;
            }
        } else {
            // Standard 1 row per parameter for CTP / CTQ
            $current_labels = $line_labels[$line_name] ?? $default_labels;
            $param_max_seq = intval($param['max_seq']);
            $max_slots = ($param_max_seq > 0) ? $param_max_seq : count($current_labels);

            $row = [
                'parameter_id' => $pid,
                'checkpoint_id' => 0,
                'line_name' => $param['line_name'],
                'section_name' => $param['section_name'],
                'process_name' => $param['process_name'],
                'model_name' => $param['model_name'],
                'item_check_name' => $param['item_check_name'],
                'sub_item_check_name' => $param['sub_item_check_name'],
                'data_type' => $param['data_type'],
                'slots_per_day' => $max_slots
            ];

            for ($i = 1; $i <= $daysInMonth; $i++) {
                $ts = strtotime($month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT));
                $isWeekend = (date('N', $ts) >= 6);

                if (isset($filledByParam[$pid][$i])) {
                    $status = $filledByParam[$pid][$i]['status'];
                    $filledSeq = $filledByParam[$pid][$i]['filled_seqs'];
                    
                    if (empty($filledSeq)) {
                        $row["day_$i"] = $isWeekend ? 3 : 0;
                        if (!$isWeekend) {
                            $row["day_{$i}_label"] = $current_labels[0] ?? "S1";
                            $row["day_{$i}_missing_slots"] = $max_slots;
                        } else {
                            $row["day_{$i}_missing_slots"] = 0;
                        }
                    } else {
                        $row["day_$i"] = $status;
                        $missingCount = 0;
                        if ($status === 1) {
                            $missingLabel = '';
                            for ($seq = 1; $seq <= $max_slots; $seq++) {
                                if (!in_array((string)$seq, $filledSeq)) {
                                    if ($missingLabel === '') $missingLabel = $current_labels[$seq - 1] ?? "S$seq";
                                    $missingCount++;
                                }
                            }
                            $row["day_{$i}_label"] = $missingLabel;
                        }
                        $row["day_{$i}_missing_slots"] = $missingCount;
                    }
                } else {
                    $row["day_$i"] = $isWeekend ? 3 : 0;
                    if (!$isWeekend) {
                        $row["day_{$i}_label"] = $current_labels[0] ?? "S1";
                        $row["day_{$i}_missing_slots"] = $max_slots;
                    } else {
                        $row["day_{$i}_missing_slots"] = 0;
                    }
                }
            }
            $data[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'month' => $month,
        'days_count' => $daysInMonth,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

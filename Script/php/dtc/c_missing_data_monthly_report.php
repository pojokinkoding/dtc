<?php
// c_missing_data_monthly_report.php - Monthly Station Performance Reporting & Excel Export
require_once __DIR__ . '/../../../config/config.php';

$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) && empty($_SESSION['username']) && empty($_SESSION['role'])) {
    $format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : (isset($_GET['export']) ? strtolower(trim($_GET['export'])) : 'json');
    if ($format === 'excel' || $format === 'pdf') {
        die("Unauthorized access. Please log in to download reports.");
    }
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Please log in to view reports.'
    ]);
    exit;
}

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$targetSection = isset($_GET['section_name']) ? trim($_GET['section_name']) : '';
$targetLine = isset($_GET['line_name']) ? trim($_GET['line_name']) : '';
$targetParamId = isset($_GET['param_id']) ? intval($_GET['param_id']) : 0;
$targetCheckpointId = isset($_GET['checkpoint_id']) ? intval($_GET['checkpoint_id']) : 0;
$targetParamKey = isset($_GET['param_key']) ? trim($_GET['param_key']) : '';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : (isset($_GET['export']) ? strtolower(trim($_GET['export'])) : 'json');

try {
    $conn = getDBConnection();
    
    // 0. Fetch active running models for target month
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
    $sectionHasRunning = [];
    foreach ($activeRMs as $rm) {
        $lName = strtolower(trim($rm['line_name']));
        $sName = strtolower(trim($rm['section_name']));
        $mName = strtolower(trim($rm['model_name']));
        
        $k = $lName . '|' . $sName . '|' . $mName;
        $runningSet[$k] = true;
        
        $secKey = $lName . '|' . $sName;
        $sectionHasRunning[$secKey] = true;
    }

    // Build SQL query for active parameters in target month
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
               COALESCE(p.lsl, spec.lsl) as lsl,
               COALESCE(p.usl, spec.usl) as usl,
               COALESCE(p.target_value, spec.target_value) as target_value,
               COALESCE(p.target_zst, spec.target_zst) as target_zst,
               COALESCE(p.target_zlt, spec.target_zlt) as target_zlt,
        (SELECT MAX(CAST(m.sample_sequence AS UNSIGNED)) 
         FROM dtc_measurements m 
         JOIN dtc_inspection_sessions s2 ON m.session_id = s2.session_id 
         WHERE s2.parameter_id = p.parameter_id AND m.sample_value != '') as max_seq
        FROM dtc_master_parameters p
        LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    ";
    
    $paramsExec = [];
    if (!empty($targetParamId) && $targetParamId > 0) {
        $sqlParams .= " WHERE p.parameter_id = :param_target ";
        $paramsExec[':param_target'] = $targetParamId;
    } else {
        $sqlParams .= " WHERE p.target_month = :month ";
        $paramsExec[':month'] = $month;
        if (!empty($targetSection)) {
            $sqlParams .= " AND UPPER(COALESCE(p.section_name, spec.section_name)) = UPPER(:sec_target) ";
            $paramsExec[':sec_target'] = $targetSection;
        }
        if (!empty($targetLine)) {
            $sqlParams .= " AND UPPER(COALESCE(p.line_name, spec.line_name)) = UPPER(:line_target) ";
            $paramsExec[':line_target'] = $targetLine;
        }
    }
    $sqlParams .= " " . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)');
    $sqlParams .= " " . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)');

    $sqlParams .= " ORDER BY COALESCE(p.line_name, spec.line_name), COALESCE(p.section_name, spec.section_name), COALESCE(p.process_name, spec.process_name)";

    $stmtParams = $conn->prepare($sqlParams);
    $stmtParams->execute($paramsExec);
    $parameters = $stmtParams->fetchAll(PDO::FETCH_ASSOC);

    // Load line time slot labels
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

    // Load all inspection sessions for target month
    $sqlSessions = "
        SELECT s.parameter_id, DATE_FORMAT(s.inspection_date, '%Y-%m-%d') as inspection_date, s.is_closed, s.session_id,
               (SELECT GROUP_CONCAT(DISTINCT m.sample_label) FROM dtc_measurements m WHERE m.session_id = s.session_id AND m.sample_value != '') as filled_sequences
        FROM dtc_inspection_sessions s
        WHERE DATE_FORMAT(s.inspection_date, '%Y-%m') = :month AND s.is_active = 1
    ";
    $stmtSessions = $conn->prepare($sqlSessions);
    $stmtSessions->execute([':month' => $month]);
    $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

    $sessionMap = [];
    foreach ($sessions as $session) {
        $pid = $session['parameter_id'];
        $dateStr = $session['inspection_date'];
        $filled = !empty($session['filled_sequences']) ? explode(',', $session['filled_sequences']) : [];
        $sessionMap[$pid][$dateStr] = [
            'is_closed' => intval($session['is_closed']),
            'filled' => $filled
        ];
    }

    $daysInMonth = (int)date('t', strtotime($month . '-01'));
    $nowDateStr = date('Y-m-d');
    $prodHour = (int)date('H');
    $prodToday = ($prodHour < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

    // 0.1 Fetch sub-checkpoints for parameters if any
    $expandedParameters = [];
    $stmtCp = $conn->prepare("SELECT * FROM dtc_checkpoints WHERE parameter_id = :pid ORDER BY sort_order ASC, checkpoint_id ASC");

    foreach ($parameters as $param) {
        $pid = $param['parameter_id'];
        $stmtCp->execute([':pid' => $pid]);
        $checkpoints = $stmtCp->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($checkpoints)) {
            foreach ($checkpoints as $cp) {
                $pItem = $param;
                $pItem['checkpoint_id'] = (int)$cp['checkpoint_id'];
                $pItem['parameter_key'] = $pid . '_' . $cp['checkpoint_id'];
                
                $cpName = trim($cp['checkpoint_name'] ?? '');
                if ($cpName !== '') {
                    $pItem['sub_item_check_name'] = $cpName;
                    $pItem['measuring_item'] = $cpName;
                }
                
                if (!empty($cp['checkpoint_type'])) {
                    $pItem['data_type'] = $cp['checkpoint_type'];
                }
                if ($cp['lsl'] !== null && $cp['lsl'] !== '') $pItem['lsl'] = (float)$cp['lsl'];
                if ($cp['usl'] !== null && $cp['usl'] !== '') $pItem['usl'] = (float)$cp['usl'];
                if ($cp['target_value'] !== null && $cp['target_value'] !== '') $pItem['target_value'] = (float)$cp['target_value'];
                if (!empty($cp['spec_value'])) $pItem['spec_value'] = $cp['spec_value'];

                $expandedParameters[] = $pItem;
            }
        } else {
            $pItem = $param;
            $pItem['checkpoint_id'] = null;
            $pItem['parameter_key'] = (string)$pid;
            $expandedParameters[] = $pItem;
        }
    }

    if (!empty($targetCheckpointId) && $targetCheckpointId > 0) {
        $expandedParameters = array_values(array_filter($expandedParameters, function($item) use ($targetCheckpointId) {
            return !empty($item['checkpoint_id']) && intval($item['checkpoint_id']) === $targetCheckpointId;
        }));
    } elseif (!empty($targetParamKey)) {
        $expandedParameters = array_values(array_filter($expandedParameters, function($item) use ($targetParamKey) {
            return isset($item['parameter_key']) && (string)$item['parameter_key'] === (string)$targetParamKey;
        }));
    }

    // Aggregate monthly report data per section
    $sectionData = [];

    foreach ($expandedParameters as $param) {
        $pid = $param['parameter_id'];
        $pKey = $param['parameter_key'];
        $line_name = $param['line_name'] ?? 'REF 01';
        $section_name = $param['section_name'] ?? 'GENERAL';
        $model_name = $param['model_name'] ?? '';

        $sKey = strtolower(trim($line_name)) . '|' . strtolower(trim($section_name));
        $kKey = $sKey . '|' . strtolower(trim($model_name));

        if (empty($targetParamId)) {
            if (!empty($sectionHasRunning[$sKey])) {
                if (!isset($runningSet[$kKey])) {
                    continue;
                }
            }
        }

        $secKey = $line_name . '___' . $section_name;

        if (!isset($sectionData[$secKey])) {
            $sectionData[$secKey] = [
                'line_name' => $line_name,
                'section_name' => $section_name,
                'total_parameters' => 0,
                'days' => [],
                'total_days_incomplete' => 0,
                'missed_items_by_date' => [],
                'parameters' => []
            ];
        }
        $sectionData[$secKey]['total_parameters']++;
        $sectionData[$secKey]['parameters'][] = $param;

        $current_line_labels = $line_labels[$line_name] ?? $default_labels;
        $slots_per_day = count($current_line_labels);

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            
            // Skip future dates beyond today's production date
            if ($dateStr > $prodToday) {
                continue;
            }

            $ts = strtotime($dateStr);
            $isWeekend = (date('N', $ts) >= 6);

            if (!isset($sectionData[$secKey]['days'][$dateStr])) {
                $sectionData[$secKey]['days'][$dateStr] = [
                    'date' => $dateStr,
                    'day_num' => $d,
                    'is_weekend' => $isWeekend,
                    'expected_slots' => 0,
                    'filled_slots' => 0,
                    'completion_rate' => 0,
                    'is_full' => true
                ];
            }

            $is_closed = 0;
            $filled = [];
            if (isset($sessionMap[$pid][$dateStr])) {
                $is_closed = $sessionMap[$pid][$dateStr]['is_closed'];
                $filled = $sessionMap[$pid][$dateStr]['filled'];
            }

            $paramExpected = $slots_per_day;
            $paramFilled = 0;

            if ($is_closed === 1) {
                $paramFilled = $slots_per_day;
            } else {
                $paramFilled = count($filled);
            }

            $sectionData[$secKey]['days'][$dateStr]['expected_slots'] += $paramExpected;
            $sectionData[$secKey]['days'][$dateStr]['filled_slots'] += $paramFilled;

            $missingSlots = $paramExpected - $paramFilled;
            if ($missingSlots > 0 && !$isWeekend) {
                if (!isset($sectionData[$secKey]['missed_items_by_date'][$dateStr])) {
                    $sectionData[$secKey]['missed_items_by_date'][$dateStr] = [];
                }
                $sectionData[$secKey]['missed_items_by_date'][$dateStr][] = [
                    'parameter_id' => $pid,
                    'line_name' => $line_name,
                    'section_name' => $section_name,
                    'process_name' => $param['process_name'],
                    'model_name' => $param['model_name'],
                    'item_check_name' => $param['item_check_name'],
                    'sub_item_check_name' => $param['sub_item_check_name'],
                    'data_type' => $param['data_type'],
                    'expected_slots' => $paramExpected,
                    'filled_slots' => $paramFilled,
                    'missing_slots' => $missingSlots
                ];
            }
        }
    }

    // Pre-fetch all measurements in target month for matrix grid & data summary calculations
    $sqlAllMeas = "
        SELECT s.parameter_id,
               m.checkpoint_id,
               COALESCE(p.line_name, spec.line_name) as line_name,
               DATE_FORMAT(s.inspection_date, '%d') as day_num,
               m.sample_sequence,
               m.sample_label,
               m.sample_value,
               s.max_value,
               s.min_value,
               s.x_bar,
               s.std_dev
        FROM dtc_inspection_sessions s
        JOIN dtc_measurements m ON s.session_id = m.session_id
        JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
        LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
        WHERE DATE_FORMAT(s.inspection_date, '%Y-%m') = :month AND s.is_active = 1
        ORDER BY s.inspection_date ASC, m.sample_sequence ASC
    ";
    $stmtAllMeas = $conn->prepare($sqlAllMeas);
    $stmtAllMeas->execute([':month' => $month]);
    $allMeas = $stmtAllMeas->fetchAll(PDO::FETCH_ASSOC);

    $measMap = [];
    foreach ($allMeas as $rMeas) {
        $pid = $rMeas['parameter_id'];
        $cpId = $rMeas['checkpoint_id'];
        $pKey = (!empty($cpId)) ? ($pid . '_' . $cpId) : (string)$pid;
        $lName = $rMeas['line_name'] ?? '';

        $day = (int)$rMeas['day_num'];
        $val = $rMeas['sample_value'];
        $lbl = trim($rMeas['sample_label'] ?? '');
        $lblClean = preg_replace('/^Jam\s+/i', '', $lbl);

        $tSlots = $line_labels[$lName] ?? $default_labels;
        $seq = null;
        foreach ($tSlots as $idx => $tLabel) {
            if (trim($tLabel) === $lblClean) {
                $seq = $idx + 1;
                break;
            }
        }
        if ($seq === null) {
            $seq = (int)$rMeas['sample_sequence'];
        }

        $measMap[$pKey]['grid'][$seq][$day] = $val;
        $measMap[$pKey]['lbl'][$seq] = $lblClean;
        if ($rMeas['max_value'] !== null) $measMap[$pKey]['day_max'][$day] = (float)$rMeas['max_value'];
        if ($rMeas['min_value'] !== null) $measMap[$pKey]['day_min'][$day] = (float)$rMeas['min_value'];
        if ($rMeas['x_bar'] !== null) $measMap[$pKey]['day_xbar'][$day] = (float)$rMeas['x_bar'];
        if ($rMeas['std_dev'] !== null) $measMap[$pKey]['day_std'][$day] = (float)$rMeas['std_dev'];

        if ($val !== null && $val !== '' && is_numeric($val)) {
            $measMap[$pKey]['all_samples'][] = (float)$val;
        }
    }

    // Finalize metrics & parameter statistics per section
    foreach ($sectionData as $secKey => &$sec) {
        $incompleteCount = 0;
        $totalExpMonth = 0;
        $totalFilMonth = 0;

        foreach ($sec['days'] as $dateStr => &$dayObj) {
            $exp = $dayObj['expected_slots'];
            $fil = $dayObj['filled_slots'];
            $rate = ($exp > 0) ? round(($fil / $exp) * 100, 1) : 100;
            $dayObj['completion_rate'] = $rate;

            if (!$dayObj['is_weekend']) {
                $totalExpMonth += $exp;
                $totalFilMonth += $fil;
            }

            if ($rate < 100 && !$dayObj['is_weekend']) {
                $dayObj['is_full'] = false;
                $incompleteCount++;
            } else {
                $dayObj['is_full'] = true;
            }
        }
        unset($dayObj);

        $sec['total_days_incomplete'] = $incompleteCount;
        $sec['total_expected_month'] = $totalExpMonth;
        $sec['total_filled_month'] = $totalFilMonth;
        $sec['total_missing_month'] = max(0, $totalExpMonth - $totalFilMonth);
        $sec['monthly_compliance_rate'] = ($totalExpMonth > 0) ? round(($totalFilMonth / $totalExpMonth) * 100, 1) : 100;

        // Enrich parameters with Detail Information, Data Summary, & Measurement Grid payload
        if (!empty($sec['parameters'])) {
            foreach ($sec['parameters'] as &$p) {
                $pid = $p['parameter_id'];
                $pKey = $p['parameter_key'] ?? (string)$pid;
                $lName = $p['line_name'] ?? $sec['line_name'];
                $timeSlots = $line_labels[$lName] ?? $default_labels;

                $lsl = ($p['lsl'] !== null && $p['lsl'] !== '') ? (float)$p['lsl'] : null;
                $usl = ($p['usl'] !== null && $p['usl'] !== '') ? (float)$p['usl'] : null;
                $targetVal = ($p['target_value'] !== null && $p['target_value'] !== '') ? (float)$p['target_value'] : null;
                $targetZst = ($p['target_zst'] !== null && $p['target_zst'] !== '') ? $p['target_zst'] : '3';
                $targetZlt = ($p['target_zlt'] !== null && $p['target_zlt'] !== '') ? $p['target_zlt'] : '4';

                $specStr = !empty($p['spec_value']) ? $p['spec_value'] : (($lsl !== null && $usl !== null) ? "{$lsl} - {$usl}" : (($lsl !== null) ? ">= {$lsl}" : (($usl !== null) ? "<= {$usl}" : "-")));
                $centerSpec = ($targetVal !== null) ? number_format($targetVal, 2, '.', '') : (($lsl !== null && $usl !== null) ? number_format(($lsl + $usl) / 2, 2, '.', '') : '-');

                $dTypeUpper = strtoupper(trim($p['data_type'] ?? ''));
                $mItemUpper = strtoupper(trim($p['measuring_item'] ?? ''));
                $isQualitative = (
                    in_array($dTypeUpper, ['QUALITATIVE', 'F/PROOF', 'F-PROOF', 'TIME CHECK', 'VISUAL']) ||
                    in_array($mItemUpper, ['QUALITATIVE', 'F/PROOF', 'F-PROOF', 'TIME CHECK', 'VISUAL'])
                );
                $isQuantitative = !$isQualitative;
                $p['is_quantitative'] = $isQuantitative;

                $samples = $measMap[$pKey]['all_samples'] ?? [];
                $nCount = count($samples);
                $maxVal = ($isQuantitative && $nCount > 0) ? max($samples) : null;
                $minVal = ($isQuantitative && $nCount > 0) ? min($samples) : null;
                $meanVal = ($isQuantitative && $nCount > 0) ? array_sum($samples) / $nCount : null;
                $stdVal = null;
                if ($isQuantitative && $nCount > 1) {
                    $variance = 0;
                    foreach ($samples as $sv) {
                        $variance += pow($sv - $meanVal, 2);
                    }
                    $stdVal = sqrt($variance / ($nCount - 1));
                } elseif ($isQuantitative && $nCount === 1) {
                    $stdVal = 0;
                }

                $cp = null; $cpk = null; $zst = null; $zlt = null;
                if ($isQuantitative && $stdVal > 0 && $lsl !== null && $usl !== null && $usl > $lsl) {
                    $cp = ($usl - $lsl) / (6 * $stdVal);
                    $cpu = ($usl - $meanVal) / (3 * $stdVal);
                    $cpl = ($meanVal - $lsl) / (3 * $stdVal);
                    $cpk = min($cpu, $cpl);
                    $zst = 3 * $cp;
                    $zlt = 3 * $cpk;
                }

                // Daily summary rows (only for quantitative parameters)
                $dailyMaxRow = []; $dailyMinRow = []; $dailyZstRow = []; $dailyZltRow = [];
                if ($isQuantitative) {
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $dMax = $measMap[$pKey]['day_max'][$d] ?? null;
                        $dMin = $measMap[$pKey]['day_min'][$d] ?? null;
                        $dXbar = $measMap[$pKey]['day_xbar'][$d] ?? null;
                        $dStd = $measMap[$pKey]['day_std'][$d] ?? null;

                        if ($dMax === null || $dMin === null || $dXbar === null) {
                            $dayVals = [];
                            for ($sIdx = 1; $sIdx <= 10; $sIdx++) {
                                $v = $measMap[$pKey]['grid'][$sIdx][$d] ?? null;
                                if ($v !== null && $v !== '' && is_numeric($v)) {
                                    $dayVals[] = (float)$v;
                                }
                            }
                            if (!empty($dayVals)) {
                                if ($dMax === null) $dMax = max($dayVals);
                                if ($dMin === null) $dMin = min($dayVals);
                                if ($dXbar === null) $dXbar = array_sum($dayVals) / count($dayVals);
                            }
                        }

                        $dailyMaxRow[$d] = ($dMax !== null) ? ((floor($dMax) == $dMax) ? (string)(int)$dMax : number_format($dMax, 2, '.', '')) : '';
                        $dailyMinRow[$d] = ($dMin !== null) ? ((floor($dMin) == $dMin) ? (string)(int)$dMin : number_format($dMin, 2, '.', '')) : '';

                        $dZstVal = null; $dZltVal = null;
                        if ($dStd !== null && $dStd > 0 && $dXbar !== null && $lsl !== null && $usl !== null && $usl > $lsl) {
                            $dCp = ($usl - $lsl) / (6 * $dStd);
                            $dCpu = ($usl - $dXbar) / (3 * $dStd);
                            $dCpl = ($dXbar - $lsl) / (3 * $dStd);
                            $dCpk = min($dCpu, $dCpl);
                            $dZstVal = round(3 * $dCp, 2);
                            $dZltVal = round(3 * $dCpk, 2);
                        }
                        $dailyZstRow[$d] = ($dZstVal !== null) ? number_format($dZstVal, 2, '.', '') : '';
                        $dailyZltRow[$d] = ($dZltVal !== null) ? number_format($dZltVal, 2, '.', '') : '';
                    }
                }

                $p['spec_str'] = $specStr;
                $p['center_spec'] = $centerSpec;
                $p['n_count'] = $nCount;
                $p['max_val'] = ($maxVal !== null) ? number_format($maxVal, 2, '.', '') : null;
                $p['min_val'] = ($minVal !== null) ? number_format($minVal, 2, '.', '') : null;
                $p['avg_val'] = ($meanVal !== null) ? number_format($meanVal, 2, '.', '') : null;
                $p['std_val'] = ($stdVal !== null) ? number_format($stdVal, 2, '.', '') : null;
                $p['cp'] = ($cp !== null) ? number_format($cp, 2, '.', '') : null;
                $p['cpk'] = ($cpk !== null) ? number_format($cpk, 2, '.', '') : null;
                $p['zst'] = ($zst !== null) ? number_format($zst, 2, '.', '') : null;
                $p['zlt'] = ($zlt !== null) ? number_format($zlt, 2, '.', '') : null;
                $p['time_slots'] = $timeSlots;
                $p['grid_data'] = $measMap[$pKey]['grid'] ?? [];
                $p['daily_max'] = $dailyMaxRow;
                $p['daily_min'] = $dailyMinRow;
                $p['daily_zst'] = $dailyZstRow;
                $p['daily_zlt'] = $dailyZltRow;
            }
            unset($p);
        }
    }
    unset($sec);

    if ($format === 'pdf') {
        $monthFormatted = date('F Y', strtotime($month . '-01'));
        $scopeText = $targetSection ? htmlspecialchars($targetLine . ' - ' . $targetSection) : "All Stations";
        
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
        echo '<title>DTC Monthly Performance Report - ' . $scopeText . ' (' . $monthFormatted . ')</title>';
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
        echo '<style>';
        echo '  @page { size: A4 landscape; margin: 3mm 3mm; }';
        echo '  body { font-family: "Segoe UI", Arial, sans-serif; color: #0f172a; background-color: #f8fafc; margin: 0; padding: 6px; }';
        echo '  .no-print-bar { background: #0f172a; color: #ffffff; padding: 12px 20px; border-radius: 8px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }';
        echo '  .btn-print { background: #ef4444; color: #ffffff; border: none; padding: 8px 18px; font-weight: bold; border-radius: 6px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }';
        echo '  .btn-print:hover { background: #dc2626; }';
        echo '  .title-card { background: #0f172a; color: #ffffff; padding: 14px 18px; border-radius: 8px; margin-bottom: 16px; border-left: 6px solid #0284c7; }';
        echo '  .sec-card { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }';
        echo '  .sec-title { border-bottom: 2px solid #0284c7; padding-bottom: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: bold; color: #0f172a; }';
        echo '  .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }';
        echo '  .kpi-box { padding: 8px; border-radius: 6px; text-align: center; border: 1px solid #e2e8f0; font-size: 10.5px; }';
        echo '  .param-card { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; margin-bottom: 14px; }';
        echo '  .param-title { font-size: 12px; font-weight: bold; color: #0284c7; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 8px; }';
        echo '  .flex-layout { display: flex; gap: 8px; align-items: flex-start; }';
        echo '  .left-box { width: 220px; flex-shrink: 0; display: flex; flex-direction: column; gap: 8px; }';
        echo '  .right-box { flex: 1; min-width: 0; overflow-x: auto; }';
        echo '  .info-table, .summary-table { width: 100%; border-collapse: collapse; font-size: 8.5px; line-height: 1.35; }';
        echo '  .info-table td, .summary-table td { padding: 2px 4px; border-bottom: 1px solid #f1f5f9; }';
        echo '  .grid-table { width: 100%; border-collapse: collapse; font-size: 7.5px; text-align: center; table-layout: fixed; }';
        echo '  .grid-table th { background: #0f172a; color: #ffffff; padding: 3px 0px; border: 1px solid #cbd5e1; white-space: nowrap; }';
        echo '  .grid-table td { padding: 2px 0px; border: 1px solid #cbd5e1; white-space: nowrap; }';
        echo '  @media print {';
        echo '    .no-print, .no-print-bar { display: none !important; }';
        echo '    body { background: #ffffff !important; color: #0f172a !important; margin: 0 !important; padding: 0 !important; }';
        echo '    .title-card { display: none !important; }';
        echo '    .sec-card { background: transparent !important; border: none !important; padding: 0 !important; margin: 0 !important; box-shadow: none !important; }';
        echo '    .sec-title, .kpi-row { display: none !important; }';
        echo '    .param-card {';
        echo '      page-break-before: always !important;';
        echo '      break-before: page !important;';
        echo '      page-break-inside: avoid !important;';
        echo '      break-inside: avoid !important;';
        echo '      margin: 0 !important;';
        echo '      padding: 3px 4px !important;';
        echo '      border: 1px solid #cbd5e1 !important;';
        echo '      border-radius: 4px !important;';
        echo '      background: #ffffff !important;';
        echo '      box-shadow: none !important;';
        echo '      font-size: 6.5pt !important;';
        echo '    }';
        echo '    .sec-card:first-child .param-card:first-child { page-break-before: auto !important; break-before: auto !important; }';
        echo '    .flex-layout { display: flex !important; gap: 4px !important; align-items: flex-start !important; }';
        echo '    .left-box { width: 175px !important; flex-shrink: 0 !important; gap: 4px !important; }';
        echo '    .right-box { flex: 1 !important; width: auto !important; overflow: visible !important; }';
        echo '    .grid-table { width: 100% !important; table-layout: fixed !important; font-size: 5.8pt !important; letter-spacing: -0.2px !important; border-collapse: collapse !important; }';
        echo '    .grid-table th, .grid-table td { padding: 0.5px 0px !important; border: 1px solid #94a3b8 !important; text-align: center !important; white-space: nowrap !important; }';
        echo '    .grid-table th:first-child, .grid-table td:first-child { width: 30px !important; font-size: 5.5pt !important; }';
        echo '    .info-table, .summary-table { font-size: 6.5pt !important; line-height: 1.15 !important; }';
        echo '    .info-table td, .summary-table td { padding: 1px 2px !important; }';
        echo '  }';
        echo '</style>';
        echo '</head><body>';

        // Floating Control Bar for Web View
        echo '<div class="no-print-bar no-print">';
        echo '<div><strong style="font-size: 14px;"><i class="fa-solid fa-file-pdf" style="color:#ef4444; margin-right:6px;"></i> DTC Monthly Performance Report PDF Preview</strong></div>';
        echo '<div style="display:flex; gap:10px;">';
        echo '<button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Save as PDF / Print</button>';
        echo '<button style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.2); padding:8px 14px; border-radius:6px; cursor:pointer;" onclick="window.close()">Close Window</button>';
        echo '</div>';
        echo '</div>';

        // Title Header Banner
        echo '<div class="title-card">';
        echo '<h2 style="margin:0; font-size: 18px; color: #38bdf8; text-transform: uppercase;">SYSTEM DIGITAL TIME CHECK (DTC)</h2>';
        echo '<div style="font-size: 13px; color: #e2e8f0; margin-top: 4px;">LAPORAN PERFORMANCE BULANAN &amp; RAW MEASUREMENT DATA GRID &bull; ' . $monthFormatted . ' &bull; Scope: ' . $scopeText . '</div>';
        echo '</div>';

        foreach ($sectionData as $secKey => $sec) {
            if (empty($targetParamId) && (empty($sec['total_filled_month']) || $sec['total_filled_month'] <= 0)) {
                continue;
            }

            $rateVal = $sec['monthly_compliance_rate'] ?? 100;
            $rateColor = $rateVal >= 90 ? '#15803d' : '#b91c1c';

            echo '<div class="sec-card">';
            echo '<div class="sec-title">';
            echo '<div><span style="background:#0284c7; color:#fff; padding:2px 8px; border-radius:4px; font-size:11px;">LINE ' . htmlspecialchars($sec['line_name']) . '</span> &nbsp; <span style="color:#0284c7;">' . htmlspecialchars($sec['section_name']) . '</span></div>';
            echo '<div>Compliance Bulanan: <strong style="color:' . $rateColor . '; font-size:15px;">' . $rateVal . '%</strong> &nbsp;|&nbsp; Hari Tidak Full: <strong style="color:#b91c1c;">' . $sec['total_days_incomplete'] . ' Hari</strong></div>';
            echo '</div>';

            // KPI Summary Row
            echo '<div class="kpi-row">';
            echo '<div class="kpi-box" style="background:#f8fafc;"><div style="color:#64748b; font-weight:bold;">TOTAL EXPECTED SLOTS</div><div style="font-size:16px; font-weight:bold; color:#0f172a;">' . number_format($sec['total_expected_month'] ?? 0) . '</div></div>';
            echo '<div class="kpi-box" style="background:#f0fdf4;"><div style="color:#166534; font-weight:bold;">TOTAL SLOTS DIISI</div><div style="font-size:16px; font-weight:bold; color:#15803d;">' . number_format($sec['total_filled_month'] ?? 0) . '</div></div>';
            echo '<div class="kpi-box" style="background:#fef2f2;"><div style="color:#991b1b; font-weight:bold;">SLOTS KOSONG</div><div style="font-size:16px; font-weight:bold; color:#b91c1c;">' . number_format($sec['total_missing_month'] ?? 0) . '</div></div>';
            echo '<div class="kpi-box" style="background:#f0f9ff;"><div style="color:#0369a1; font-weight:bold;">PERSENTASE COMPLIANCE</div><div style="font-size:16px; font-weight:bold; color:' . $rateColor . ';">' . $rateVal . '%</div></div>';
            echo '</div>';

            if (!empty($sec['parameters'])) {
                $measuredParams = array_filter($sec['parameters'], function($p) use ($targetParamId) {
                    if (!empty($targetParamId)) return true;
                    if (!empty($p['n_count']) && intval($p['n_count']) > 0) return true;
                    if (!empty($p['grid_data'])) {
                        foreach ($p['grid_data'] as $seq => $days) {
                            foreach ($days as $day => $v) {
                                if ($v !== null && trim((string)$v) !== '') return true;
                            }
                        }
                    }
                    return false;
                });

                if (!empty($measuredParams)) {
                    foreach ($measuredParams as $pIdx => $p) {
                        $pid = $p['parameter_id'];
                        $lName = $p['line_name'] ?? $sec['line_name'];
                        $sName = $p['section_name'] ?? $sec['section_name'];
                        $mName = $p['model_name'] ?? '-';
                        $iName = $p['item_check_name'] ?? '-';
                        $subName = $p['sub_item_check_name'] ?? '';
                        $itemFullStr = $iName . ($subName ? " ($subName)" : "");
                        $dataType = $p['data_type'] ?? 'Quantitative';
                        $procName = $p['process_name'] ?? '-';
                        $measItem = $p['measuring_item'] ?? 'Quantitative';
                        $lsl = ($p['lsl'] !== null && $p['lsl'] !== '') ? (float)$p['lsl'] : null;
                        $usl = ($p['usl'] !== null && $p['usl'] !== '') ? (float)$p['usl'] : null;
                        $targetVal = ($p['target_value'] !== null && $p['target_value'] !== '') ? (float)$p['target_value'] : null;
                        $targetZst = ($p['target_zst'] !== null && $p['target_zst'] !== '') ? $p['target_zst'] : '3';
                        $targetZlt = ($p['target_zlt'] !== null && $p['target_zlt'] !== '') ? $p['target_zlt'] : '4';

                        $specStr = ($lsl !== null && $usl !== null) ? "{$lsl} &ndash; {$usl}" : (($lsl !== null) ? "&ge; {$lsl}" : (($usl !== null) ? "&le; {$usl}" : "-"));
                        $centerSpec = ($targetVal !== null) ? number_format($targetVal, 2, '.', '') : (($lsl !== null && $usl !== null) ? number_format(($lsl + $usl) / 2, 2, '.', '') : '-');

                        $pKey = $p['parameter_key'] ?? $pid;
                        $samples = $measMap[$pKey]['all_samples'] ?? ($measMap[$pid]['all_samples'] ?? []);
                        $nCount = count($samples);
                        $maxVal = ($nCount > 0) ? max($samples) : null;
                        $minVal = ($nCount > 0) ? min($samples) : null;
                        $meanVal = ($nCount > 0) ? array_sum($samples) / $nCount : null;
                        $stdVal = null;
                        if ($nCount > 1) {
                            $variance = 0;
                            foreach ($samples as $sv) {
                                $variance += pow($sv - $meanVal, 2);
                            }
                            $stdVal = sqrt($variance / ($nCount - 1));
                        } elseif ($nCount === 1) {
                            $stdVal = 0;
                        }

                        $cp = null; $cpk = null; $zst = null; $zlt = null;
                        if ($stdVal > 0 && $lsl !== null && $usl !== null && $usl > $lsl) {
                            $cp = ($usl - $lsl) / (6 * $stdVal);
                            $cpu = ($usl - $meanVal) / (3 * $stdVal);
                            $cpl = ($meanVal - $lsl) / (3 * $stdVal);
                            $cpk = min($cpu, $cpl);
                            $zst = 3 * $cp;
                            $zlt = 3 * $cpk;
                        }

                        $maxDisplay = ($maxVal !== null) ? number_format($maxVal, 2, '.', '') : '-';
                        $minDisplay = ($minVal !== null) ? number_format($minVal, 2, '.', '') : '-';
                        $avgDisplay = ($meanVal !== null) ? number_format($meanVal, 2, '.', '') : '-';
                        $stdDisplay = ($stdVal !== null) ? number_format($stdVal, 2, '.', '') : '-';
                        $cpDisplay = ($cp !== null) ? number_format($cp, 2, '.', '') : '-';
                        $cpkDisplay = ($cpk !== null) ? number_format($cpk, 2, '.', '') : '-';
                        $zstDisplay = ($zst !== null) ? number_format($zst, 2, '.', '') : '-';
                        $zltDisplay = ($zlt !== null) ? number_format($zlt, 2, '.', '') : '-';

                        $cpColor = ($cp !== null && $cp < 1.0) ? '#dc2626' : (($cp !== null && $cp < 1.33) ? '#d97706' : '#16a34a');
                        $cpkColor = ($cpk !== null && $cpk < 1.0) ? '#dc2626' : (($cpk !== null && $cpk < 1.33) ? '#d97706' : '#16a34a');

                        $timeSlots = $line_labels[$lName] ?? $default_labels;
                        $dailyMaxRow = []; $dailyMinRow = []; $dailyZstRow = []; $dailyZltRow = [];

                        for ($d = 1; $d <= $daysInMonth; $d++) {
                            $dMax = $measMap[$pKey]['day_max'][$d] ?? ($measMap[$pid]['day_max'][$d] ?? null);
                            $dMin = $measMap[$pKey]['day_min'][$d] ?? ($measMap[$pid]['day_min'][$d] ?? null);
                            $dXbar = $measMap[$pKey]['day_xbar'][$d] ?? ($measMap[$pid]['day_xbar'][$d] ?? null);
                            $dStd = $measMap[$pKey]['day_std'][$d] ?? ($measMap[$pid]['day_std'][$d] ?? null);

                            if ($dMax === null || $dMin === null || $dXbar === null) {
                                $dayVals = [];
                                for ($sIdx = 1; $sIdx <= 10; $sIdx++) {
                                    $v = $measMap[$pKey]['grid'][$sIdx][$d] ?? ($measMap[$pid]['grid'][$sIdx][$d] ?? null);
                                    if ($v !== null && $v !== '' && is_numeric($v)) {
                                        $dayVals[] = (float)$v;
                                    }
                                }
                                if (!empty($dayVals)) {
                                    if ($dMax === null) $dMax = max($dayVals);
                                    if ($dMin === null) $dMin = min($dayVals);
                                    if ($dXbar === null) $dXbar = array_sum($dayVals) / count($dayVals);
                                }
                            }

                            $dailyMaxRow[$d] = ($dMax !== null) ? number_format($dMax, 2, '.', '') : '';
                            $dailyMinRow[$d] = ($dMin !== null) ? number_format($dMin, 2, '.', '') : '';

                            $dZstVal = null; $dZltVal = null;
                            if ($dStd !== null && $dStd > 0 && $dXbar !== null && $lsl !== null && $usl !== null && $usl > $lsl) {
                                $dCp = ($usl - $lsl) / (6 * $dStd);
                                $dCpu = ($usl - $dXbar) / (3 * $dStd);
                                $dCpl = ($dXbar - $lsl) / (3 * $dStd);
                                $dCpk = min($dCpu, $dCpl);
                                $dZstVal = round(3 * $dCp, 2);
                                $dZltVal = round(3 * $dCpk, 2);
                            }
                            $dailyZstRow[$d] = ($dZstVal !== null) ? number_format($dZstVal, 2, '.', '') : '';
                            $dailyZltRow[$d] = ($dZltVal !== null) ? number_format($dZltVal, 2, '.', '') : '';
                        }

                        echo '<div class="param-card">';
                        echo '<div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; color:#ffffff; padding:5px 10px; border-radius:4px; margin-bottom:8px; font-size:11px;">';
                        echo '<div><strong style="color:#38bdf8;">LINE ' . htmlspecialchars($sec['line_name']) . '</strong> &bull; <strong style="color:#ffffff;">' . htmlspecialchars($sec['section_name']) . '</strong></div>';
                        echo '<div>Compliance Bulanan: <strong style="color:' . $rateColor . '; font-size:12px;">' . $rateVal . '%</strong> &nbsp;|&nbsp; Hari Tidak Full: <strong style="color:#f87171;">' . $sec['total_days_incomplete'] . ' Hari</strong></div>';
                        echo '</div>';
                        echo '<div class="param-title">#' . ($pIdx + 1) . ' ' . htmlspecialchars($itemFullStr) . ' &nbsp;<span style="font-weight:normal; font-size:11px; color:#64748b;">(MODEL: ' . htmlspecialchars($mName) . ' | TYPE: ' . htmlspecialchars($dataType) . ')</span></div>';
                        echo '<div class="flex-layout">';

                        // Left Side: Detail Info & Data Summary
                        echo '<div class="left-box">';
                        echo '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px;">';
                        echo '<div style="font-size:10px; font-weight:bold; color:#0284c7; margin-bottom:6px; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">DETAIL INFORMATION</div>';
                        echo '<table class="info-table">';
                        echo '<tr><td style="color:#64748b; width:45%;">LINE</td><td style="font-weight:bold;">' . htmlspecialchars($lName) . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">SECTION</td><td style="font-weight:bold;">' . htmlspecialchars($sName) . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">MODEL NAME</td><td style="font-weight:bold; color:#0284c7;">' . htmlspecialchars($mName) . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">ITEM CHECK &amp; TYPE</td><td style="font-weight:bold;">' . htmlspecialchars($itemFullStr) . ' [' . htmlspecialchars($dataType) . ']</td></tr>';
                        echo '<tr><td style="color:#64748b;">PROCESS NAME</td><td>' . htmlspecialchars($procName) . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">SPEC (LSL - USL)</td><td style="font-weight:bold; color:#0369a1;">' . $specStr . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">MEASUREMENT</td><td>' . htmlspecialchars($measItem) . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">TARGET ZST / ZLT</td><td style="font-weight:bold;">' . $targetZst . ' / ' . $targetZlt . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">MONTH</td><td>' . $monthFormatted . '</td></tr>';
                        echo '</table></div>';

                        echo '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px;">';
                        echo '<div style="font-size:10px; font-weight:bold; color:#0284c7; margin-bottom:6px; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">DATA SUMMARY</div>';
                        echo '<table class="summary-table">';
                        echo '<tr><td style="color:#64748b; width:50%;">Sample Q\'ty(n)</td><td style="text-align:right; font-weight:bold;">' . $nCount . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">Center spec</td><td style="text-align:right; font-weight:bold;">' . $centerSpec . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">Maximum data</td><td style="text-align:right; font-weight:bold;">' . $maxDisplay . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">Minimum data</td><td style="text-align:right; font-weight:bold;">' . $minDisplay . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">Avg(X-bar)</td><td style="text-align:right; font-weight:bold;">' . $avgDisplay . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">Std deviation</td><td style="text-align:right; font-weight:bold;">' . $stdDisplay . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">Cp</td><td style="text-align:right; font-weight:bold; color:' . $cpColor . ';">' . $cpDisplay . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">Cpk</td><td style="text-align:right; font-weight:bold; color:' . $cpkColor . ';">' . $cpkDisplay . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">Zst</td><td style="text-align:right; font-weight:bold; color:#0284c7;">' . $zstDisplay . '</td></tr>';
                        echo '<tr><td style="color:#64748b;">Zlt</td><td style="color:#0369a1; text-align:right; font-weight:bold;">' . $zltDisplay . '</td></tr>';
                        echo '</table></div>';
                        echo '</div>'; // end left-box

                        // Right Side: Grid Table
                        echo '<div class="right-box">';
                        echo '<div style="font-size:10px; font-weight:bold; color:#0284c7; margin-bottom:6px;"><i class="fa-solid fa-table-cells"></i> MEASUREMENT DATA GRID (' . $monthFormatted . ')</div>';
                        echo '<table class="grid-table"><thead><tr>';
                        echo '<th style="background:#0f172a; color:#fff; width:55px;">Jam</th>';
                        for ($d = 1; $d <= $daysInMonth; $d++) {
                            echo '<th style="background:#0284c7; color:#fff; min-width:24px;">' . $d . '</th>';
                        }
                        echo '</tr></thead><tbody>';

                        foreach ($timeSlots as $slotIdx => $tLabel) {
                            $seqNo = $slotIdx + 1;
                            echo '<tr>';
                            echo '<td style="background:#f1f5f9; font-weight:bold; color:#334155;">' . htmlspecialchars($tLabel) . '</td>';
                            for ($d = 1; $d <= $daysInMonth; $d++) {
                                $val = $p['grid_data'][$seqNo][$d] ?? null;
                                $valStr = '';
                                if ($val !== null && $val !== '') {
                                    if (is_numeric($val)) {
                                        $fVal = (float)$val;
                                        $valStr = (floor($fVal) == $fVal) ? (string)(int)$fVal : number_format($fVal, 2, '.', '');
                                    } else {
                                        $valStr = htmlspecialchars($val);
                                    }
                                }
                                $valUpper = strtoupper(trim((string)$val));
                                
                                $cellContent = '';
                                if ($valUpper === 'OK') {
                                    $cellContent = '<span style="background:#dcfce7; color:#15803d; font-weight:bold; padding:0 2px; border-radius:2px; display:inline-block; white-space:nowrap;">OK</span>';
                                } elseif ($valUpper === 'NG') {
                                    $cellContent = '<span style="background:#fee2e2; color:#b91c1c; font-weight:bold; padding:0 2px; border-radius:2px; display:inline-block; white-space:nowrap;">NG</span>';
                                } else {
                                    $isOos = ($val !== null && $val !== '' && is_numeric($val) && (($lsl !== null && (float)$val < $lsl) || ($usl !== null && (float)$val > $usl)));
                                    if ($isOos) {
                                        $cellContent = '<span style="background:#fee2e2; color:#b91c1c; font-weight:bold; padding:0 2px; border-radius:2px; display:inline-block; white-space:nowrap;">' . $valStr . '</span>';
                                    } else {
                                        $cellContent = $valStr;
                                    }
                                }
                                echo '<td>' . $cellContent . '</td>';
                            }
                            echo '</tr>';
                        }

                        if (!empty($p['is_quantitative'])) {
                            // Max Data
                            echo '<tr style="background:#f0fdf4;">';
                            echo '<td style="background:#dcfce7; font-weight:bold; color:#166534;">Max Data</td>';
                            for ($d = 1; $d <= $daysInMonth; $d++) {
                                echo '<td style="font-weight:bold; color:#15803d;">' . $dailyMaxRow[$d] . '</td>';
                            }
                            echo '</tr>';

                            // Min Data
                            echo '<tr style="background:#f0fdf4;">';
                            echo '<td style="background:#dcfce7; font-weight:bold; color:#166534;">Min Data</td>';
                            for ($d = 1; $d <= $daysInMonth; $d++) {
                                echo '<td style="font-weight:bold; color:#15803d;">' . $dailyMinRow[$d] . '</td>';
                            }
                            echo '</tr>';

                            // Zst
                            echo '<tr style="background:#f0f9ff;">';
                            echo '<td style="background:#e0f2fe; font-weight:bold; color:#0369a1;">Zst</td>';
                            for ($d = 1; $d <= $daysInMonth; $d++) {
                                echo '<td style="font-weight:bold; color:#0284c7;">' . $dailyZstRow[$d] . '</td>';
                            }
                            echo '</tr>';

                            // Zlt
                            echo '<tr style="background:#f0f9ff;">';
                            echo '<td style="background:#e0f2fe; font-weight:bold; color:#0369a1;">Zlt</td>';
                            for ($d = 1; $d <= $daysInMonth; $d++) {
                                echo '<td style="font-weight:bold; color:#0369a1;">' . $dailyZltRow[$d] . '</td>';
                            }
                            echo '</tr>';
                        }

                        echo '</tbody></table>';
                        echo '</div>'; // end right-box

                        echo '</div></div>'; // end flex-layout & param-card
                    }
                }
            }

            echo '</div>'; // end sec-card
        }

        echo '<script>setTimeout(function(){ window.print(); }, 600);</script>';
        echo '</body></html>';
        exit;
    }

    if ($format === 'excel') {
        // Output Executive Excel Spreadsheet Header
        $filename = "DTC_Monthly_Performance_" . ($targetSection ? str_replace(' ', '_', $targetSection) : "All_Stations") . "_{$month}.xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="utf-8">';
        echo '<style>';
        echo '  body { font-family: "Segoe UI", Arial, sans-serif; color: #1e293b; background-color: #ffffff; }';
        echo '  col.col-label { width: 140px !important; width: 105pt !important; mso-width-source: userset !important; mso-width-alt: 5250 !important; }';
        echo '  col.col-val { width: 180px !important; width: 135pt !important; mso-width-source: userset !important; mso-width-alt: 6750 !important; }';
        echo '  col.col-spacer { width: 20px !important; width: 15pt !important; mso-width-source: userset !important; mso-width-alt: 750 !important; }';
        echo '  col.col-jam { width: 80px !important; width: 60pt !important; mso-width-source: userset !important; mso-width-alt: 3000 !important; }';
        echo '  col.col-day { width: 50px !important; width: 38pt !important; mso-width-source: userset !important; mso-width-alt: 1900 !important; }';
        echo '  td.col-day, th.col-day { width: 50px !important; width: 38pt !important; min-width: 50px !important; max-width: 50px !important; mso-width-source: userset !important; mso-width-alt: 1900 !important; text-align: center; }';
        echo '  .title-banner { background-color: #0f172a; color: #ffffff; padding: 16px 20px; border-bottom: 4px solid #0284c7; }';
        echo '  .meta-table { border-collapse: collapse; width: 100%; margin-bottom: 15px; font-size: 12px; }';
        echo '  .meta-table td { padding: 6px 12px; border: 1px solid #cbd5e1; }';
        echo '  .meta-label { background-color: #f1f5f9; font-weight: bold; color: #475569; width: 220px; }';
        echo '  .sec-header { background-color: #1e293b; color: #38bdf8; font-size: 14px; font-weight: bold; padding: 10px 14px; border: 1px solid #0f172a; }';
        echo '  .data-table { border-collapse: collapse; width: 100%; font-size: 11px; margin-bottom: 25px; }';
        echo '  .data-table th { background-color: #0284c7; color: #ffffff; font-weight: bold; padding: 6px; border: 1px solid #0369a1; text-align: center; }';
        echo '  .data-table td { padding: 5px 6px; border: 1px solid #cbd5e1; text-align: center; }';
        echo '  .card-header-table { border-collapse: collapse; width: 100%; margin-bottom: 10px; font-size: 11px; border: 1px solid #cbd5e1; }';
        echo '  .card-header-table td, .card-header-table th { padding: 5px 8px; border: 1px solid #cbd5e1; }';
        echo '  .badge-full { background-color: #dcfce7; color: #15803d; font-weight: bold; padding: 3px 8px; border-radius: 4px; }';
        echo '  .badge-inc { background-color: #fee2e2; color: #b91c1c; font-weight: bold; padding: 3px 8px; border-radius: 4px; }';
        echo '  .badge-off { background-color: #f1f5f9; color: #64748b; padding: 3px 8px; border-radius: 4px; }';
        echo '</style>';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Station Performance</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '</head><body>';

        // Main Title Header Banner
        $totalExcelCols = $daysInMonth + 4;
        echo '<table style="border-collapse:collapse; margin-bottom:15px; table-layout:fixed;">';
        echo '<colgroup>';
        echo '<col class="col-label" width="140" style="width: 105pt;">';
        echo '<col class="col-val" width="180" style="width: 135pt;">';
        echo '<col class="col-spacer" width="20" style="width: 15pt;">';
        echo '<col class="col-jam" width="80" style="width: 60pt;">';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            echo '<col class="col-day" width="50" style="width: 38pt;">';
        }
        echo '</colgroup>';
        echo '<tr>';
        echo '<td colspan="' . $totalExcelCols . '" style="background-color:#0f172a; color:#38bdf8; padding:12px; font-weight:bold; font-size:16px; text-transform:uppercase; text-align:left;">';
        echo 'SYSTEM DIGITAL TIME CHECK (DTC)<br>';
        echo '<span style="font-size:12px; color:#ffffff; font-weight:normal;">LAPORAN PERFORMANCE BULANAN &amp; RAW MEASUREMENT DATA GRID</span>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        echo '<br>';

        foreach ($sectionData as $secKey => $sec) {
            // Skip section if there are no filled slots / measurements at all
            if (empty($targetParamId) && (empty($sec['total_filled_month']) || $sec['total_filled_month'] <= 0)) {
                continue;
            }

            // 2. RENDER PER-PARAMETER HEADER + MEASUREMENT DATA GRID
            if (!empty($sec['parameters'])) {
                $measuredParams = array_filter($sec['parameters'], function($p) use ($targetParamId) {
                    if (!empty($targetParamId)) return true;
                    if (!empty($p['n_count']) && intval($p['n_count']) > 0) return true;
                    if (!empty($p['grid_data'])) {
                        foreach ($p['grid_data'] as $seq => $days) {
                            foreach ($days as $day => $v) {
                                if ($v !== null && trim((string)$v) !== '') return true;
                            }
                        }
                    }
                    return false;
                });

                if (!empty($measuredParams)) {
                    foreach ($measuredParams as $pIdx => $p) {
                    $pid = $p['parameter_id'];
                    $lName = $p['line_name'] ?? $sec['line_name'];
                    $sName = $p['section_name'] ?? $sec['section_name'];
                    $mName = $p['model_name'] ?? '-';
                    $iName = $p['item_check_name'] ?? '-';
                    $subName = $p['sub_item_check_name'] ?? '';
                    $itemFullStr = $iName . ($subName ? " ($subName)" : "");
                    $dataType = $p['data_type'] ?? 'Quantitative';
                    $procName = $p['process_name'] ?? '-';
                    $measItem = $p['measuring_item'] ?? 'Quantitative';
                    $lsl = ($p['lsl'] !== null && $p['lsl'] !== '') ? (float)$p['lsl'] : null;
                    $usl = ($p['usl'] !== null && $p['usl'] !== '') ? (float)$p['usl'] : null;
                    $targetVal = ($p['target_value'] !== null && $p['target_value'] !== '') ? (float)$p['target_value'] : null;
                    $targetZst = ($p['target_zst'] !== null && $p['target_zst'] !== '') ? $p['target_zst'] : '3';
                    $targetZlt = ($p['target_zlt'] !== null && $p['target_zlt'] !== '') ? $p['target_zlt'] : '4';

                    $specStr = ($lsl !== null && $usl !== null) ? "{$lsl} &ndash; {$usl}" : (($lsl !== null) ? "&ge; {$lsl}" : (($usl !== null) ? "&le; {$usl}" : "-"));
                    $centerSpec = ($targetVal !== null) ? number_format($targetVal, 2, '.', '') : (($lsl !== null && $usl !== null) ? number_format(($lsl + $usl) / 2, 2, '.', '') : '-');

                    // Calculate overall stats for Data Summary box
                    $samples = $measMap[$pid]['all_samples'] ?? [];
                    $nCount = count($samples);
                    $maxVal = ($nCount > 0) ? max($samples) : null;
                    $minVal = ($nCount > 0) ? min($samples) : null;
                    $meanVal = ($nCount > 0) ? array_sum($samples) / $nCount : null;
                    $stdVal = null;
                    if ($nCount > 1) {
                        $variance = 0;
                        foreach ($samples as $sv) {
                            $variance += pow($sv - $meanVal, 2);
                        }
                        $stdVal = sqrt($variance / ($nCount - 1));
                    } elseif ($nCount === 1) {
                        $stdVal = 0;
                    }

                    $cp = null; $cpk = null; $zst = null; $zlt = null;
                    if ($stdVal > 0 && $lsl !== null && $usl !== null && $usl > $lsl) {
                        $cp = ($usl - $lsl) / (6 * $stdVal);
                        $cpu = ($usl - $meanVal) / (3 * $stdVal);
                        $cpl = ($meanVal - $lsl) / (3 * $stdVal);
                        $cpk = min($cpu, $cpl);
                        $zst = 3 * $cp;
                        $zlt = 3 * $cpk;
                    }

                    $maxDisplay = ($maxVal !== null) ? number_format($maxVal, 2, '.', '') : '-';
                    $minDisplay = ($minVal !== null) ? number_format($minVal, 2, '.', '') : '-';
                    $avgDisplay = ($meanVal !== null) ? number_format($meanVal, 2, '.', '') : '-';
                    $stdDisplay = ($stdVal !== null) ? number_format($stdVal, 2, '.', '') : '-';
                    $cpDisplay = ($cp !== null) ? number_format($cp, 2, '.', '') : '-';
                    $cpkDisplay = ($cpk !== null) ? number_format($cpk, 2, '.', '') : '-';
                    $zstDisplay = ($zst !== null) ? number_format($zst, 2, '.', '') : '-';
                    $zltDisplay = ($zlt !== null) ? number_format($zlt, 2, '.', '') : '-';

                    $cpColor = ($cp !== null && $cp < 1.0) ? '#dc2626' : (($cp !== null && $cp < 1.33) ? '#d97706' : '#16a34a');
                    $cpkColor = ($cpk !== null && $cpk < 1.0) ? '#dc2626' : (($cpk !== null && $cpk < 1.33) ? '#d97706' : '#16a34a');

                    // 1. RENDER PARAMETER CONTAINER TABLE IN EXCEL
                    // Left Side (Col A & B): Detail Information (top) + Data Summary (bottom)
                    // Right Side (Col D to AI): Measurement Data Grid
                    $timeSlots = $line_labels[$lName] ?? $default_labels;
                    $dailyMaxRow = []; $dailyMinRow = []; $dailyZstRow = []; $dailyZltRow = [];

                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $dMax = $measMap[$pid]['day_max'][$d] ?? null;
                        $dMin = $measMap[$pid]['day_min'][$d] ?? null;
                        $dXbar = $measMap[$pid]['day_xbar'][$d] ?? null;
                        $dStd = $measMap[$pid]['day_std'][$d] ?? null;

                        if ($dMax === null || $dMin === null || $dXbar === null) {
                            $dayVals = [];
                            for ($sIdx = 1; $sIdx <= 10; $sIdx++) {
                                $v = $measMap[$pid]['grid'][$sIdx][$d] ?? null;
                                if ($v !== null && $v !== '' && is_numeric($v)) {
                                    $dayVals[] = (float)$v;
                                }
                            }
                            if (!empty($dayVals)) {
                                if ($dMax === null) $dMax = max($dayVals);
                                if ($dMin === null) $dMin = min($dayVals);
                                if ($dXbar === null) $dXbar = array_sum($dayVals) / count($dayVals);
                            }
                        }

                        $dailyMaxRow[$d] = ($dMax !== null) ? ((floor($dMax) == $dMax) ? (string)(int)$dMax : number_format($dMax, 2, '.', '')) : '';
                        $dailyMinRow[$d] = ($dMin !== null) ? ((floor($dMin) == $dMin) ? (string)(int)$dMin : number_format($dMin, 2, '.', '')) : '';

                        $dZstVal = null; $dZltVal = null;
                        if ($dStd !== null && $dStd > 0 && $dXbar !== null && $lsl !== null && $usl !== null && $usl > $lsl) {
                            $dCp = ($usl - $lsl) / (6 * $dStd);
                            $dCpu = ($usl - $dXbar) / (3 * $dStd);
                            $dCpl = ($dXbar - $lsl) / (3 * $dStd);
                            $dCpk = min($dCpu, $dCpl);
                            $dZstVal = round(3 * $dCp, 2);
                            $dZltVal = round(3 * $dCpk, 2);
                        }
                        $dailyZstRow[$d] = ($dZstVal !== null) ? number_format($dZstVal, 2, '.', '') : '';
                        $dailyZltRow[$d] = ($dZltVal !== null) ? number_format($dZltVal, 2, '.', '') : '';
                    }

                    echo '<table style="border-collapse: collapse; margin-top: 15px; margin-bottom: 25px; table-layout: fixed; font-family: Arial, sans-serif; font-size: 9pt;">';
                    echo '<colgroup>';
                    echo '<col class="col-label" width="140" style="width: 105pt;">'; // Col A: Left label
                    echo '<col class="col-val" width="180" style="width: 135pt;">'; // Col B: Left val
                    echo '<col class="col-spacer" width="20" style="width: 15pt;">';   // Col C: Blank spacer
                    echo '<col class="col-jam" width="80" style="width: 60pt;">';   // Col D: Grid Jam
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        echo '<col class="col-day" width="50" style="width: 38pt;">'; // Col E..AI: Grid Days 1..31 (50px width)
                    }
                    echo '</colgroup>';

                    // Row 0: Title Header
                    echo '<tr>';
                    echo '<th colspan="2" style="background-color: #0284c7; color: #ffffff; text-align: left; font-size: 10pt; font-weight: bold; padding: 6px 8px; font-family: Arial, sans-serif;">#' . ($pIdx + 1) . ' ' . htmlspecialchars($itemFullStr) . '</th>';
                    echo '<td class="col-spacer" style="border: none;">&nbsp;</td>';
                    echo '<th colspan="' . ($daysInMonth + 1) . '" style="background-color: #0f172a; color: #38bdf8; text-align: left; font-size: 10pt; font-weight: bold; padding: 6px 8px; font-family: Arial, sans-serif;">MEASUREMENT DATA GRID (' . date('F Y', strtotime($month . '-01')) . ')</th>';
                    echo '</tr>';

                    // Row 1: Section Sub-headers
                    echo '<tr>';
                    echo '<th colspan="2" style="background-color: #0f172a; color: #38bdf8; text-align: left; font-size: 9.5pt; font-weight: bold; padding: 5px 8px; font-family: Arial, sans-serif;">DETAIL INFORMATION</th>';
                    echo '<td class="col-spacer" style="border: none;">&nbsp;</td>';
                    echo '<th class="col-jam" width="80" style="background-color: #0f172a; color: #ffffff; width: 60pt; min-width: 60pt; max-width: 60pt; text-align: center; font-size: 9.5pt; font-weight: bold; font-family: Arial, sans-serif;">Jam</th>';
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        echo '<th class="col-day" width="50" style="background-color: #0284c7; color: #ffffff; width: 38pt; min-width: 38pt; max-width: 38pt; text-align: center; font-size: 9.5pt; font-weight: bold; font-family: Arial, sans-serif;">' . $d . '</th>';
                    }
                    echo '</tr>';

                    // Prepare left rows (Detail Information & Data Summary)
                    $leftItems = [
                        ['lbl' => 'LINE', 'val' => htmlspecialchars($lName)],
                        ['lbl' => 'SECTION', 'val' => htmlspecialchars($sName)],
                        ['lbl' => 'MODEL NAME', 'val' => htmlspecialchars($mName), 'val_style' => 'color:#0284c7;'],
                        ['lbl' => 'ITEM CHECK & TYPE', 'val' => htmlspecialchars($itemFullStr) . ' [' . htmlspecialchars($dataType) . ']'],
                        ['lbl' => 'PROCESS NAME', 'val' => htmlspecialchars($procName)],
                        ['lbl' => 'SPEC (LSL - USL)', 'val' => $specStr, 'val_style' => 'color:#0369a1;'],
                        ['lbl' => 'MEASUREMENT', 'val' => htmlspecialchars($measItem)],
                        ['lbl' => 'TARGET ZST / ZLT', 'val' => $targetZst . ' / ' . $targetZlt, 'val_style' => 'mso-number-format:\'\@\';'],
                        ['lbl' => 'MONTH', 'val' => date('F Y', strtotime($month . '-01'))],
                        ['lbl' => 'DATA SUMMARY', 'val' => '', 'is_summary_title' => true],
                        ['lbl' => 'Sample Q\'ty(n)', 'val' => $nCount],
                        ['lbl' => 'Center spec', 'val' => $centerSpec],
                        ['lbl' => 'Maximum data', 'val' => $maxDisplay],
                        ['lbl' => 'Minimum data', 'val' => $minDisplay],
                        ['lbl' => 'Avg(X-bar)', 'val' => $avgDisplay],
                        ['lbl' => 'Std deviation', 'val' => $stdDisplay],
                        ['lbl' => 'Cp', 'val' => $cpDisplay, 'val_style' => 'color:' . $cpColor . ';'],
                        ['lbl' => 'Cpk', 'val' => $cpkDisplay, 'val_style' => 'color:' . $cpkColor . ';'],
                        ['lbl' => 'Zst', 'val' => $zstDisplay, 'val_style' => 'color:#0284c7;'],
                        ['lbl' => 'Zlt', 'val' => $zltDisplay, 'val_style' => 'color:#0369a1;']
                    ];

                    // Prepare right rows (Measurement Data Grid)
                    $rightRows = [];
                    foreach ($timeSlots as $slotIdx => $tLabel) {
                        $seqNo = $slotIdx + 1;
                        $rightRows[] = ['type' => 'slot', 'label' => $tLabel, 'seq' => $seqNo];
                    }
                    if (!empty($p['is_quantitative'])) {
                        $rightRows[] = ['type' => 'summary_max', 'label' => 'Max Data'];
                        $rightRows[] = ['type' => 'summary_min', 'label' => 'Min Data'];
                        $rightRows[] = ['type' => 'summary_zst', 'label' => 'Zst'];
                        $rightRows[] = ['type' => 'summary_zlt', 'label' => 'Zlt'];
                    }

                    $maxRows = max(count($leftItems), count($rightRows));

                    for ($rIdx = 0; $rIdx < $maxRows; $rIdx++) {
                        echo '<tr>';
                        
                        // Left Cell Output (Col A & B)
                        if (isset($leftItems[$rIdx])) {
                            $item = $leftItems[$rIdx];
                            if (!empty($item['is_summary_title'])) {
                                echo '<th colspan="2" style="background-color: #0f172a; color: #38bdf8; text-align: left; font-size: 9.5pt; font-weight: bold; padding: 5px 8px; font-family: Arial, sans-serif;">DATA SUMMARY</th>';
                            } else {
                                $vStyle = $item['val_style'] ?? '';
                                echo '<td class="col-label" style="background-color: #f8fafc; font-weight: bold; color: #475569; padding: 4px 6px; border: 1px solid #cbd5e1; font-size: 9pt; font-family: Arial, sans-serif;">' . $item['lbl'] . '</td>';
                                echo '<td class="col-val" style="font-weight: bold; padding: 4px 6px; border: 1px solid #cbd5e1; font-size: 9pt; font-family: Arial, sans-serif; ' . $vStyle . '">' . $item['val'] . '</td>';
                            }
                        } else {
                            echo '<td class="col-label" style="border: none;">&nbsp;</td><td class="col-val" style="border: none;">&nbsp;</td>';
                        }

                        // Col C Spacer
                        echo '<td class="col-spacer" style="border: none;">&nbsp;</td>';

                        // Right Cell Output (Col D to AI)
                        if (isset($rightRows[$rIdx])) {
                            $r = $rightRows[$rIdx];
                            if ($r['type'] === 'slot') {
                                $seqNo = $r['seq'];
                                echo '<td class="col-jam" width="80" style="background-color: #f1f5f9; font-weight: bold; color: #334155; text-align: center; border: 1px solid #cbd5e1; width: 60pt; min-width: 60pt; max-width: 60pt; font-size: 9.5pt; font-family: Arial, sans-serif;">' . htmlspecialchars($r['label']) . '</td>';
                                for ($d = 1; $d <= $daysInMonth; $d++) {
                                    $val = $p['grid_data'][$seqNo][$d] ?? null;
                                    $valStr = '';
                                    if ($val !== null && $val !== '') {
                                        if (is_numeric($val)) {
                                            $fVal = (float)$val;
                                            $valStr = (floor($fVal) == $fVal) ? (string)(int)$fVal : number_format($fVal, 2, '.', '');
                                        } else {
                                            $valStr = htmlspecialchars($val);
                                        }
                                    }
                                    $valUpper = strtoupper(trim((string)$val));
                                    $bgStyle = '';
                                    if ($valUpper === 'OK') {
                                        $bgStyle = 'background-color:#dcfce7; color:#15803d; font-weight:bold;';
                                    } elseif ($valUpper === 'NG') {
                                        $bgStyle = 'background-color:#fee2e2; color:#b91c1c; font-weight:bold;';
                                    } else {
                                        $isOos = ($val !== null && $val !== '' && is_numeric($val) && (($lsl !== null && (float)$val < $lsl) || ($usl !== null && (float)$val > $usl)));
                                        if ($isOos) $bgStyle = 'background-color:#fee2e2; color:#b91c1c; font-weight:bold;';
                                    }
                                    $cellVal = ($valStr !== '') ? $valStr : '&nbsp;';
                                    echo '<td class="col-day" width="50" style="width: 38pt; min-width: 38pt; max-width: 38pt; text-align: center; border: 1px solid #cbd5e1; font-size: 9.5pt; font-family: Arial, sans-serif; mso-number-format:\'\@\'; ' . $bgStyle . '">' . $cellVal . '</td>';
                                }
                            } elseif ($r['type'] === 'summary_max') {
                                echo '<td class="col-jam" width="80" style="background-color: #dcfce7; font-weight: bold; color: #166534; text-align: center; border: 1px solid #cbd5e1; width: 60pt; min-width: 60pt; max-width: 60pt; font-size: 9.5pt; font-family: Arial, sans-serif;">Max Data</td>';
                                for ($d = 1; $d <= $daysInMonth; $d++) {
                                    $vDisplay = ($dailyMaxRow[$d] !== '') ? $dailyMaxRow[$d] : '&nbsp;';
                                    echo '<td class="col-day" width="50" style="font-weight: bold; color: #15803d; width: 38pt; min-width: 38pt; max-width: 38pt; text-align: center; border: 1px solid #cbd5e1; font-size: 9.5pt; font-family: Arial, sans-serif;">' . $vDisplay . '</td>';
                                }
                            } elseif ($r['type'] === 'summary_min') {
                                echo '<td class="col-jam" width="80" style="background-color: #dcfce7; font-weight: bold; color: #166534; text-align: center; border: 1px solid #cbd5e1; width: 60pt; min-width: 60pt; max-width: 60pt; font-size: 9.5pt; font-family: Arial, sans-serif;">Min Data</td>';
                                for ($d = 1; $d <= $daysInMonth; $d++) {
                                    $vDisplay = ($dailyMinRow[$d] !== '') ? $dailyMinRow[$d] : '&nbsp;';
                                    echo '<td class="col-day" width="50" style="font-weight: bold; color: #15803d; width: 38pt; min-width: 38pt; max-width: 38pt; text-align: center; border: 1px solid #cbd5e1; font-size: 9.5pt; font-family: Arial, sans-serif;">' . $vDisplay . '</td>';
                                }
                            } elseif ($r['type'] === 'summary_zst') {
                                echo '<td class="col-jam" width="80" style="background-color: #e2e8f0; font-weight: bold; color: #1e293b; text-align: center; border: 1px solid #cbd5e1; width: 60pt; min-width: 60pt; max-width: 60pt; font-size: 9.5pt; font-family: Arial, sans-serif;">Zst</td>';
                                for ($d = 1; $d <= $daysInMonth; $d++) {
                                    $vDisplay = ($dailyZstRow[$d] !== '') ? $dailyZstRow[$d] : '&nbsp;';
                                    echo '<td class="col-day" width="50" style="font-weight: bold; color: #0284c7; width: 38pt; min-width: 38pt; max-width: 38pt; text-align: center; border: 1px solid #cbd5e1; font-size: 9.5pt; font-family: Arial, sans-serif;">' . $vDisplay . '</td>';
                                }
                            } elseif ($r['type'] === 'summary_zlt') {
                                echo '<td class="col-jam" width="80" style="background-color: #e2e8f0; font-weight: bold; color: #1e293b; text-align: center; border: 1px solid #cbd5e1; width: 60pt; min-width: 60pt; max-width: 60pt; font-size: 9.5pt; font-family: Arial, sans-serif;">Zlt</td>';
                                for ($d = 1; $d <= $daysInMonth; $d++) {
                                    $vDisplay = ($dailyZltRow[$d] !== '') ? $dailyZltRow[$d] : '&nbsp;';
                                    echo '<td class="col-day" width="50" style="font-weight: bold; color: #0369a1; width: 38pt; min-width: 38pt; max-width: 38pt; text-align: center; border: 1px solid #cbd5e1; font-size: 9.5pt; font-family: Arial, sans-serif;">' . $vDisplay . '</td>';
                                }
                            }
                        } else {
                            echo '<td class="col-spacer" style="border: none;">&nbsp;</td>';
                            for ($d = 1; $d <= $daysInMonth; $d++) {
                                echo '<td class="col-day" width="50" style="width: 38pt; min-width: 38pt; max-width: 38pt; border: none;">&nbsp;</td>';
                            }
                        }

                        echo '</tr>';
                    }

                    echo '</table>';
                    echo '<br>';
                    echo '<br><hr style="border: 0; border-top: 1px dashed #cbd5e1;"><br>';
                }
            }
        }

            echo '<br><hr style="border: 0; border-top: 2px solid #0f172a;"><br>';
        }

        echo '</body></html>';
        exit;
    } else {
        // Return JSON format
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'month' => $month,
            'month_formatted' => date('F Y', strtotime($month . '-01')),
            'data' => array_values($sectionData)
        ]);
    }

} catch (Exception $e) {
    if ($format === 'excel') {
        echo "Error generating report: " . $e->getMessage();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>

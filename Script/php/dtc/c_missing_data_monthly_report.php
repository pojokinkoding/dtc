<?php
// c_missing_data_monthly_report.php - Monthly Station Performance Reporting & Excel Export
require_once __DIR__ . '/../../../config/config.php';

$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if ($userRole !== 'admin' && strpos($userRole, 'supervisor') === false) {
    $format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : (isset($_GET['export']) && $_GET['export'] === 'excel' ? 'excel' : 'json');
    if ($format === 'excel') {
        die("Unauthorized access. Data Monitoring reports are restricted to Supervisor and Admin.");
    }
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Data Monitoring is restricted to Supervisor and Admin.'
    ]);
    exit;
}

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$targetSection = isset($_GET['section_name']) ? trim($_GET['section_name']) : '';
$targetLine = isset($_GET['line_name']) ? trim($_GET['line_name']) : '';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : (isset($_GET['export']) && $_GET['export'] === 'excel' ? 'excel' : 'json');

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
        WHERE p.target_month = :month
        " . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
        " . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
    ";
    
    $paramsExec = [':month' => $month];
    if (!empty($targetSection)) {
        $sqlParams .= " AND UPPER(COALESCE(p.section_name, spec.section_name)) = UPPER(:sec_target) ";
        $paramsExec[':sec_target'] = $targetSection;
    }
    if (!empty($targetLine)) {
        $sqlParams .= " AND UPPER(COALESCE(p.line_name, spec.line_name)) = UPPER(:line_target) ";
        $paramsExec[':line_target'] = $targetLine;
    }

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

    // Aggregate monthly report data per section
    $sectionData = [];

    foreach ($parameters as $param) {
        $pid = $param['parameter_id'];
        $line_name = $param['line_name'] ?? 'REF 01';
        $section_name = $param['section_name'] ?? 'GENERAL';
        $model_name = $param['model_name'] ?? '';

        $sKey = strtolower(trim($line_name)) . '|' . strtolower(trim($section_name));
        $kKey = $sKey . '|' . strtolower(trim($model_name));

        if (!empty($sectionHasRunning[$sKey])) {
            if (!isset($runningSet[$kKey])) {
                continue;
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

    // Finalize metrics per section
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
    }
    unset($sec);

    if ($format === 'excel') {
        // Output Executive Excel Spreadsheet Header
        $filename = "DTC_Monthly_Performance_" . ($targetSection ? str_replace(' ', '_', $targetSection) : "All_Stations") . "_{$month}.xls";
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        // Pre-fetch all measurements in target month for matrix grid & data summary calculations
        $sqlAllMeas = "
            SELECT s.parameter_id,
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
            WHERE DATE_FORMAT(s.inspection_date, '%Y-%m') = :month AND s.is_active = 1
            ORDER BY s.inspection_date ASC, m.sample_sequence ASC
        ";
        $stmtAllMeas = $conn->prepare($sqlAllMeas);
        $stmtAllMeas->execute([':month' => $month]);
        $allMeas = $stmtAllMeas->fetchAll(PDO::FETCH_ASSOC);

        $measMap = [];
        foreach ($allMeas as $rMeas) {
            $pid = $rMeas['parameter_id'];
            $day = (int)$rMeas['day_num'];
            $seq = (int)$rMeas['sample_sequence'];
            $val = $rMeas['sample_value'];
            $lbl = trim($rMeas['sample_label'] ?? '');
            $lblClean = preg_replace('/^Jam\s+/i', '', $lbl);

            $measMap[$pid]['grid'][$seq][$day] = $val;
            $measMap[$pid]['lbl'][$seq] = $lblClean;
            if ($rMeas['max_value'] !== null) $measMap[$pid]['day_max'][$day] = (float)$rMeas['max_value'];
            if ($rMeas['min_value'] !== null) $measMap[$pid]['day_min'][$day] = (float)$rMeas['min_value'];
            if ($rMeas['x_bar'] !== null) $measMap[$pid]['day_xbar'][$day] = (float)$rMeas['x_bar'];
            if ($rMeas['std_dev'] !== null) $measMap[$pid]['day_std'][$day] = (float)$rMeas['std_dev'];

            if ($val !== null && $val !== '' && is_numeric($val)) {
                $measMap[$pid]['all_samples'][] = (float)$val;
            }
        }

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="utf-8">';
        echo '<style>';
        echo '  body { font-family: "Segoe UI", Arial, sans-serif; color: #1e293b; background-color: #ffffff; }';
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
        echo '<div class="title-banner">';
        echo '<h2 style="margin:0; font-size: 18px; color: #38bdf8; text-transform: uppercase;">SYSTEM DIGITAL TIME CHECK (DTC)</h2>';
        echo '<h3 style="margin: 4px 0 0 0; font-size: 14px; color: #ffffff; font-weight: normal;">LAPORAN PERFORMANCE BULANAN &amp; RAW MEASUREMENT DATA GRID</h3>';
        echo '</div>';
        echo '<br>';

        foreach ($sectionData as $secKey => $sec) {
            // RENDER PER-PARAMETER HEADER (GAMBAR 1) + MEASUREMENT DATA GRID (GAMBAR 2)
            if (!empty($sec['parameters'])) {
                foreach ($sec['parameters'] as $p) {
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

                    // 1. RENDER HEADER (GAMBAR 1)
                    echo '<table style="width:100%; border-collapse:collapse; margin-top:15px; margin-bottom:10px;">';
                    echo '<tr>';
                    
                    // Left Column: Detail Information
                    echo '<td style="width:55%; vertical-align:top; padding-right:10px;">';
                    echo '<table class="card-header-table">';
                    echo '<tr><th colspan="2" style="background-color:#0f172a; color:#38bdf8; text-align:left; font-size:12px;">DETAIL INFORMATION</th></tr>';
                    echo '<tr><td style="background-color:#f8fafc; font-weight:bold; color:#475569; width:35%;">LINE</td><td style="font-weight:bold;">' . htmlspecialchars($lName) . '</td></tr>';
                    echo '<tr><td style="background-color:#f8fafc; font-weight:bold; color:#475569;">SECTION</td><td style="font-weight:bold;">' . htmlspecialchars($sName) . '</td></tr>';
                    echo '<tr><td style="background-color:#f8fafc; font-weight:bold; color:#475569;">MODEL NAME</td><td style="font-weight:bold; color:#0284c7;">' . htmlspecialchars($mName) . '</td></tr>';
                    echo '<tr><td style="background-color:#f8fafc; font-weight:bold; color:#475569;">ITEM CHECK &amp; DATA TYPE</td><td style="font-weight:bold;">' . htmlspecialchars($itemFullStr) . ' [' . htmlspecialchars($dataType) . ']</td></tr>';
                    echo '<tr><td style="background-color:#f8fafc; font-weight:bold; color:#475569;">PROCESS NAME</td><td>' . htmlspecialchars($procName) . '</td></tr>';
                    echo '<tr><td style="background-color:#f8fafc; font-weight:bold; color:#475569;">SPEC (LSL - USL)</td><td style="font-weight:bold; color:#0369a1;">' . $specStr . '</td></tr>';
                    echo '<tr><td style="background-color:#f8fafc; font-weight:bold; color:#475569;">MEASUREMENT</td><td>' . htmlspecialchars($measItem) . '</td></tr>';
                    echo '<tr><td style="background-color:#f8fafc; font-weight:bold; color:#475569;">TARGET ZST / ZLT</td><td style="font-weight:bold; mso-number-format:\'\@\';">' . $targetZst . ' / ' . $targetZlt . '</td></tr>';
                    echo '<tr><td style="background-color:#f8fafc; font-weight:bold; color:#475569;">MONTH</td><td style="font-weight:bold; color:#0f172a;">' . date('F Y', strtotime($month . '-01')) . '</td></tr>';
                    echo '</table>';
                    echo '</td>';

                    // Right Column: Data Summary
                    echo '<td style="width:45%; vertical-align:top;">';
                    echo '<table class="card-header-table">';
                    echo '<tr><th colspan="4" style="background-color:#0f172a; color:#38bdf8; text-align:left; font-size:12px;">DATA SUMMARY</th></tr>';
                    echo '<tr>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155; width:30%;">Sample Q\'ty(n)</td><td style="text-align:right; font-weight:bold;">' . $nCount . '</td>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155; width:30%;">Center spec</td><td style="text-align:right; font-weight:bold;">' . $centerSpec . '</td>';
                    echo '</tr>';
                    echo '<tr>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155;">Maximum data</td><td style="text-align:right; font-weight:bold;">' . $maxDisplay . '</td>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155;">Cp</td><td style="text-align:right; font-weight:bold; color:' . $cpColor . ';">' . $cpDisplay . '</td>';
                    echo '</tr>';
                    echo '<tr>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155;">Minimum data</td><td style="text-align:right; font-weight:bold;">' . $minDisplay . '</td>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155;">Cpk</td><td style="text-align:right; font-weight:bold; color:' . $cpkColor . ';">' . $cpkDisplay . '</td>';
                    echo '</tr>';
                    echo '<tr>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155;">Avg(X-bar)</td><td style="text-align:right; font-weight:bold;">' . $avgDisplay . '</td>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155;">Zst</td><td style="text-align:right; font-weight:bold; color:#0284c7;">' . $zstDisplay . '</td>';
                    echo '</tr>';
                    echo '<tr>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155;">Std deviation</td><td style="text-align:right; font-weight:bold;">' . $stdDisplay . '</td>';
                    echo '<td style="background-color:#f1f5f9; font-weight:bold; color:#334155;">Zlt</td><td style="text-align:right; font-weight:bold; color:#0369a1;">' . $zltDisplay . '</td>';
                    echo '</tr>';
                    echo '</table>';
                    echo '</td>';

                    echo '</tr>';
                    echo '</table>';

                    // 2. RENDER MEASUREMENT DATA GRID (GAMBAR 2)
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

                    echo '<h4 style="margin-top: 10px; margin-bottom: 6px; color: #0284c7;">MEASUREMENT DATA GRID</h4>';
                    echo '<table class="data-table">';
                    echo '<thead><tr>';
                    echo '<th style="background-color: #0f172a; color: #ffffff; width: 70px;">Jam</th>';
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        echo '<th style="background-color: #0284c7; color: #ffffff; min-width: 32px;">' . $d . '</th>';
                    }
                    echo '</tr></thead><tbody>';

                    // TIME SLOT ROWS
                    foreach ($timeSlots as $slotIdx => $tLabel) {
                        $seqNo = $slotIdx + 1;
                        echo '<tr>';
                        echo '<td style="background-color: #f1f5f9; font-weight: bold; color: #334155;">' . htmlspecialchars($tLabel) . '</td>';
                        for ($d = 1; $d <= $daysInMonth; $d++) {
                            $val = $measMap[$pid]['grid'][$seqNo][$d] ?? null;
                            $valStr = ($val !== null && $val !== '') ? (is_numeric($val) ? number_format((float)$val, 2, '.', '') : htmlspecialchars($val)) : '';
                            $isOos = ($val !== null && $val !== '' && is_numeric($val) && (($lsl !== null && (float)$val < $lsl) || ($usl !== null && (float)$val > $usl)));
                            $bgStyle = $isOos ? 'background-color:#fee2e2; color:#b91c1c; font-weight:bold;' : '';
                            echo '<td style="' . $bgStyle . '">' . $valStr . '</td>';
                        }
                        echo '</tr>';
                    }

                    // SUMMARY ROWS AT BOTTOM OF GRID (GAMBAR 2)
                    // Max Data
                    echo '<tr style="background-color: #f0fdf4;">';
                    echo '<td style="background-color: #dcfce7; font-weight: bold; color: #166534;">Max Data</td>';
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        echo '<td style="font-weight: bold; color: #15803d;">' . $dailyMaxRow[$d] . '</td>';
                    }
                    echo '</tr>';

                    // Min Data
                    echo '<tr style="background-color: #f0fdf4;">';
                    echo '<td style="background-color: #dcfce7; font-weight: bold; color: #166534;">Min Data</td>';
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        echo '<td style="font-weight: bold; color: #15803d;">' . $dailyMinRow[$d] . '</td>';
                    }
                    echo '</tr>';

                    // Zst
                    echo '<tr style="background-color: #f8fafc;">';
                    echo '<td style="background-color: #e2e8f0; font-weight: bold; color: #1e293b;">Zst</td>';
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        echo '<td style="font-weight: bold; color: #0284c7;">' . $dailyZstRow[$d] . '</td>';
                    }
                    echo '</tr>';

                    // Zlt
                    echo '<tr style="background-color: #f8fafc;">';
                    echo '<td style="background-color: #e2e8f0; font-weight: bold; color: #1e293b;">Zlt</td>';
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        echo '<td style="font-weight: bold; color: #0369a1;">' . $dailyZltRow[$d] . '</td>';
                    }
                    echo '</tr>';

                    echo '</tbody></table>';
                    echo '<br><hr style="border: 0; border-top: 1px dashed #cbd5e1;"><br>';
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

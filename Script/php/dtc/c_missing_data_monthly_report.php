<?php
// c_missing_data_monthly_report.php - Monthly Station Performance Reporting & Excel Export
require_once '../../../config/config.php';

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
                'missed_items_by_date' => []
            ];
        }
        $sectionData[$secKey]['total_parameters']++;

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

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="utf-8">';
        echo '<style>';
        echo '  body { font-family: "Segoe UI", Arial, sans-serif; color: #1e293b; background-color: #ffffff; }';
        echo '  .title-banner { background-color: #0f172a; color: #ffffff; padding: 16px 20px; border-bottom: 4px solid #0284c7; }';
        echo '  .meta-table { border-collapse: collapse; width: 100%; margin-bottom: 20px; font-size: 12px; }';
        echo '  .meta-table td { padding: 6px 12px; border: 1px solid #cbd5e1; }';
        echo '  .meta-label { background-color: #f1f5f9; font-weight: bold; color: #475569; width: 220px; }';
        echo '  .sec-header { background-color: #1e293b; color: #38bdf8; font-size: 14px; font-weight: bold; padding: 10px 14px; border: 1px solid #0f172a; }';
        echo '  .data-table { border-collapse: collapse; width: 100%; font-size: 11px; margin-bottom: 25px; }';
        echo '  .data-table th { background-color: #0284c7; color: #ffffff; font-weight: bold; padding: 8px; border: 1px solid #0369a1; text-align: center; }';
        echo '  .data-table td { padding: 6px 8px; border: 1px solid #cbd5e1; text-align: center; }';
        echo '  .missed-table th { background-color: #881337; color: #ffffff; border: 1px solid #4c0519; }';
        echo '  .badge-full { background-color: #dcfce7; color: #15803d; font-weight: bold; padding: 3px 8px; border-radius: 4px; }';
        echo '  .badge-inc { background-color: #fee2e2; color: #b91c1c; font-weight: bold; padding: 3px 8px; border-radius: 4px; }';
        echo '  .badge-off { background-color: #f1f5f9; color: #64748b; padding: 3px 8px; border-radius: 4px; }';
        echo '</style>';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Station Performance</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '</head><body>';

        // Main Title Header Banner
        echo '<div class="title-banner">';
        echo '<h2 style="margin:0; font-size: 18px; color: #38bdf8; text-transform: uppercase;">SYSTEM DIGITAL TIME CHECK (DTC)</h2>';
        echo '<h3 style="margin: 4px 0 0 0; font-size: 14px; color: #ffffff; font-weight: normal;">LAPORAN PERFORMANCE BULANAN TIME CHECK PER STASIUN</h3>';
        echo '</div>';
        echo '<br>';

        foreach ($sectionData as $secKey => $sec) {
            echo '<table class="meta-table">';
            echo '<tr><td colspan="4" class="sec-header">LINE: ' . htmlspecialchars($sec['line_name']) . ' &nbsp;&bull;&nbsp; STASIUN: ' . htmlspecialchars($sec['section_name']) . '</td></tr>';
            echo '<tr>';
            echo '<td class="meta-label">Bulan Laporan</td><td><strong>' . date('F Y', strtotime($month . '-01')) . '</strong></td>';
            echo '<td class="meta-label">Total Active Parameters</td><td>' . $sec['total_parameters'] . ' Parameters</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td class="meta-label">Total Slot Expected (Sebulan)</td><td>' . number_format($sec['total_expected_month']) . ' Slots</td>';
            echo '<td class="meta-label">Total Slot Diisi (Sebulan)</td><td>' . number_format($sec['total_filled_month']) . ' Slots</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td class="meta-label">Total Slot Kosong (Belum Diisi)</td><td style="color: ' . ($sec['total_missing_month'] > 0 ? '#dc2626' : '#16a34a') . '; font-weight: bold;">' . number_format($sec['total_missing_month']) . ' Slots Kosong</td>';
            echo '<td class="meta-label">PERSENTASE SUMMARY BULANAN</td><td style="color: ' . ($sec['monthly_compliance_rate'] >= 90 ? '#15803d' : '#dc2626') . '; font-weight: bold; font-size: 14px;">' . number_format($sec['monthly_compliance_rate'], 1) . '% COMPLIANCE</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td class="meta-label">Jumlah Hari Tidak Full</td><td style="color: ' . ($sec['total_days_incomplete'] > 0 ? '#dc2626' : '#16a34a') . '; font-weight: bold;">' . $sec['total_days_incomplete'] . ' Hari Incomplete</td>';
            echo '<td class="meta-label">Waktu Export</td><td>' . date('d M Y H:i:s') . ' WIB</td>';
            echo '</tr>';
            echo '</table>';

            echo '<h4 style="margin-top: 10px; margin-bottom: 6px; color: #0284c7;">1. Persentase Pengisian Time Check Harian (% Daily Filling Rate)</h4>';
            echo '<table class="data-table">';
            echo '<thead><tr>';
            echo '<th style="width: 120px;">Tanggal</th><th style="width: 100px;">Status Hari</th><th style="width: 130px;">Total Slot Expected</th><th style="width: 130px;">Total Slot Diisi</th><th style="width: 150px;">Completion Rate (%)</th><th style="width: 150px;">Status Pengisian</th>';
            echo '</tr></thead><tbody>';

            $rowIdx = 0;
            foreach ($sec['days'] as $dateStr => $dayObj) {
                $rowIdx++;
                $bgStyle = ($rowIdx % 2 === 0) ? 'background-color: #f8fafc;' : 'background-color: #ffffff;';
                $statusBadge = $dayObj['is_weekend'] ? '<span class="badge-off">Weekend</span>' : ($dayObj['is_full'] ? '<span class="badge-full">FULL (100%)</span>' : '<span class="badge-inc">BELUM FULL</span>');
                $rateColor = $dayObj['is_full'] ? '#15803d' : '#b91c1c';

                echo "<tr style=\"{$bgStyle}\">";
                echo '<td style="font-weight: bold;">' . date('d M Y', strtotime($dateStr)) . '</td>';
                echo '<td>' . ($dayObj['is_weekend'] ? 'Sabtu/Minggu' : 'Hari Kerja') . '</td>';
                echo '<td>' . number_format($dayObj['expected_slots']) . '</td>';
                echo '<td>' . number_format($dayObj['filled_slots']) . '</td>';
                echo "<td style=\"font-weight: bold; color: {$rateColor}; font-size: 12px;\">" . number_format($dayObj['completion_rate'], 1) . '%</td>';
                echo '<td>' . $statusBadge . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            echo '<h4 style="margin-top: 15px; margin-bottom: 6px; color: #991b1b;">2. Rincian Item Check / Parameter yang Tidak Diisi (Missed Items Breakdown)</h4>';
            if (!empty($sec['missed_items_by_date'])) {
                echo '<table class="data-table missed-table">';
                echo '<thead><tr>';
                echo '<th style="width: 40px;">No</th><th style="width: 110px;">Tanggal</th><th style="width: 80px;">Line</th><th style="width: 110px;">Stasiun</th><th style="width: 120px;">Running Model</th><th style="width: 140px;">Proses</th><th style="width: 180px;">Item Check Name</th><th style="width: 150px;">Sub-Item Check</th><th style="width: 90px;">Data Type</th><th style="width: 90px;">Slot Expected</th><th style="width: 90px;">Slot Diisi</th><th style="width: 100px;">Slot Kosong</th>';
                echo '</tr></thead><tbody>';

                $itemNo = 0;
                foreach ($sec['missed_items_by_date'] as $dateStr => $items) {
                    foreach ($items as $item) {
                        $itemNo++;
                        $bgStyle = ($itemNo % 2 === 0) ? 'background-color: #fff5f5;' : 'background-color: #ffffff;';
                        echo "<tr style=\"{$bgStyle}\">";
                        echo '<td>' . $itemNo . '</td>';
                        echo '<td style="font-weight: bold; color: #0f172a;">' . date('d M Y', strtotime($dateStr)) . '</td>';
                        echo '<td>' . htmlspecialchars($item['line_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['section_name']) . '</td>';
                        echo '<td><strong style="color: #0284c7;">' . htmlspecialchars($item['model_name'] ?: '-') . '</strong></td>';
                        echo '<td style="text-align: left;">' . htmlspecialchars($item['process_name']) . '</td>';
                        echo '<td style="text-align: left; font-weight: bold; color: #0f172a;">' . htmlspecialchars($item['item_check_name']) . '</td>';
                        echo '<td style="text-align: left;">' . htmlspecialchars($item['sub_item_check_name'] ?: '-') . '</td>';
                        echo '<td><span style="font-weight:bold; color:#475569;">' . htmlspecialchars($item['data_type']) . '</span></td>';
                        echo '<td>' . $item['expected_slots'] . '</td>';
                        echo '<td>' . $item['filled_slots'] . '</td>';
                        echo '<td style="background-color: #fee2e2; color: #b91c1c; font-weight: bold;">' . $item['missing_slots'] . ' Slot Kosong</td>';
                        echo '</tr>';
                    }
                }
                echo '</tbody></table>';
            } else {
                echo '<p style="color: #16a34a; font-style: italic; font-weight: bold; background: #f0fdf4; padding: 10px; border: 1px solid #bbf7d0; border-radius: 4px;">Sempurna! Seluruh item check di stasiun ini telah terisi full 100% pada semua hari kerja.</p>';
            }
            echo '<br><hr style="border: 0; border-top: 1px dashed #cbd5e1;"><br>';
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
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>

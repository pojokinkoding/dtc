<?php
// c_missing_data_trending.php - Monthly Daily Overdue Percentage Trending per Section
require_once '../../../config/config.php';
header('Content-Type: application/json');

$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if ($userRole !== 'admin' && strpos($userRole, 'supervisor') === false && strpos($userRole, 'management') === false) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Data Monitoring is restricted to Supervisor, Admin, and Management.'
    ]);
    exit;
}

$prodHour = (int)date('H');
$prodToday = ($prodHour < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$prodMonth = date('Y-m', strtotime($prodToday));

$month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : $prodMonth;
$lineFilter = isset($_GET['line_name']) ? trim($_GET['line_name']) : '';
$sectionFilter = isset($_GET['section_name']) ? trim($_GET['section_name']) : '';

$nowH = $prodHour;
$nowM = (int)date('i');

if (!function_exists('isSlotPastTrending')) {
    function isSlotPastTrending($timeStr, $nowH, $nowM) {
        if (!$timeStr) return false;
        $tp = explode(':', trim($timeStr));
        if (count($tp) < 2) return false;
        $h = (int)$tp[0];
        $m = (int)$tp[1];
        if ($h >= 24) $h = $h - 24;

        $slotMinutesFrom7 = ($h < 7 ? $h + 24 : $h) * 60 + $m;
        $nowMinutesFrom7 = ($nowH < 7 ? $nowH + 24 : $nowH) * 60 + $nowM;

        return $slotMinutesFrom7 <= $nowMinutesFrom7;
    }
}

if (!function_exists('isSlotBeforeCreationTrending')) {
    function isSlotBeforeCreationTrending($slotTime, $createdAtStr, $dateStr) {
        if (empty($createdAtStr) || empty($dateStr) || empty($slotTime)) return false;

        $parts = explode(' ', trim($createdAtStr));
        $createdDate = $parts[0] ?? '';
        $createdTime = $parts[1] ?? '';

        // Only apply creation-time cutoff when created_at falls in the same target month;
        // backfilled/imported historical months (created_at in a later month) must not be skipped.
        if (substr($createdDate, 0, 7) !== substr($dateStr, 0, 7)) return false;
        if ($createdDate < $dateStr) return false;
        if ($createdDate > $dateStr) return true;

        $tp = explode(':', $createdTime);
        $cH = (int)($tp[0] ?? 0);
        $cM = (int)($tp[1] ?? 0);

        if ($cH < 7) return false;

        $createdMinutesFrom7 = ($cH - 7) * 60 + $cM;

        $sParts = explode(':', trim($slotTime));
        $sH = (int)($sParts[0] ?? 0);
        $sM = (int)($sParts[1] ?? 0);
        if ($sH >= 24) $sH -= 24;
        $curSlotMins = (($sH < 7 ? $sH + 24 : $sH) - 7) * 60 + $sM;

        return $createdMinutesFrom7 > $curSlotMins;
    }
}

try {
    $conn = getDBConnection();

    // 1. Fetch active running models for target month
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
    foreach ($activeRMs as $rm) {
        $lName = strtolower(trim($rm['line_name']));
        $sName = strtolower(trim($rm['section_name']));
        $mName = strtolower(trim($rm['model_name']));
        $k = $lName . '|' . $sName . '|' . $mName;
        $runningSet[$k] = $rm['created_at'] ?? true;
    }

    // 2. Fetch parameters for target month
    $sqlParams = "
        SELECT p.parameter_id, p.target_month,
               COALESCE(p.model_name, spec.model_name) as model_name,
               COALESCE(p.section_name, spec.section_name) as section_name,
               COALESCE(p.line_name, spec.line_name) as line_name,
               COALESCE(p.measuring_item, spec.measuring_item) as measuring_item
        FROM dtc_master_parameters p
        LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
        WHERE p.target_month = :month
        " . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
        " . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
    ";
    $paramsExec = [':month' => $month];
    if ($lineFilter !== '') {
        $sqlParams .= " AND COALESCE(p.line_name, spec.line_name) = :line_filter ";
        $paramsExec[':line_filter'] = $lineFilter;
    }
    if ($sectionFilter !== '') {
        $sqlParams .= " AND COALESCE(p.section_name, spec.section_name) = :section_filter ";
        $paramsExec[':section_filter'] = $sectionFilter;
    }

    $stmtParams = $conn->prepare($sqlParams);
    $stmtParams->execute($paramsExec);
    $parameters = $stmtParams->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch time labels per line
    $stmtLabel = $conn->prepare("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE 'time_matrix_labels_%'");
    $stmtLabel->execute();
    $lineLabels = [];
    while ($rowSetting = $stmtLabel->fetch(PDO::FETCH_ASSOC)) {
        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
        $decoded = normalizeTimeLabels(json_decode($val, true));
        if ($decoded) {
            $ln = str_replace('time_matrix_labels_', '', $rowSetting['setting_key']);
            $lineLabels[$ln] = $decoded;
        }
    }
    $defaultLabels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '00:30', '02:30', '04:30'];

    // 4. Qualitative checkpoints check
    $stmtHasCp = $conn->query("SELECT DISTINCT parameter_id FROM dtc_checkpoints");
    $paramsWithCheckpoints = array_flip($stmtHasCp->fetchAll(PDO::FETCH_COLUMN));

    // 5. Fetch all sessions & filled slot labels for target month
    $sqlSessions = "
        SELECT s.parameter_id,
               DATE_FORMAT(s.inspection_date, '%Y-%m-%d') as inspection_date,
               s.is_closed,
               (SELECT GROUP_CONCAT(DISTINCT m.sample_label)
                FROM dtc_measurements m
                WHERE m.session_id = s.session_id
                  AND m.sample_value IS NOT NULL
                  AND TRIM(m.sample_value) != '') as filled_labels
        FROM dtc_inspection_sessions s
        WHERE DATE_FORMAT(s.inspection_date, '%Y-%m') = :month
          AND s.is_active = 1
    ";
    $stmtSessions = $conn->prepare($sqlSessions);
    $stmtSessions->execute([':month' => $month]);
    $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

    $sessionMap = [];
    $dateHasAnyActivity = [];
    foreach ($sessions as $sess) {
        $pid = (int)$sess['parameter_id'];
        $dStr = $sess['inspection_date'];
        $filled = !empty($sess['filled_labels']) ? explode(',', $sess['filled_labels']) : [];
        $sessionMap[$pid][$dStr] = [
            'is_closed' => (int)$sess['is_closed'],
            'filled' => array_flip($filled)
        ];
        if ((int)$sess['is_closed'] === 1 || !empty($filled)) {
            $dateHasAnyActivity[$dStr] = true;
        }
    }

    $daysInMonth = (int)date('t', strtotime($month . '-01'));
    $sectionStats = [];

    foreach ($parameters as $param) {
        $pid = (int)$param['parameter_id'];
        $lineName = $param['line_name'] ?? 'REF 01';
        $sectionName = $param['section_name'] ?? 'DEFAULT';
        $modelName = $param['model_name'] ?? '';

        $k = strtolower(trim($lineName)) . '|' . strtolower(trim($sectionName)) . '|' . strtolower(trim($modelName));
        if (!isset($runningSet[$k])) {
            continue;
        }

        $measuringItem = strtolower(trim($param['measuring_item'] ?? 'quantitative'));
        if ($measuringItem === 'qualitative' && !isset($paramsWithCheckpoints[$pid])) {
            continue;
        }

        $secKey = $lineName . '___' . $sectionName;
        if (!isset($sectionStats[$secKey])) {
            $sectionStats[$secKey] = [
                'line_name' => $lineName,
                'section_name' => $sectionName,
                'label' => $lineName . ' • ' . $sectionName,
                'total_params' => 0,
                'days' => []
            ];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $sectionStats[$secKey]['days'][$d] = [
                    'expected' => 0,
                    'overdue' => 0,
                    'filled' => 0
                ];
            }
        }
        $sectionStats[$secKey]['total_params']++;

        $currentLabels = $lineLabels[$lineName] ?? $defaultLabels;
        $modelCreatedAt = is_string($runningSet[$k] ?? null) ? $runningSet[$k] : null;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);

            // Do not count future dates beyond today's production date
            if ($dateStr > $prodToday) {
                continue;
            }

            $ts = strtotime($dateStr);
            $isWeekend = (date('N', $ts) >= 6);
            // Skip weekend days unless production actually ran on that weekend date
            if ($isWeekend && empty($dateHasAnyActivity[$dateStr])) {
                continue;
            }

            $sessInfo = $sessionMap[$pid][$dateStr] ?? null;
            $isClosed = $sessInfo ? ($sessInfo['is_closed'] === 1) : false;
            $filledMap = $sessInfo ? $sessInfo['filled'] : [];

            foreach ($currentLabels as $slotTime) {
                if (isSlotBeforeCreationTrending($slotTime, $modelCreatedAt, $dateStr)) {
                    continue;
                }

                // Match c_missing_data.php Inspection Activities denominator: all active slots for the day
                $sectionStats[$secKey]['days'][$d]['expected']++;

                $isDue = ($dateStr < $prodToday) || ($dateStr === $prodToday && isSlotPastTrending($slotTime, $nowH, $nowM));
                if ($isClosed || isset($filledMap[$slotTime])) {
                    $sectionStats[$secKey]['days'][$d]['filled']++;
                } elseif ($isDue) {
                    $sectionStats[$secKey]['days'][$d]['overdue']++;
                }
            }
        }
    }

    // Build response series & ranking
    $sectionsOutput = [];
    $dailyOverall = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dailyOverall[$d] = ['expected' => 0, 'overdue' => 0];
    }

    foreach ($sectionStats as $secKey => $sec) {
        $totExp = 0;
        $totOverdue = 0;
        $worstDay = null;
        $worstPct = -1;
        $dailySeries = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            $ts = strtotime($dateStr);
            $isWeekend = (date('N', $ts) >= 6);
            $isFuture = ($dateStr > $prodToday);

            $exp = $sec['days'][$d]['expected'];
            $ovd = $sec['days'][$d]['overdue'];
            $filled = $sec['days'][$d]['filled'];
            // 1 full completed day with 0 filled slots is treated as holiday/libur and excluded from %
            $isOffDay = ($dateStr < $prodToday && $exp > 0 && $filled === 0);

            if ($isFuture || $exp === 0 || $isOffDay) {
                $pct = null;
            } else {
                $totExp += $exp;
                $totOverdue += $ovd;
                $dailyOverall[$d]['expected'] += $exp;
                $dailyOverall[$d]['overdue'] += $ovd;

                $pct = round(($ovd / $exp) * 100, 1);
                if ($pct > $worstPct) {
                    $worstPct = $pct;
                    $worstDay = $d;
                }
            }

            $dailySeries[] = [
                'day' => $d,
                'date' => $dateStr,
                'is_weekend' => $isWeekend,
                'is_future' => $isFuture,
                'is_off' => $isOffDay,
                'expected' => $exp,
                'overdue' => $ovd,
                'filled' => $filled,
                'pct' => $pct
            ];
        }

        $monthlyPct = $totExp > 0 ? round(($totOverdue / $totExp) * 100, 1) : 0;

        $sectionsOutput[] = [
            'line_name' => $sec['line_name'],
            'section_name' => $sec['section_name'],
            'label' => $sec['label'],
            'total_params' => $sec['total_params'],
            'total_expected' => $totExp,
            'total_overdue' => $totOverdue,
            'monthly_overdue_pct' => $monthlyPct,
            'worst_day' => $worstDay,
            'worst_day_pct' => ($worstPct >= 0 ? $worstPct : 0),
            'daily' => $dailySeries
        ];
    }

    // Sort from worst overdue percentage (paling parah) to lowest
    usort($sectionsOutput, function ($a, $b) {
        if ($b['monthly_overdue_pct'] == $a['monthly_overdue_pct']) {
            return $b['total_overdue'] <=> $a['total_overdue'];
        }
        return ($b['monthly_overdue_pct'] <=> $a['monthly_overdue_pct']);
    });

    $overallDailySeries = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $exp = $dailyOverall[$d]['expected'];
        $ovd = $dailyOverall[$d]['overdue'];
        $overallDailySeries[] = [
            'day' => $d,
            'expected' => $exp,
            'overdue' => $ovd,
            'pct' => $exp > 0 ? round(($ovd / $exp) * 100, 1) : null
        ];
    }

    echo json_encode([
        'status' => 'success',
        'month' => $month,
        'prod_today' => $prodToday,
        'days_count' => $daysInMonth,
        'sections' => $sectionsOutput,
        'overall_daily' => $overallDailySeries
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}


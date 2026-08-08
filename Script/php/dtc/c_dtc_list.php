<?php
// c_dtc_list.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    $prodHour = (int)date('H');
    $prodToday = ($prodHour < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
    $currentMonth = date('Y-m');
    $period = isset($_GET['period']) ? trim($_GET['period']) : 'all';

    // Endpoint to fetch distinct past months for dropdown filter
    // Endpoint to fetch distinct past months for dropdown filter
    if (isset($_GET['action']) && $_GET['action'] === 'get_months') {
        $stmtMonths = $conn->prepare("
            SELECT DISTINCT p.target_month,
                   DATE_FORMAT(STR_TO_DATE(CONCAT(p.target_month, '-01'), '%Y-%m-%d'), '%b %Y') as label
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.target_month IS NOT NULL AND p.target_month < :current_month
            " . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
            " . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
            ORDER BY p.target_month DESC
        ");
        $stmtMonths->execute([':current_month' => $currentMonth]);
        $months = $stmtMonths->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "months" => $months]);
        exit;
    }

    $wherePeriod = "";
    $queryParams = [];

    if ($period === 'current') {
        $wherePeriod = " AND p.target_month = :current_month ";
        $queryParams[':current_month'] = $currentMonth;
    } else if ($period === 'history') {
        $wherePeriod = " AND (p.target_month < :current_month OR p.target_month IS NULL) ";
        $queryParams[':current_month'] = $currentMonth;
    }

    $selected_month = trim($_GET['month'] ?? '');
    if (!empty($selected_month)) {
        $wherePeriod .= " AND p.target_month = :sel_month ";
        $queryParams[':sel_month'] = $selected_month;
    }

    $line_filter = trim($_GET['line'] ?? '');
    if (!empty($line_filter)) {
        $wherePeriod .= " AND (p.line_name = :line OR spec.line_name = :line) ";
        $queryParams[':line'] = $line_filter;
    }

    $model_filter = trim($_GET['model'] ?? '');
    if (!empty($model_filter)) {
        $wherePeriod .= " AND (p.model_name = :model OR spec.model_name = :model) ";
        $queryParams[':model'] = $model_filter;
    }

    $running_models = trim($_GET['running_models'] ?? '');
    if (!empty($running_models)) {
        $models_arr = array_map('trim', explode(',', $running_models));
        $models_arr = array_filter($models_arr);
        if (!empty($models_arr)) {
            $in_placeholders = [];
            foreach ($models_arr as $idx => $m_val) {
                $ph = ':rmod_' . $idx;
                $in_placeholders[] = $ph;
                $queryParams[$ph] = $m_val;
            }
            $wherePeriod .= " AND (p.model_name IN (" . implode(',', $in_placeholders) . ") OR spec.model_name IN (" . implode(',', $in_placeholders) . ")) ";
        }
    }

    $section_filter = trim($_GET['section'] ?? '');
    if (!empty($section_filter)) {
        $wherePeriod .= " AND (p.section_name = :sec OR spec.section_name = :sec) ";
        $queryParams[':sec'] = $section_filter;
    }

    $type_filter = trim($_GET['type'] ?? '');
    if (!empty($type_filter)) {
        $wherePeriod .= " AND (p.data_type = :type OR spec.data_type = :type) ";
        $queryParams[':type'] = $type_filter;
    }

    $search_value = '';
    if (isset($_GET['search']['value']) && trim($_GET['search']['value']) !== '') {
        $search_value = trim($_GET['search']['value']);
    } else if (isset($_GET['search_val']) && trim($_GET['search_val']) !== '') {
        $search_value = trim($_GET['search_val']);
    }

    $whereSearch = "";
    if (!empty($search_value)) {
        $whereSearch = " AND (p.model_name LIKE :kw OR p.item_check_name LIKE :kw OR p.process_name LIKE :kw OR spec.model_name LIKE :kw OR spec.item_check_name LIKE :kw) ";
        $queryParams[':kw'] = '%' . $search_value . '%';
    }

    $is_server_side = isset($_GET['draw']) || isset($_GET['start']);

    // Count Total Records
    $sqlTotal = "SELECT COUNT(*) FROM dtc_master_parameters p LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id WHERE 1=1 " . $wherePeriod . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)');
    $stmtTotal = $conn->prepare($sqlTotal);
    $paramsNoKw = $queryParams;
    unset($paramsNoKw[':kw']);
    $stmtTotal->execute($paramsNoKw);
    $recordsTotal = (int)$stmtTotal->fetchColumn();

    // Count Filtered Records
    $sqlFiltered = "SELECT COUNT(*) FROM dtc_master_parameters p LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id WHERE 1=1 " . $wherePeriod . $whereSearch . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)');
    $stmtFiltered = $conn->prepare($sqlFiltered);
    $stmtFiltered->execute($queryParams);
    $recordsFiltered = (int)$stmtFiltered->fetchColumn();

    // Paging Limit & Offset
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    if ($length <= 0) $length = 10;

    $limitClause = "";
    if ($is_server_side) {
        $limitClause = " LIMIT " . $start . ", " . $length;
    }

    $sql = "SELECT 
                DATE_FORMAT(STR_TO_DATE(CONCAT(p.target_month, '-01'), '%Y-%m-%d'), '%b %Y') as inspection_month,
                p.target_month as raw_month,
                p.parameter_id,
                COALESCE(p.line_name, spec.line_name) as line_name,
                COALESCE(p.section_name, spec.section_name) as section_name,
                COALESCE(p.process_name, spec.process_name) as process_name,
                COALESCE(p.model_name, spec.model_name) as model_name,
                COALESCE(p.item_check_name, spec.item_check_name) as item_check_name,
                COALESCE(p.sub_item_check_name, spec.sub_item_check_name) as sub_item_check_name,
                COALESCE(p.data_type, spec.data_type) as data_type,
                COALESCE(p.measuring_item, spec.measuring_item) as measuring_item,
                COALESCE(p.lsl, spec.lsl) as lsl,
                COALESCE(p.usl, spec.usl) as usl,
                COALESCE(p.target_value, spec.target_value) as target_value,
                (SELECT AVG(m.sample_value) FROM dtc_measurements m JOIN dtc_inspection_sessions s ON m.session_id = s.session_id WHERE s.parameter_id = p.parameter_id AND s.is_active = 1 AND m.sample_value IS NOT NULL AND m.sample_value REGEXP '^[0-9.]+$') as pop_mean,
                (SELECT STDDEV_SAMP(m.sample_value) FROM dtc_measurements m JOIN dtc_inspection_sessions s ON m.session_id = s.session_id WHERE s.parameter_id = p.parameter_id AND s.is_active = 1 AND m.sample_value IS NOT NULL AND m.sample_value REGEXP '^[0-9.]+$') as pop_std,
                COALESCE((
                    SELECT MAX(u.full_name) 
                    FROM dtc_inspection_sessions s 
                    JOIN dtc_users u ON s.operator_id = u.user_id 
                    WHERE s.parameter_id = p.parameter_id AND s.is_active = 1
                ), 'System Admin') as operator_name
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE 1=1 " . $wherePeriod . $whereSearch . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
                " . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
            ORDER BY p.target_month DESC, p.parameter_id DESC " . $limitClause;
            
    $stmt = $conn->prepare($sql);
    $stmt->execute($queryParams);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load time matrix labels for overdue calculation
    $stmtLbl = $conn->prepare("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE 'time_matrix_labels_%'");
    $stmtLbl->execute();
    $tLineLabels = [];
    while ($rowLbl = $stmtLbl->fetch(PDO::FETCH_ASSOC)) {
        $val = is_resource($rowLbl['setting_value']) ? stream_get_contents($rowLbl['setting_value']) : $rowLbl['setting_value'];
        $decoded = json_decode($val, true);
        if ($decoded) {
            $ln = str_replace('time_matrix_labels_', '', $rowLbl['setting_key']);
            $tLineLabels[$ln] = $decoded;
        }
    }
    $defaultSlots = ['07:30','09:40','12:40','14:40','16:40','18:40','20:05','22:30','24:30','02:30'];
    $nowH = (int)date('H');
    $nowM = (int)date('i');

    // Load today's filled sample sequences per parameter
    $stmtFilled = $conn->prepare("
        SELECT s.parameter_id, m.sample_sequence
        FROM dtc_inspection_sessions s
        JOIN dtc_measurements m ON s.session_id = m.session_id
        WHERE s.inspection_date = :today AND m.sample_value IS NOT NULL AND m.sample_value != ''
    ");
    $stmtFilled->execute([':today' => $prodToday]);
    $filledMap = [];
    while ($rf = $stmtFilled->fetch(PDO::FETCH_ASSOC)) {
        $filledMap[$rf['parameter_id']][(int)$rf['sample_sequence']] = true;
    }

    // Load set of parameter IDs that have at least one checkpoint defined
    $stmtHasCp = $conn->query("SELECT DISTINCT parameter_id FROM dtc_checkpoints");
    $paramsWithCheckpoints = array_flip($stmtHasCp->fetchAll(PDO::FETCH_COLUMN));

    if (!function_exists('isSlotPast')) {
        function isSlotPast($timeStr, $nowH, $nowM) {
            if (!$timeStr) return false;
            $tp = explode(':', trim($timeStr));
            if (count($tp) < 2) return false;
            $h = (int)$tp[0];
            $m = (int)$tp[1];
            if ($h >= 24) $h = $h - 24;

            // Production day runs from 07:00 AM to 07:00 AM next morning.
            // Hours < 7 (e.g. 00:30, 02:30, 04:30) belong to night shift (next calendar day morning, +24h).
            $slotMinutesFrom7 = ($h < 7 ? $h + 24 : $h) * 60 + $m;
            $nowMinutesFrom7 = ($nowH < 7 ? $nowH + 24 : $nowH) * 60 + $nowM;

            return $slotMinutesFrom7 <= $nowMinutesFrom7;
        }
    }

    foreach ($results as &$row) {
        $row['monthly_zst'] = null;
        $row['monthly_zlt'] = null;
        if (isset($row['pop_std']) && $row['pop_std'] > 0 && isset($row['pop_mean']) && $row['lsl'] !== null && $row['usl'] !== null) {
            $mean = (float)$row['pop_mean'];
            $std = (float)$row['pop_std'];
            $lsl = (float)$row['lsl'];
            $usl = (float)$row['usl'];
            
            $cp = ($usl - $lsl) / (6 * $std);
            $cpu = ($usl - $mean) / (3 * $std);
            $cpl = ($mean - $lsl) / (3 * $std);
            $cpk = min($cpu, $cpl);
            
            $zst = 3 * $cp;
            $zlt = 3 * $cpk;
            
            $row['monthly_zst'] = round($zst, 2);
            $row['monthly_zlt'] = round($zlt, 2);
        }

        // Calculate overdue_today_count: past-time slots not yet filled for today
        // Rule: Quantitative (CTQ/CTP) — checkpoints auto-generated, ALWAYS count overdue
        //       Qualitative (Time Check/F/Proof) — checkpoints must be added manually first,
        //       only count overdue if at least one checkpoint has been defined
        $pid = $row['parameter_id'];
        $measuringItem = strtolower(trim($row['measuring_item'] ?? 'quantitative'));
        $isQualitative = ($measuringItem === 'qualitative');
        if ($isQualitative && !isset($paramsWithCheckpoints[$pid])) {
            // Qualitative param with no checkpoints yet — not overdue (nothing to fill yet)
            $row['overdue_today_count'] = 0;
        } else {
            $lineName = $row['line_name'] ?? '';
            $slots = isset($tLineLabels[$lineName]) ? $tLineLabels[$lineName] : $defaultSlots;
            $overdueCount = 0;
            foreach ($slots as $idx => $timeStr) {
                $seq = $idx + 1;
                if (isset($filledMap[$pid][$seq])) continue;
                if (isSlotPast($timeStr, $nowH, $nowM)) {
                    $overdueCount++;
                }
            }
            $row['overdue_today_count'] = $overdueCount;
        }
    }
    
    if ($is_server_side) {
        echo json_encode([
            "draw" => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $results
        ]);
    } else {
        echo json_encode(["data" => $results]);
    }
    
} catch (Exception $e) {
    echo json_encode(["data" => [], "error" => $e->getMessage()]);
}
?>

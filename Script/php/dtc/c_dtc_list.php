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
                   CASE 
                       WHEN p.target_month = :current_month THEN CONCAT(DATE_FORMAT(STR_TO_DATE(CONCAT(p.target_month, '-01'), '%Y-%m-%d'), '%b %Y'), ' (s/d H-1)')
                       ELSE DATE_FORMAT(STR_TO_DATE(CONCAT(p.target_month, '-01'), '%Y-%m-%d'), '%b %Y')
                   END as label
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.target_month IS NOT NULL AND p.target_month <= :current_month
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
        $wherePeriod = " AND (p.target_month <= :current_month OR p.target_month IS NULL) ";
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
        $models_arr = json_decode($running_models, true);
        if (!is_array($models_arr)) {
            if (strpos($running_models, '||') !== false) {
                $models_arr = explode('||', $running_models);
            } else {
                $models_arr = explode(',', $running_models);
            }
        }
        if (is_array($models_arr) && !empty($models_arr)) {
            $orClauses = [];
            foreach (array_values($models_arr) as $idx => $m_val) {
                if (is_array($m_val)) {
                    $mL = trim($m_val['line_name'] ?? '');
                    $mS = trim($m_val['section_name'] ?? '');
                    $mM = trim($m_val['model_name'] ?? '');
                    $phM = ':rmod_m_' . $idx;
                    $subCond = "(p.model_name = $phM OR spec.model_name = $phM)";
                    $queryParams[$phM] = $mM;
                    if (!empty($mL)) {
                        $phL = ':rmod_l_' . $idx;
                        $subCond .= " AND (p.line_name = $phL OR spec.line_name = $phL)";
                        $queryParams[$phL] = $mL;
                    }
                    if (!empty($mS)) {
                        $phS = ':rmod_s_' . $idx;
                        $subCond .= " AND (p.section_name = $phS OR spec.section_name = $phS)";
                        $queryParams[$phS] = $mS;
                    }
                    $orClauses[] = "(" . $subCond . ")";
                } else if (is_string($m_val) && trim($m_val) !== '') {
                    $phM = ':rmod_m_' . $idx;
                    $orClauses[] = "(p.model_name = $phM OR spec.model_name = $phM)";
                    $queryParams[$phM] = trim($m_val);
                }
            }
            if (!empty($orClauses)) {
                $wherePeriod .= " AND (" . implode(" OR ", $orClauses) . ") ";
            }
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

    $oos_only = trim($_GET['oos_only'] ?? '0');
    if ($oos_only === '1') {
        $dateCondOOSFilter = ($period === 'history')
            ? " AND DATE_FORMAT(s.inspection_date, '%Y-%m') = p.target_month AND (p.target_month < '$currentMonth' OR s.inspection_date < '$prodToday') "
            : " AND DATE_FORMAT(s.inspection_date, '%Y-%m') = p.target_month ";
        $wherePeriod .= " AND EXISTS (
            SELECT 1 
            FROM dtc_measurements m 
            JOIN dtc_inspection_sessions s ON m.session_id = s.session_id 
            JOIN dtc_master_parameters p2 ON s.parameter_id = p2.parameter_id
            LEFT JOIN dtc_master_dtc_specs spec2 ON p2.spec_id = spec2.spec_id
            LEFT JOIN dtc_checkpoints c ON m.checkpoint_id = c.checkpoint_id
            WHERE s.parameter_id = p.parameter_id 
              AND s.is_active = 1 
              {$dateCondOOSFilter}
              AND (
                  CASE 
                      WHEN c.lsl IS NOT NULL OR c.usl IS NOT NULL THEN (
                          m.sample_value IS NOT NULL AND TRIM(m.sample_value) != '' AND m.sample_value REGEXP '^[0-9.-]+$'
                          AND (
                              (c.lsl IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) < c.lsl)
                              OR
                              (c.usl IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) > c.usl)
                          )
                      )
                      WHEN (UPPER(TRIM(COALESCE(p2.data_type, spec2.data_type))) IN ('TIME CHECK', 'F/PROOF')
                            OR LOWER(TRIM(COALESCE(p2.measuring_item, spec2.measuring_item))) = 'qualitative') THEN (
                          UPPER(TRIM(m.sample_value)) = 'NG'
                      )
                      ELSE (
                          (UPPER(TRIM(m.sample_value)) = 'NG')
                          OR (
                              m.sample_value IS NOT NULL AND TRIM(m.sample_value) != '' AND m.sample_value REGEXP '^[0-9.-]+$'
                              AND (
                                  (COALESCE(p2.lsl, spec2.lsl) IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) < COALESCE(p2.lsl, spec2.lsl))
                                  OR
                                  (COALESCE(p2.usl, spec2.usl) IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) > COALESCE(p2.usl, spec2.usl))
                              )
                          )
                      )
                  END
              )
        ) ";
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
        SELECT s.parameter_id, m.sample_label
        FROM dtc_inspection_sessions s
        JOIN dtc_measurements m ON s.session_id = m.session_id
        WHERE s.inspection_date = :today AND m.sample_value IS NOT NULL AND m.sample_value != ''
    ");
    $stmtFilled->execute([':today' => $prodToday]);
    $filledMap = [];
    while ($rf = $stmtFilled->fetch(PDO::FETCH_ASSOC)) {
        $filledMap[$rf['parameter_id']][$rf['sample_label']] = true;
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

    // Load active running models for today's month to get their created_at timestamps
    $stmtRMList = $conn->prepare("
        SELECT UPPER(TRIM(model_name)) as mname, UPPER(TRIM(line_name)) as lname, UPPER(TRIM(section_name)) as sname, created_at 
        FROM dtc_running_models 
        WHERE is_active = 1 AND target_month = :month
    ");
    $stmtRMList->execute([':month' => $currentMonth]);
    $activeRMMap = [];
    while ($rRM = $stmtRMList->fetch(PDO::FETCH_ASSOC)) {
        $key = $rRM['mname'] . '|' . $rRM['lname'] . '|' . $rRM['sname'];
        $activeRMMap[$key] = $rRM['created_at'];
        if (!isset($activeRMMap[$rRM['mname']])) {
            $activeRMMap[$rRM['mname']] = $rRM['created_at'];
        }
    }

    // Batch query Out of Spec (OOS) counts for loaded parameters
    $paramIds = array_column($results, 'parameter_id');
    $oosMap = [];
    if (!empty($paramIds)) {
        $inClause = implode(',', array_map('intval', $paramIds));
        $dateCondOOSCount = ($period === 'history')
            ? " AND DATE_FORMAT(s.inspection_date, '%Y-%m') = p.target_month AND (p.target_month < '$currentMonth' OR s.inspection_date < '$prodToday') "
            : " AND DATE_FORMAT(s.inspection_date, '%Y-%m') = p.target_month ";
        $sqlOOS = "
            SELECT s.parameter_id, COUNT(*) as total_oos
            FROM dtc_measurements m
            JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
            JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            LEFT JOIN dtc_checkpoints c ON m.checkpoint_id = c.checkpoint_id
            WHERE s.parameter_id IN ($inClause)
              AND s.is_active = 1
              {$dateCondOOSCount}
              AND (
                  CASE 
                      WHEN c.lsl IS NOT NULL OR c.usl IS NOT NULL THEN (
                          m.sample_value IS NOT NULL AND TRIM(m.sample_value) != '' AND m.sample_value REGEXP '^[0-9.-]+$'
                          AND (
                              (c.lsl IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) < c.lsl)
                              OR
                              (c.usl IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) > c.usl)
                          )
                      )
                      WHEN (UPPER(TRIM(COALESCE(p.data_type, spec.data_type))) IN ('TIME CHECK', 'F/PROOF')
                            OR LOWER(TRIM(COALESCE(p.measuring_item, spec.measuring_item))) = 'qualitative') THEN (
                          UPPER(TRIM(m.sample_value)) = 'NG'
                      )
                      ELSE (
                          (UPPER(TRIM(m.sample_value)) = 'NG')
                          OR (
                              m.sample_value IS NOT NULL AND TRIM(m.sample_value) != '' AND m.sample_value REGEXP '^[0-9.-]+$'
                              AND (
                                  (COALESCE(p.lsl, spec.lsl) IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) < COALESCE(p.lsl, spec.lsl))
                                  OR
                                  (COALESCE(p.usl, spec.usl) IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) > COALESCE(p.usl, spec.usl))
                              )
                          )
                      )
                  END
              )
            GROUP BY s.parameter_id
        ";
        $stmtOOS = $conn->query($sqlOOS);
        if ($stmtOOS) {
            while ($rOOS = $stmtOOS->fetch(PDO::FETCH_ASSOC)) {
                $oosMap[$rOOS['parameter_id']] = (int)$rOOS['total_oos'];
            }
        }
    }

    foreach ($results as &$row) {
        $row['oos_count'] = $oosMap[$row['parameter_id']] ?? 0;
        $row['prod_today'] = $prodToday;
        $row['period'] = $period;
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

        $mNameKey = strtoupper(trim($row['model_name'] ?? ''));
        $lNameKey = strtoupper(trim($row['line_name'] ?? ''));
        $sNameKey = strtoupper(trim($row['section_name'] ?? ''));
        $comboKey = $mNameKey . '|' . $lNameKey . '|' . $sNameKey;

        $rmCreatedAt = $activeRMMap[$comboKey] ?? ($activeRMMap[$mNameKey] ?? null);
        $row['rm_created_at'] = $rmCreatedAt;

        if ($isQualitative && !isset($paramsWithCheckpoints[$pid])) {
            // Qualitative param with no checkpoints yet — not overdue (nothing to fill yet)
            $row['overdue_today_count'] = 0;
        } else {
            $lineName = $row['line_name'] ?? '';
            $slots = isset($tLineLabels[$lineName]) ? $tLineLabels[$lineName] : $defaultSlots;
            $overdueCount = 0;

            $createdMinsFrom7 = null;
            if ($rmCreatedAt) {
                $createdParts = explode(' ', trim($rmCreatedAt));
                $cDate = $createdParts[0] ?? '';
                $cTime = $createdParts[1] ?? '';
                if ($cDate === $prodToday && !empty($cTime)) {
                    $tp = explode(':', $cTime);
                    $cH = (int)($tp[0] ?? 0);
                    $cM = (int)($tp[1] ?? 0);
                    if ($cH >= 7) {
                        $createdMinsFrom7 = ($cH - 7) * 60 + $cM;
                    }
                }
            }

            foreach ($slots as $idx => $timeStr) {
                if (isset($filledMap[$pid][$timeStr])) continue;

                $stp = explode(':', trim($timeStr));
                $sh = (int)($stp[0] ?? 0);
                $sm = (int)($stp[1] ?? 0);
                if ($sh >= 24) $sh -= 24;
                $shShift = $sh < 7 ? $sh + 24 : $sh;
                $curSlotMinsFrom7 = ($shShift - 7) * 60 + $sm;

                $nextTimeStr = $slots[$idx + 1] ?? null;
                if ($nextTimeStr) {
                    $ntp = explode(':', trim($nextTimeStr));
                    $nsh = (int)($ntp[0] ?? 0);
                    $nsm = (int)($ntp[1] ?? 0);
                    if ($nsh >= 24) $nsh -= 24;
                    $nshShift = $nsh < 7 ? $nsh + 24 : $nsh;
                    $nextSlotMinsFrom7 = ($nshShift - 7) * 60 + $nsm;
                } else {
                    $nextSlotMinsFrom7 = $curSlotMinsFrom7 + 120;
                }

                // If running model was created AFTER this slot's session window ended -> NOT OVERDUE!
                if ($createdMinsFrom7 !== null && $createdMinsFrom7 >= $nextSlotMinsFrom7) {
                    continue;
                }

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

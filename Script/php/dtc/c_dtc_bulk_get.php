<?php
// Script/php/dtc/c_dtc_bulk_get.php
// Endpoint to fetch ALL active running models, their parameters, checkpoints, and current day existing measurements for ALL slots.
require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = getDBConnection();

    $line_filter = trim($_GET['line'] ?? '');
    $section_filter = trim($_GET['section'] ?? '');
    $model_filter = trim($_GET['model'] ?? '');
    $param_filter = intval($_GET['param_id'] ?? $_GET['parameter_id'] ?? 0);
    $prod_hour = (int)date('H');
    $default_prod_date = ($prod_hour < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
    $inspection_date = !empty($_GET['date']) ? trim($_GET['date']) : $default_prod_date;
    $month = date('Y-m', strtotime($inspection_date));

    // --- MODE DETAIL (PARAM_ID > 0): Direct Parameter Fetching (Bypasses dtc_running_models) ---
    if ($param_filter > 0) {
        $stmtP = $conn->prepare("
            SELECT p.parameter_id, p.spec_id, p.target_month,
                   COALESCE(p.model_name, spec.model_name) as model_name,
                   COALESCE(p.line_name, spec.line_name) as line_name,
                   COALESCE(p.section_name, spec.section_name) as section_name,
                   COALESCE(p.process_name, spec.process_name) as process_name,
                   COALESCE(p.item_check_name, spec.item_check_name) as item_check_name,
                   COALESCE(p.sub_item_check_name, spec.sub_item_check_name) as sub_item_check_name,
                   COALESCE(p.data_type, spec.data_type) as data_type,
                   COALESCE(p.measuring_item, spec.measuring_item) as measuring_item,
                   COALESCE(p.lsl, spec.lsl) as lsl,
                   COALESCE(p.usl, spec.usl) as usl,
                   COALESCE(p.target_value, spec.target_value) as target_value,
                   COALESCE(p.uom, spec.uom) as uom,
                   p.reference_image
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.parameter_id = :pid
            " . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
        ");
        $stmtP->execute([':pid' => $param_filter]);
        $paramSingle = $stmtP->fetch(PDO::FETCH_ASSOC);

        if ($paramSingle) {
            $firstLine = $paramSingle['line_name'] ?? 'REF 01';
            $setting_key = 'time_matrix_labels_' . $firstLine;
            
            $labels_map = [];
            $stmtLabel = $conn->prepare("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key = :key OR setting_key = 'time_matrix_labels'");
            $stmtLabel->execute([':key' => $setting_key]);
            while ($rowSetting = $stmtLabel->fetch(PDO::FETCH_ASSOC)) {
                if ($rowSetting && $rowSetting['setting_value']) {
                    $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
                    $decoded = json_decode($val, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $labels_map[$rowSetting['setting_key']] = $decoded;
                    }
                }
            }
            
            $time_labels = $labels_map[$setting_key] ?? ($labels_map['time_matrix_labels'] ?? []);
            if (empty($time_labels)) {
                $time_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
            }

            $time_labels = array_values(array_unique(array_filter($time_labels)));
            usort($time_labels, function($a, $b) {
                $parseMins = function($t) {
                    $parts = explode(':', trim($t));
                    $h = intval($parts[0] ?? 0);
                    $m = intval($parts[1] ?? 0);
                    if ($h == 24) $h = 0;
                    $mins = $h * 60 + $m;
                    if ($mins < 7 * 60) $mins += 24 * 60;
                    return $mins;
                };
                return $parseMins($a) <=> $parseMins($b);
            });
            $time_labels = array_values($time_labels);

            $pid = (int)$paramSingle['parameter_id'];
            $sqlMeas = "
                SELECT s.parameter_id, m.checkpoint_id, m.sample_label, m.sample_value, s.remarks, s.is_closed
                FROM dtc_inspection_sessions s
                JOIN dtc_measurements m ON s.session_id = m.session_id
                WHERE s.parameter_id = :pid
                  AND DATE(s.inspection_date) = :idate
                  AND s.is_active = 1
            ";
            $stmtMeas = $conn->prepare($sqlMeas);
            $stmtMeas->execute([':pid' => $pid, ':idate' => $inspection_date]);
            $existing_map = [];
            while ($r = $stmtMeas->fetch(PDO::FETCH_ASSOC)) {
                $cpid = $r['checkpoint_id'] ?: 0;
                $lbl = trim($r['sample_label']);
                $key = "{$pid}_{$cpid}_{$lbl}";
                $existing_map[$key] = [
                    'val' => $r['sample_value'],
                    'remarks' => $r['remarks'],
                    'is_closed' => (int)$r['is_closed']
                ];
            }

            $stmtCp = $conn->prepare("
                SELECT * FROM dtc_checkpoints 
                WHERE parameter_id = :pid 
                ORDER BY sort_order ASC, checkpoint_id ASC
            ");
            $stmtCp->execute([':pid' => $pid]);
            $checkpoints = $stmtCp->fetchAll(PDO::FETCH_ASSOC);

            $cpList = [];
            if (!empty($checkpoints)) {
                foreach ($checkpoints as $cp) {
                    $cpid = (int)$cp['checkpoint_id'];
                    $cp_lsl = $cp['lsl'] !== null ? (float)$cp['lsl'] : ($paramSingle['lsl'] !== null ? (float)$paramSingle['lsl'] : null);
                    $cp_target = $cp['target_value'] !== null ? (float)$cp['target_value'] : ($paramSingle['target_value'] !== null ? (float)$paramSingle['target_value'] : null);
                    $cp_usl = $cp['usl'] !== null ? (float)$cp['usl'] : ($paramSingle['usl'] !== null ? (float)$paramSingle['usl'] : null);

                    $slotMap = [];
                    foreach ($time_labels as $lbl) {
                        $key = "{$pid}_{$cpid}_{$lbl}";
                        $existData = $existing_map[$key] ?? null;
                        $slotMap[$lbl] = [
                            'val' => $existData['val'] ?? '',
                            'remarks' => $existData['remarks'] ?? ''
                        ];
                    }

                    $cpList[] = [
                        'checkpoint_id' => $cpid,
                        'checkpoint_name' => $cp['checkpoint_name'],
                        'checkpoint_type' => $cp['checkpoint_type'] ?? 'Qualitative',
                        'spec_value' => $cp['spec_value'],
                        'lsl' => $cp_lsl,
                        'target_value' => $cp_target,
                        'usl' => $cp_usl,
                        'reference_image' => $cp['reference_image'] ?: $paramSingle['reference_image'],
                        'slots' => $slotMap
                    ];
                }
            } else {
                // No checkpoints found in dtc_checkpoints.
                // Quantitative (CTQ/CTP): use parameter directly as fallback checkpoint
                // Qualitative (Time Check/F/Proof): must have checkpoint added manually first
                $measuringItemSingle = strtolower(trim($paramSingle['measuring_item'] ?? 'quantitative'));
                if ($measuringItemSingle === 'qualitative') {
                    echo json_encode([
                        'status' => 'warning',
                        'message' => 'Parameter ini (Time Check/F-Proof) belum memiliki checkpoint. Silakan tambahkan checkpoint terlebih dahulu di halaman detail.',
                        'models' => [],
                        'time_labels' => $time_labels
                    ]);
                    exit;
                }
                // Quantitative fallback: use parameter itself as checkpoint
                $slotMap = [];
                foreach ($time_labels as $lbl) {
                    $key = "{$pid}_0_{$lbl}";
                    $existData = $existing_map[$key] ?? null;
                    $slotMap[$lbl] = [
                        'val' => $existData['val'] ?? '',
                        'remarks' => $existData['remarks'] ?? ''
                    ];
                }
                $cpList[] = [
                    'checkpoint_id' => 0,
                    'checkpoint_name' => $paramSingle['item_check_name'],
                    'checkpoint_type' => $paramSingle['data_type'] ?? 'Quantitative',
                    'spec_value' => '',
                    'lsl' => $paramSingle['lsl'] !== null ? (float)$paramSingle['lsl'] : null,
                    'target_value' => $paramSingle['target_value'] !== null ? (float)$paramSingle['target_value'] : null,
                    'usl' => $paramSingle['usl'] !== null ? (float)$paramSingle['usl'] : null,
                    'reference_image' => $paramSingle['reference_image'],
                    'slots' => $slotMap
                ];
            }

            $modelNode = [
                'running_id' => 0,
                'model_name' => $paramSingle['model_name'],
                'line_name' => $paramSingle['line_name'],
                'section_name' => $paramSingle['section_name'],
                'target_month' => $paramSingle['target_month'],
                'parameters' => [[
                    'parameter_id' => $pid,
                    'process_name' => $paramSingle['process_name'],
                    'item_check_name' => $paramSingle['item_check_name'],
                    'sub_item_check_name' => $paramSingle['sub_item_check_name'],
                    'data_type' => $paramSingle['data_type'],
                    'measuring_item' => $paramSingle['measuring_item'],
                    'lsl' => $paramSingle['lsl'],
                    'usl' => $paramSingle['usl'],
                    'target_value' => $paramSingle['target_value'],
                    'uom' => $paramSingle['uom'],
                    'checkpoints' => $cpList
                ]]
            ];

            $user_role = strtolower(trim($_SESSION['role'] ?? ''));
            $is_admin = ($user_role === 'admin');

            echo json_encode([
                'status' => 'success',
                'inspection_date' => $inspection_date,
                'is_admin' => $is_admin,
                'time_labels' => $time_labels,
                'models' => [$modelNode]
            ]);
            exit;
        }
    }

    // 1. Fetch active running models for this month and selected filters
    $sqlRM = "
        SELECT running_id, model_name, line_name, section_name, target_month 
        FROM dtc_running_models 
        WHERE is_active = 1 AND target_month = :month
    ";
    $paramsRM = [':month' => $month];

    if (!empty($line_filter)) {
        $sqlRM .= " AND line_name = :line ";
        $paramsRM[':line'] = $line_filter;
    }
    if (!empty($section_filter)) {
        $sqlRM .= " AND section_name = :section ";
        $paramsRM[':section'] = $section_filter;
    }
    if (!empty($model_filter)) {
        $sqlRM .= " AND model_name = :model ";
        $paramsRM[':model'] = $model_filter;
    }
    $sqlRM .= getIPAccessFilterSQL('line_name', 'section_name');
    $sqlRM .= getUserAccessFilterSQL('line_name', 'section_name');
    $sqlRM .= " ORDER BY line_name ASC, section_name ASC, model_name ASC ";

    $stmtRM = $conn->prepare($sqlRM);
    $stmtRM->execute($paramsRM);
    $runningModels = $stmtRM->fetchAll(PDO::FETCH_ASSOC);

    if (empty($runningModels)) {
        // Fallback: search for active models for target_month without line/section/model filter but with IP & User access filters
        $sqlRM2 = "SELECT running_id, model_name, line_name, section_name, target_month FROM dtc_running_models WHERE is_active = 1 AND target_month = :month " . getIPAccessFilterSQL('line_name', 'section_name') . getUserAccessFilterSQL('line_name', 'section_name') . " ORDER BY line_name ASC, section_name ASC, model_name ASC";
        $stmtRM2 = $conn->prepare($sqlRM2);
        $stmtRM2->execute([':month' => $month]);
        $runningModels = $stmtRM2->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($runningModels)) {
        echo json_encode([
            'status' => 'warning',
            'message' => "Tidak ada Running Model aktif ditemukan.",
            'models' => [],
            'time_labels' => []
        ]);
        exit;
    }

    // 2. Fetch time labels
    $firstLine = $runningModels[0]['line_name'] ?? 'REF 01';
    $setting_key = 'time_matrix_labels_' . $firstLine;
    
    $labels_map = [];
    $stmtLabel = $conn->prepare("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key = :key OR setting_key = 'time_matrix_labels'");
    $stmtLabel->execute([':key' => $setting_key]);
    while ($rowSetting = $stmtLabel->fetch(PDO::FETCH_ASSOC)) {
        if ($rowSetting && $rowSetting['setting_value']) {
            $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
            $decoded = json_decode($val, true);
            if (is_array($decoded) && !empty($decoded)) {
                $labels_map[$rowSetting['setting_key']] = $decoded;
            }
        }
    }
    
    $time_labels = $labels_map[$setting_key] ?? ($labels_map['time_matrix_labels'] ?? []);
    if (empty($time_labels)) {
        $time_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
    }

    // Sort time labels chronologically based on shift start (07:00 AM)
    $time_labels = array_values(array_unique(array_filter($time_labels)));
    usort($time_labels, function($a, $b) {
        $parseMins = function($t) {
            $parts = explode(':', trim($t));
            $h = intval($parts[0] ?? 0);
            $m = intval($parts[1] ?? 0);
            if ($h == 24) $h = 0;
            $mins = $h * 60 + $m;
            if ($mins < 7 * 60) {
                $mins += 24 * 60;
            }
            return $mins;
        };
        return $parseMins($a) <=> $parseMins($b);
    });
    $time_labels = array_values($time_labels);

    // 3. For each running model, load parameters and checkpoints
    $modelTrees = [];

    foreach ($runningModels as $rm) {
        $mName = $rm['model_name'];
        $lName = $rm['line_name'];
        $sName = $rm['section_name'];
        $tMonth = $rm['target_month'];

        $sqlParams = "
            SELECT p.parameter_id, p.spec_id,
                   COALESCE(p.model_name, spec.model_name) as model_name,
                   COALESCE(p.line_name, spec.line_name) as line_name,
                   COALESCE(p.section_name, spec.section_name) as section_name,
                   COALESCE(p.process_name, spec.process_name) as process_name,
                   COALESCE(p.item_check_name, spec.item_check_name) as item_check_name,
                   COALESCE(p.sub_item_check_name, spec.sub_item_check_name) as sub_item_check_name,
                   COALESCE(p.data_type, spec.data_type) as data_type,
                   COALESCE(p.measuring_item, spec.measuring_item) as measuring_item,
                   COALESCE(p.lsl, spec.lsl) as lsl,
                   COALESCE(p.usl, spec.usl) as usl,
                   COALESCE(p.target_value, spec.target_value) as target_value,
                   COALESCE(p.uom, spec.uom) as uom,
                   p.reference_image
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.target_month = :tmonth 
              AND COALESCE(p.model_name, spec.model_name) = :mname
              AND COALESCE(p.line_name, spec.line_name) = :lname
              AND COALESCE(p.section_name, spec.section_name) = :sname
        ";
        $paramsP = [
            ':tmonth' => $tMonth,
            ':mname' => $mName,
            ':lname' => $lName,
            ':sname' => $sName
        ];

        if ($param_filter > 0) {
            $sqlParams .= " AND p.parameter_id = :pfilter ";
            $paramsP[':pfilter'] = $param_filter;
        }

        $sqlParams .= " " . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
              " . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
            ORDER BY COALESCE(p.process_name, spec.process_name) ASC, p.parameter_id ASC
        ";

        $stmtP = $conn->prepare($sqlParams);
        $stmtP->execute($paramsP);
        $parameters = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        if (empty($parameters)) continue;

        $param_ids = array_column($parameters, 'parameter_id');
        $existing_map = [];
        if (!empty($param_ids)) {
            $inStr = implode(',', array_map('intval', $param_ids));
            $sqlMeas = "
                SELECT s.parameter_id, m.checkpoint_id, m.sample_label, m.sample_value, s.remarks, s.is_closed
                FROM dtc_inspection_sessions s
                JOIN dtc_measurements m ON s.session_id = m.session_id
                WHERE s.parameter_id IN ($inStr)
                  AND DATE(s.inspection_date) = :idate
                  AND s.is_active = 1
            ";
            $stmtMeas = $conn->prepare($sqlMeas);
            $stmtMeas->execute([':idate' => $inspection_date]);
            while ($r = $stmtMeas->fetch(PDO::FETCH_ASSOC)) {
                $pid = $r['parameter_id'];
                $cpid = $r['checkpoint_id'] ?: 0;
                $lbl = trim($r['sample_label']);
                $key = "{$pid}_{$cpid}_{$lbl}";
                $existing_map[$key] = [
                    'val' => $r['sample_value'],
                    'remarks' => $r['remarks'],
                    'is_closed' => (int)$r['is_closed']
                ];
            }
        }

        $items = [];
        foreach ($parameters as $p) {
            $pid = (int)$p['parameter_id'];

            $stmtCp = $conn->prepare("
                SELECT * FROM dtc_checkpoints 
                WHERE parameter_id = :pid 
                ORDER BY sort_order ASC, checkpoint_id ASC
            ");
            $stmtCp->execute([':pid' => $pid]);
            $checkpoints = $stmtCp->fetchAll(PDO::FETCH_ASSOC);

            $cpList = [];
            if (!empty($checkpoints)) {
                foreach ($checkpoints as $cp) {
                    $cpid = (int)$cp['checkpoint_id'];
                    $cp_lsl = $cp['lsl'] !== null ? (float)$cp['lsl'] : ($p['lsl'] !== null ? (float)$p['lsl'] : null);
                    $cp_target = $cp['target_value'] !== null ? (float)$cp['target_value'] : ($p['target_value'] !== null ? (float)$p['target_value'] : null);
                    $cp_usl = $cp['usl'] !== null ? (float)$cp['usl'] : ($p['usl'] !== null ? (float)$p['usl'] : null);

                    $slotMap = [];
                    foreach ($time_labels as $lbl) {
                        $key = "{$pid}_{$cpid}_{$lbl}";
                        $existData = $existing_map[$key] ?? null;
                        $slotMap[$lbl] = [
                            'val' => $existData['val'] ?? '',
                            'remarks' => $existData['remarks'] ?? ''
                        ];
                    }

                    $cpList[] = [
                        'checkpoint_id' => $cpid,
                        'checkpoint_name' => $cp['checkpoint_name'],
                        'checkpoint_type' => $cp['checkpoint_type'] ?? 'Qualitative',
                        'spec_value' => $cp['spec_value'],
                        'lsl' => $cp_lsl,
                        'target_value' => $cp_target,
                        'usl' => $cp_usl,
                        'reference_image' => $cp['reference_image'] ?: $p['reference_image'],
                        'measurements_by_slot' => $slotMap
                    ];
                }
            } else {
                // No real checkpoints found.
                // Quantitative (CTQ/CTP): measurement is tracked directly per parameter — use fallback row
                // Qualitative (Time Check/F/Proof): checkpoints must be added manually — skip if none
                $measuringItem = strtolower(trim($p['measuring_item'] ?? 'quantitative'));
                if ($measuringItem === 'qualitative') {
                    // Skip — user hasn't added checkpoints yet for this Qualitative parameter
                    continue;
                }
                // Quantitative fallback: single row using parameter itself as checkpoint
                $slotMap = [];
                foreach ($time_labels as $lbl) {
                    $key = "{$pid}_0_{$lbl}";
                    $existData = $existing_map[$key] ?? null;
                    $slotMap[$lbl] = [
                        'val' => $existData['val'] ?? '',
                        'remarks' => $existData['remarks'] ?? ''
                    ];
                }
                $cpList[] = [
                    'checkpoint_id' => 0,
                    'checkpoint_name' => $p['item_check_name'],
                    'checkpoint_type' => 'Quantitative',
                    'spec_value' => ($p['lsl'] !== null && $p['usl'] !== null) ? "{$p['lsl']} - {$p['usl']} {$p['uom']}" : '',
                    'lsl' => $p['lsl'] !== null ? (float)$p['lsl'] : null,
                    'target_value' => $p['target_value'] !== null ? (float)$p['target_value'] : null,
                    'usl' => $p['usl'] !== null ? (float)$p['usl'] : null,
                    'reference_image' => $p['reference_image'],
                    'measurements_by_slot' => $slotMap
                ];
            }

            $items[] = [
                'parameter_id' => $pid,
                'line_name' => $p['line_name'],
                'section_name' => $p['section_name'],
                'process_name' => $p['process_name'] ?: 'Umum',
                'model_name' => $p['model_name'],
                'item_check_name' => $p['item_check_name'],
                'sub_item_check_name' => $p['sub_item_check_name'],
                'data_type' => $p['data_type'],
                'measuring_item' => $p['measuring_item'],
                'uom' => $p['uom'],
                'reference_image' => $p['reference_image'],
                'checkpoints' => $cpList
            ];
        }

        $modelTrees[] = [
            'running_id' => (int)$rm['running_id'],
            'model_name' => $mName,
            'line_name' => $lName,
            'section_name' => $sName,
            'target_month' => $tMonth,
            'items' => $items
        ];
    }

    $user_role = strtolower(trim($_SESSION['role'] ?? ''));
    $is_admin = ($user_role === 'admin');

    echo json_encode([
        'status' => 'success',
        'is_admin' => $is_admin,
        'inspection_date' => $inspection_date,
        'time_labels' => $time_labels,
        'models' => $modelTrees
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

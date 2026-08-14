<?php
// Script/php/dtc/c_dtc_matrix_qualitative_get.php
// Refactored: Now reads checkpoints from dtc_checkpoints table
// Time labels come from dtc_app_settings
require_once '../../../config/config.php';

header('Content-Type: application/json');

$model = $_GET['model'] ?? '';
$line = $_GET['line'] ?? '';
$section = $_GET['section'] ?? '';
$month = $_GET['month'] ?? '';

$param_id = $_GET['param_id'] ?? $_GET['parameter_id'] ?? 0;

if (empty($model) || empty($line) || empty($section) || empty($month)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // 1. Find the matching parameter(s)
    $parameters = [];
    if (!empty($param_id)) {
        $sql_params = "
            SELECT p.parameter_id, 
                   COALESCE(p.item_check_name, spec.item_check_name) as item_check_name,
                   COALESCE(p.data_type, spec.data_type) as data_type,
                   COALESCE(p.measuring_item, spec.measuring_item) as measuring_item,
                   COALESCE(p.lsl, spec.lsl) as lsl,
                   COALESCE(p.usl, spec.usl) as usl,
                   COALESCE(p.target_value, spec.target_value) as target_value,
                   COALESCE(p.line_name, spec.line_name) as line_name,
                   COALESCE(p.process_name, spec.process_name) as process_name,
                   COALESCE(p.model_name, spec.model_name) as model_name,
                   COALESCE(p.section_name, spec.section_name) as section_name
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.parameter_id = :param_id
              AND UPPER(COALESCE(p.data_type, spec.data_type)) IN ('TIME CHECK', 'F/PROOF')
        ";
        $stmt = $conn->prepare($sql_params);
        $stmt->execute([':param_id' => $param_id]);
        $parameters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($parameters)) {
        $sql_params = "
            SELECT p.parameter_id, 
                   COALESCE(p.item_check_name, spec.item_check_name) as item_check_name,
                   COALESCE(p.data_type, spec.data_type) as data_type,
                   COALESCE(p.measuring_item, spec.measuring_item) as measuring_item,
                   COALESCE(p.lsl, spec.lsl) as lsl,
                   COALESCE(p.usl, spec.usl) as usl,
                   COALESCE(p.target_value, spec.target_value) as target_value,
                   COALESCE(p.line_name, spec.line_name) as line_name,
                   COALESCE(p.process_name, spec.process_name) as process_name,
                   COALESCE(p.model_name, spec.model_name) as model_name,
                   COALESCE(p.section_name, spec.section_name) as section_name
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.target_month = :month
              AND COALESCE(p.model_name, spec.model_name) = :model
              AND COALESCE(p.line_name, spec.line_name) = :line
              AND COALESCE(p.section_name, spec.section_name) = :section
              AND UPPER(COALESCE(p.data_type, spec.data_type)) IN ('TIME CHECK', 'F/PROOF')
            ORDER BY p.parameter_id ASC
        ";
        $stmt = $conn->prepare($sql_params);
        $stmt->execute([
            ':month' => $month,
            ':model' => $model,
            ':line' => $line,
            ':section' => $section
        ]);
        $parameters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($parameters)) {
        echo json_encode(['status' => 'success', 'data' => [], 'time_labels' => [], 'parameters' => []]);
        exit;
    }

    $days_in_month = date('t', strtotime($month . '-01'));
    
function sortShiftTimeLabels($labels) {
    $labels = array_values(array_unique(array_filter($labels)));
    usort($labels, function($a, $b) {
        $getMins = function($str) {
            $str = trim($str);
            if (preg_match('/^(\d{1,2})[:\.](\d{2})/', $str, $m)) {
                $h = (int)$m[1];
                $min = (int)$m[2];
                if ($h < 7) {
                    $h += 24;
                }
                return $h * 60 + $min;
            }
            return 9999;
        };
        return $getMins($a) <=> $getMins($b);
    });
    return $labels;
}

    // 2. Get time labels dynamically from settings combined with measurements
    $line_name = $parameters[0]['line_name'] ?? $line;
    $setting_key = 'time_matrix_labels_' . $line_name;
    
    $base_labels = [];
    $stmtLabel = $conn->prepare("SELECT setting_value FROM dtc_app_settings WHERE setting_key = :key OR setting_key = 'time_matrix_labels'");
    $stmtLabel->execute([':key' => $setting_key]);
    while ($rowSetting = $stmtLabel->fetch(PDO::FETCH_ASSOC)) {
        if ($rowSetting && $rowSetting['setting_value']) {
            $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
            $decoded = json_decode($val, true);
            if (is_array($decoded) && !empty($decoded)) {
                $base_labels = array_merge($base_labels, $decoded);
            }
        }
    }
    if (empty($base_labels)) {
        $base_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
    }
    
    $distinct_labels = [];
    if (!empty($parameters)) {
        $param_ids_str = implode(',', array_map('intval', array_column($parameters, 'parameter_id')));
        $stmtDist = $conn->query("
            SELECT DISTINCT m.sample_label 
            FROM dtc_measurements m 
            JOIN dtc_inspection_sessions s ON m.session_id = s.session_id 
            WHERE s.parameter_id IN ($param_ids_str)
        ");
        $distinct_labels = $stmtDist->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!empty($distinct_labels)) {
        $raw_labels = array_merge($base_labels, $distinct_labels);
    } else {
        $raw_labels = $base_labels;
    }

    $time_labels = sortShiftTimeLabels($raw_labels);

    // 3. For each parameter, get its checkpoints
    $result_data = [];
    $param_info = [];
    
    foreach ($parameters as $param) {
        $param_id = $param['parameter_id'];
        
        $param_info[] = [
            'parameter_id' => (int)$param_id,
            'item_check_name' => $param['item_check_name'],
            'data_type' => $param['data_type'],
            'measuring_item' => $param['measuring_item'],
            'lsl' => $param['lsl'] !== null ? (float)$param['lsl'] : null,
            'usl' => $param['usl'] !== null ? (float)$param['usl'] : null,
            'target_value' => $param['target_value'] !== null ? (float)$param['target_value'] : null,
            'process_name' => $param['process_name']
        ];
        
        // Get checkpoints for this parameter
        $stmtCp = $conn->prepare("SELECT * FROM dtc_checkpoints WHERE parameter_id = :pid ORDER BY sort_order ASC, checkpoint_id ASC");
        $stmtCp->execute([':pid' => $param_id]);
        $checkpoints = $stmtCp->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($checkpoints as $cp) {
            $cp_id = $cp['checkpoint_id'];
            
            // Get measurements for this checkpoint
            $sql_data = "
                SELECT s.inspection_date, m.sample_label, m.sample_value
                FROM dtc_inspection_sessions s
                JOIN dtc_measurements m ON s.session_id = m.session_id
                WHERE s.parameter_id = :param_id
                  AND m.checkpoint_id = :cp_id
                  AND s.inspection_date LIKE :month_like
                ORDER BY s.inspection_date ASC, m.sample_sequence ASC
            ";
            $stmtData = $conn->prepare($sql_data);
            $stmtData->execute([
                ':param_id' => $param_id,
                ':cp_id' => $cp_id,
                ':month_like' => $month . '%'
            ]);
            $measurements = $stmtData->fetchAll(PDO::FETCH_ASSOC);
            
            // Build matrix: time_label => { day => value }
            $matrix = [];
            $daily_values = []; // day => array of numeric values
            
            foreach ($measurements as $m) {
                $day = (int)date('d', strtotime($m['inspection_date']));
                $label = $m['sample_label'];
                $val = $m['sample_value'];
                
                if (!isset($matrix[$label])) {
                    $matrix[$label] = [];
                }
                $matrix[$label][$day] = $val;
                
                $is_quant_cp = (isset($cp['checkpoint_type']) && strcasecmp($cp['checkpoint_type'], 'Quantitative') === 0) || (isset($param['measuring_item']) && strcasecmp($param['measuring_item'], 'Quantitative') === 0);
                if ($is_quant_cp && is_numeric($val)) {
                    if (!isset($daily_values[$day])) {
                        $daily_values[$day] = [];
                    }
                    $daily_values[$day][] = (float)$val;
                }
            }
            
            // Calculate chart data per day
            $chart_xbar = [];
            $chart_r = [];
            $chart_categories = [];
            
            for ($d = 1; $d <= $days_in_month; $d++) {
                $chart_categories[] = (string)$d;
                if (isset($daily_values[$d]) && count($daily_values[$d]) > 0) {
                    $vals = $daily_values[$d];
                    $avg = array_sum($vals) / count($vals);
                    $range = max($vals) - min($vals);
                    $chart_xbar[] = round($avg, 2);
                    $chart_r[] = round($range, 2);
                } else {
                    $chart_xbar[] = null;
                    $chart_r[] = null;
                }
            }
            
            $cp_lsl = isset($cp['lsl']) && $cp['lsl'] !== null ? (float)$cp['lsl'] : ($param['lsl'] !== null ? (float)$param['lsl'] : null);
            $cp_target = isset($cp['target_value']) && $cp['target_value'] !== null ? (float)$cp['target_value'] : ($param['target_value'] !== null ? (float)$param['target_value'] : null);
            $cp_usl = isset($cp['usl']) && $cp['usl'] !== null ? (float)$cp['usl'] : ($param['usl'] !== null ? (float)$param['usl'] : null);

            $result_data[] = [
                'checkpoint_id' => (int)$cp_id,
                'parameter_id' => (int)$param_id,
                'checkpoint_name' => $cp['checkpoint_name'],
                'spec_value' => $cp['spec_value'],
                'lsl' => $cp_lsl,
                'target_value' => $cp_target,
                'usl' => $cp_usl,
                'checkpoint_type' => $cp['checkpoint_type'] ?? 'Qualitative',
                'reference_image' => $cp['reference_image'],
                'matrix' => $matrix,
                'chart_data' => [
                    'categories' => $chart_categories,
                    'xbar' => $chart_xbar,
                    'r' => $chart_r,
                    'lsl' => $cp_lsl,
                    'usl' => $cp_usl,
                    'target' => $cp_target
                ]
            ];
        }
    }

    // 4. Get session closed status per day per parameter
    $closed_days = [];
    $param_ids = array_column($parameters, 'parameter_id');
    if (!empty($param_ids)) {
        $in_clause = implode(',', array_map('intval', $param_ids));
        $sql_sess_status = "
            SELECT parameter_id, CAST(DATE_FORMAT(inspection_date, '%e') AS UNSIGNED) as day_of_month, is_closed
            FROM dtc_inspection_sessions
            WHERE parameter_id IN ($in_clause)
              AND inspection_date LIKE :month_like
              AND is_active = 1
        ";
        $stmt_sess_status = $conn->prepare($sql_sess_status);
        $stmt_sess_status->execute([':month_like' => $month . '%']);
        while ($row = $stmt_sess_status->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int)$row['parameter_id'];
            $day = (int)$row['day_of_month'];
            if (!isset($closed_days[$pid])) {
                $closed_days[$pid] = [];
            }
            $closed_days[$pid][$day] = (int)$row['is_closed'];
        }
    }

    $rm_created_at = null;
    $stmtRM = $conn->prepare("
        SELECT created_at FROM dtc_running_models 
        WHERE target_month = :month 
          AND UPPER(TRIM(line_name)) = UPPER(TRIM(:line)) 
          AND UPPER(TRIM(section_name)) = UPPER(TRIM(:section)) 
          AND UPPER(TRIM(model_name)) = UPPER(TRIM(:model)) 
          AND is_active = 1 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmtRM->execute([
        ':month' => $month,
        ':line' => $line_name,
        ':section' => $section,
        ':model' => $model
    ]);
    $rm_created_at = $stmtRM->fetchColumn() ?: null;

    echo json_encode([
        'status' => 'success',
        'days_in_month' => (int)$days_in_month,
        'time_labels' => $time_labels,
        'parameters' => $param_info,
        'closed_days' => $closed_days,
        'running_model_created_at' => $rm_created_at,
        'data' => $result_data
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

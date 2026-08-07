<?php
// c_dtc_matrix_qualitative.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

$param_id = isset($_GET['param_id']) ? intval($_GET['param_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

try {
    $conn = getDBConnection();
    
    // 1. Get the base parameter info to find its grouping
    $stmtBase = $conn->prepare("SELECT p.section_name, p.line_name, p.target_month 
                                FROM dtc_master_parameters p 
                                WHERE p.parameter_id = :param_id");
    $stmtBase->execute([':param_id' => $param_id]);
    $baseParam = $stmtBase->fetch(PDO::FETCH_ASSOC);
    
    if (!$baseParam) {
        throw new Exception("Parameter not found.");
    }
    
    $section_name = $baseParam['section_name'];
    $line_name = $baseParam['line_name'];
    $actual_month = $month; // Use requested month
    
    // 2. Fetch all parameters in this section, line, month, and Qualitative
    $sqlParams = "SELECT p.parameter_id, COALESCE(p.item_check_name, s.item_check_name) as item_check_name, COALESCE(p.data_type, s.data_type) as data_type, COALESCE(p.measuring_item, s.measuring_item) as measuring_item, COALESCE(p.lsl, s.lsl) as lsl, COALESCE(p.usl, s.usl) as usl, p.process_name 
                  FROM dtc_master_parameters p
                  LEFT JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id
                  WHERE p.section_name = :sec AND p.line_name = :lin 
                    AND p.target_month = :mon AND COALESCE(p.measuring_item, s.measuring_item) = 'Qualitative'
                  ORDER BY p.parameter_id ASC";
    $stmtParams = $conn->prepare($sqlParams);
    $stmtParams->execute([':sec' => $section_name, ':lin' => $line_name, ':mon' => $actual_month]);
    $parameters = $stmtParams->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch time labels from settings
    $stmtSetting = $conn->prepare("SELECT setting_value FROM dtc_app_settings WHERE setting_key = 'time_matrix_labels'");
    $stmtSetting->execute();
    $rowSetting = $stmtSetting->fetch(PDO::FETCH_ASSOC);
    $time_labels = [];
    if ($rowSetting && $rowSetting['setting_value']) {
        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
        $time_labels = json_decode($val, true);
    }
    if (empty($time_labels)) {
        $time_labels = ['07:30', '09:40', '12:40', '14:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
    }
    
    $results_grouped = [];
    
    foreach ($parameters as $index => $param) {
        $pid = $param['parameter_id'];
        
        // Fetch existing labels for this parameter to lock in the initial pattern
        $stmtExistingLabels = $conn->prepare("
            SELECT m.sample_sequence, m.sample_label 
            FROM dtc_measurements m 
            JOIN dtc_inspection_sessions s ON m.session_id = s.session_id 
            WHERE s.parameter_id = :pid AND s.is_active = 1
            ORDER BY s.inspection_date ASC, m.measurement_id ASC
        ");
        $stmtExistingLabels->execute([':pid' => $pid]);
        $existing_labels = [];
        while ($r = $stmtExistingLabels->fetch(PDO::FETCH_ASSOC)) {
            $seq = intval($r['sample_sequence']);
            $lbl = trim($r['sample_label'] ?? '');
            if ($lbl && strtolower($lbl) !== 'null' && !isset($existing_labels[$seq])) {
                $existing_labels[$seq] = preg_replace('/^Jam\s+/i', '', $lbl);
            }
        }
        
        $local_time_labels = $time_labels;
        for ($i = 0; $i < 10; $i++) {
            if (isset($existing_labels[$i + 1])) {
                $local_time_labels[$i] = $existing_labels[$i + 1];
            }
        }
        
        // Prepare the structure for this parameter
        $paramData = [
            'no' => $index + 1,
            'parameter_id' => $pid,
            'check_point' => $param['process_name'] . ' - ' . $param['item_check_name'] . ' [' . $param['data_type'] . ']',
            'periode' => '3 kali per-hari',
            'spec' => 'OK',
            'times' => []
        ];
        
        for ($i = 1; $i <= 10; $i++) {
            $paramData['times'][$i] = [
                'seq' => $i,
                'time_label' => $local_time_labels[$i - 1] ?? "Sample $i",
                'days' => array_fill(1, 31, null)
            ];
        }
        
        // Fetch sessions and measurements for this parameter
        $sqlMeas = "SELECT 
                        s.session_id,
                        DATE_FORMAT(s.inspection_date, '%d') as day_of_month,
                        s.is_closed,
                        m.sample_sequence,
                        m.sample_value,
                        m.sample_label
                    FROM dtc_inspection_sessions s
                    JOIN dtc_measurements m ON s.session_id = m.session_id
                    WHERE s.parameter_id = :pid 
                      AND DATE_FORMAT(s.inspection_date, '%Y-%m') = :month
                      AND s.is_active = 1";
        $stmtMeas = $conn->prepare($sqlMeas);
        $stmtMeas->execute([':pid' => $pid, ':month' => $actual_month]);
        $measurements = $stmtMeas->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($measurements as $m) {
            $day = intval($m['day_of_month']);
            $seq = intval($m['sample_sequence']);
            $val = $m['sample_value'];
            
            if (isset($paramData['times'][$seq])) {
                $paramData['times'][$seq]['days'][$day] = $val;
            }
        }
        
        $paramData['times'] = array_values($paramData['times']);
        
        // Also get close status per day for this param
        $paramData['closed_days'] = array_fill(1, 31, 0);
        $sqlSessions = "SELECT DATE_FORMAT(inspection_date, '%d') as day_of_month, is_closed 
                        FROM dtc_inspection_sessions 
                        WHERE parameter_id = :pid AND DATE_FORMAT(inspection_date, '%Y-%m') = :month AND is_active = 1";
        $stmtSess = $conn->prepare($sqlSessions);
        $stmtSess->execute([':pid' => $pid, ':month' => $actual_month]);
        $sessData = $stmtSess->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sessData as $s) {
            $paramData['closed_days'][intval($s['day_of_month'])] = intval($s['is_closed']);
        }
        
        $results_grouped[] = $paramData;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $results_grouped,
        'month' => $actual_month
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

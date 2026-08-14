<?php
// Script/php/dtc/c_dtc_measurement_get.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

$param_id = isset($_GET['parameter_id']) ? intval($_GET['parameter_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (!$param_id || !$date) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Fetch active running model created_at timestamp for this parameter
    $rm_created_at = null;
    $stmtP = $conn->prepare("
        SELECT COALESCE(p.model_name, spec.model_name) as model_name,
               COALESCE(p.line_name, spec.line_name) as line_name,
               COALESCE(p.section_name, spec.section_name) as section_name
        FROM dtc_master_parameters p
        LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
        WHERE p.parameter_id = :pid
    ");
    $stmtP->execute([':pid' => $param_id]);
    $pRow = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($pRow && !empty($pRow['model_name'])) {
        $stmtRM = $conn->prepare("
            SELECT created_at FROM dtc_running_models 
            WHERE target_month = :month 
              AND UPPER(TRIM(model_name)) = UPPER(TRIM(:mname)) 
              AND is_active = 1 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmtRM->execute([':month' => substr($date, 0, 7), ':mname' => $pRow['model_name']]);
        $rm_created_at = $stmtRM->fetchColumn() ?: null;
    }
    
    // In Oracle, date format comparison TO_CHAR(inspection_date, 'YYYY-MM-DD')
    $sql = "SELECT m.sample_sequence, m.sample_value, m.sample_label, s.remarks, s.is_closed 
            FROM dtc_inspection_sessions s
            JOIN dtc_measurements m ON s.session_id = m.session_id
            WHERE s.parameter_id = :param_id 
              AND DATE(s.inspection_date) = :idate";
              
    $stmt = $conn->prepare($sql);
    $stmt->execute([':param_id' => $param_id, ':idate' => $date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch time labels from settings using line_name
    $line_name = $pRow ? trim($pRow['line_name']) : '';
    $time_labels = [];
    if (!empty($line_name)) {
        $stmtSetting = $conn->prepare("SELECT setting_value FROM dtc_app_settings WHERE setting_key = :k");
        $stmtSetting->execute([':k' => 'time_matrix_labels_' . $line_name]);
        $rowSetting = $stmtSetting->fetch(PDO::FETCH_ASSOC);
        if ($rowSetting && $rowSetting['setting_value']) {
            $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
            $time_labels = json_decode($val, true);
        }
    }
    if (empty($time_labels)) {
        $time_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
    }

    if (count($rows) > 0) {
        $data = [
            'remarks' => $rows[0]['remarks'],
            'is_closed' => intval($rows[0]['is_closed'])
        ];
        foreach ($rows as $r) {
            $lbl = trim($r['sample_label'] ?? '');
            $lblClean = preg_replace('/^Jam\s+/i', '', $lbl);
            
            $seq = null;
            foreach ($time_labels as $idx => $tLabel) {
                if (trim($tLabel) === $lblClean) {
                    $seq = $idx + 1;
                    break;
                }
            }
            if ($seq === null) {
                $seq = intval($r['sample_sequence']);
            }
            
            $data["sample_$seq"] = $r['sample_value'];
            if (!empty($r['sample_label'])) {
                $data["label_$seq"] = $r['sample_label'];
            }
        }
        echo json_encode(["status" => "found", "data" => $data, "running_model_created_at" => $rm_created_at]);
    } else {
        echo json_encode(["status" => "not_found", "running_model_created_at" => $rm_created_at]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

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
    
    if (count($rows) > 0) {
        $data = [
            'remarks' => $rows[0]['remarks'],
            'is_closed' => intval($rows[0]['is_closed'])
        ];
        foreach ($rows as $r) {
            $seq = intval($r['sample_sequence']);
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

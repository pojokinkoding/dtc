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
        echo json_encode(["status" => "found", "data" => $data]);
    } else {
        echo json_encode(["status" => "not_found"]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

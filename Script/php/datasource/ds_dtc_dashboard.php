<?php
// ds_dtc_dashboard.php
require_once '../../../config.php';

function ds_getSessions($parameterId, $date) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT s.session_id, s.inspection_time, s.remarks,
                       m.sample_1, m.sample_2, m.sample_3, m.sample_4, m.sample_5
                FROM dtc_inspection_sessions s
                LEFT JOIN dtc_measurements m ON s.session_id = m.session_id
                WHERE s.parameter_id = :param_id 
                  AND DATE(s.inspection_date) = STR_TO_DATE(:insp_date, '%Y-%m-%d')
                  AND s.is_active = 1
                ORDER BY s.inspection_time ASC";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':param_id', $parameterId, PDO::PARAM_INT);
        $stmt->bindParam(':insp_date', $date, PDO::PARAM_STR);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If empty, generate empty rows for Jam 1-5 (based on the user's requirement for 5 time checks)
        if (empty($results)) {
            $times = ['08:00', '10:00', '13:00', '15:00', '17:00'];
            $results = [];
            foreach ($times as $t) {
                $results[] = [
                    "session_id" => null,
                    "inspection_time" => $t,
                    "sample_1" => null,
                    "sample_2" => null,
                    "sample_3" => null,
                    "sample_4" => null,
                    "sample_5" => null,
                    "remarks" => ""
                ];
            }
        }
        
        return $results;
    } catch (Exception $e) {
        return [];
    }
}

function ds_saveMeasurements($models) {
    try {
        $conn = getDBConnection();
        
        foreach ($models as $row) {
            if ($row['sample_1'] !== null && $row['sample_2'] !== null && 
                $row['sample_3'] !== null && $row['sample_4'] !== null && $row['sample_5'] !== null) {
                
                $sql = "CALL SP_DTC_SAVE_MEASUREMENT(
                                :p_param, :p_date, :p_time, :p_shift, :p_operator, :p_remarks,
                                :p_s1, :p_s2, :p_s3, :p_s4, :p_s5, :p_out
                            );";
                        
                $stmt = $conn->prepare($sql);
                
                $paramId = 1; // Example hardcoded parameter
                $date = date('Y-m-d'); // Current date
                $shift = "Shift 1";
                $opId = 1; // ID for Soni sopyan
                
                $stmt->bindParam(':p_param', $paramId);
                $stmt->bindParam(':p_date', $date);
                $stmt->bindParam(':p_time', $row['inspection_time']);
                $stmt->bindParam(':p_shift', $shift);
                $stmt->bindParam(':p_operator', $opId);
                $stmt->bindParam(':p_remarks', $row['remarks']);
                $stmt->bindParam(':p_s1', $row['sample_1']);
                $stmt->bindParam(':p_s2', $row['sample_2']);
                $stmt->bindParam(':p_s3', $row['sample_3']);
                $stmt->bindParam(':p_s4', $row['sample_4']);
                $stmt->bindParam(':p_s5', $row['sample_5']);
                
                $outId = 0;
                $stmt->bindParam(':p_out', $outId, PDO::PARAM_INT, 10);
                
                $stmt->execute();
            }
        }
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}
?>

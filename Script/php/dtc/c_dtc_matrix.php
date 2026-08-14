<?php
// c_dtc_matrix.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

$param_id = isset($_GET['param_id']) ? intval($_GET['param_id']) : (isset($_GET['spec_id']) ? intval($_GET['spec_id']) : 0);
// Expecting month in format YYYY-MM
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

try {
    $conn = getDBConnection();
    
    // Fetch all measurements vertically
    $sql = "SELECT 
                s.inspection_date,
                DATE_FORMAT(s.inspection_date, '%d') as day_of_month,
                m.sample_sequence,
                m.sample_label,
                m.sample_value,
                s.max_value, s.min_value, s.x_bar, s.range_value, s.std_dev,
                COALESCE(p.lsl, spec.lsl) as lsl, COALESCE(p.usl, spec.usl) as usl
            FROM dtc_inspection_sessions s
            JOIN dtc_measurements m ON s.session_id = m.session_id
            JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE s.parameter_id = :param_id 
              AND DATE_FORMAT(s.inspection_date, '%Y-%m') = :month
              AND s.is_active = 1
            ORDER BY s.inspection_date ASC, m.sample_sequence ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([':param_id' => $param_id, ':month' => $month]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fallback: if no data in the requested month, use the latest month that has actual data
    $actual_month = $month;
    if (empty($results)) {
        $stmt_latest = $conn->prepare("SELECT DATE_FORMAT(MAX(s.inspection_date), '%Y-%m') as latest_month 
                                       FROM dtc_inspection_sessions s 
                                       WHERE s.parameter_id = :param_id AND s.is_active = 1");
        $stmt_latest->execute([':param_id' => $param_id]);
        $latest = $stmt_latest->fetchColumn();
        if ($latest) {
            $actual_month = $latest;
            $stmt2 = $conn->prepare($sql);
            $stmt2->execute([':param_id' => $param_id, ':month' => $actual_month]);
            $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Prepare Pivot Structure
    // Group by sample_sequence -> sample_index
    // pivot_data will be initialized after time_labels are fetched
    $max_data = ['jam' => 'Max Data'];
    $min_data = ['jam' => 'Min Data'];
    $zst_row_data = ['jam' => 'Zst'];
    $zlt_row_data = ['jam' => 'Zlt'];
    
    // Initialize day columns 1-31 and chart arrays
    $xbar_data = [];
    $r_data = [];
    $zst_data = [];
    $zlt_data = [];
    for($i = 1; $i <= 31; $i++) {
        $max_data["day_$i"] = null;
        $min_data["day_$i"] = null;
        $zst_row_data["day_$i"] = null;
        $zlt_row_data["day_$i"] = null;
        $xbar_data[] = null;
        $r_data[] = null;
        $zst_data[] = null;
        $zlt_data[] = null;
    }
    
    // Get line name for this parameter
    $stmtLine = $conn->prepare("SELECT COALESCE(p.line_name, s.line_name) as line_name 
                                FROM dtc_master_parameters p 
                                LEFT JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id 
                                WHERE p.parameter_id = :pid");
    $stmtLine->execute([':pid' => $param_id]);
    $lineRow = $stmtLine->fetch(PDO::FETCH_ASSOC);
    $line_name = $lineRow ? trim($lineRow['line_name']) : '';

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

    // Pre-initialize pivot_data to guarantee ordered rows s1 to s10
    $pivot_data = [];
    for ($i = 1; $i <= 10; $i++) {
        $row_key = $i;
        $lbl = $time_labels[$i - 1] ?? "Sample $i";
        $pivot_data[$row_key] = ['jam' => $lbl, 'is_sample' => true];
        for($d = 1; $d <= 31; $d++) {
            $pivot_data[$row_key]["day_$d"] = null;
        }
    }

    foreach($results as $row) {
        $day = intval($row['day_of_month']);
        $day_key = "day_$day";
        
        $lbl = trim($row['sample_label'] ?? '');
        $lblClean = preg_replace('/^Jam\s+/i', '', $lbl);
        
        $row_key = null;
        foreach ($time_labels as $idx => $tLabel) {
            if (trim($tLabel) === $lblClean) {
                $row_key = $idx + 1;
                break;
            }
        }
        if ($row_key === null) {
            $row_key = intval($row['sample_sequence']);
        }
        
        // Removed dynamic initialization here since it's pre-initialized
        
        // Ensure UI doesn't break if numeric parsing fails
        $val = $row["sample_value"];
        if ($val === '' || $val === null) {
            $pivot_data[$row_key][$day_key] = null;
        } else {
            $pivot_data[$row_key][$day_key] = is_numeric($val) ? floatval($val) : $val;
        }
        
        // Also capture the chart data (maps perfectly to 1 session per day)
        $day_index = $day - 1; // 0-indexed for chart arrays
        $xbar_data[$day_index] = $row['x_bar'] !== null ? floatval($row['x_bar']) : null;
        $r_data[$day_index] = $row['range_value'] !== null ? floatval($row['range_value']) : null;
        
        $max_data[$day_key] = $row['max_value'] !== null ? floatval($row['max_value']) : null;
        $min_data[$day_key] = $row['min_value'] !== null ? floatval($row['min_value']) : null;
        
        // Dynamically recalculate ZST on the fly (in case LSL/USL specs were updated)
        if($row['std_dev'] !== null && floatval($row['std_dev']) > 0 && $row['x_bar'] !== null) {
            $std = floatval($row['std_dev']);
            $xbar = floatval($row['x_bar']);
            $lsl = floatval($row['lsl']);
            $usl = floatval($row['usl']);
            
            $cpu = ($usl - $xbar) / (3 * $std);
            $cpl = ($xbar - $lsl) / (3 * $std);
            $cpk = min($cpu, $cpl);
            $zst = 3 * $cpk;
            $zlt = $zst - 1.5;
            
            $zst_data[$day_index] = round($zst, 3);
            $zlt_data[$day_index] = round($zlt, 3);
            $zst_row_data[$day_key] = round($zst, 2);
            $zlt_row_data[$day_key] = round($zlt, 2);
        }
    }
    
    // Sort pivot data by sequence
    ksort($pivot_data);
    
    // Only calculate and append Max/Min if there is actual data
    if(count($pivot_data) > 0) {
        // Convert Pivot to Indexed Array
        $final_data = array_values($pivot_data);
        
        // Append Max and Min, Zst, Zlt rows at the end
        $final_data[] = $max_data;
        $final_data[] = $min_data;
        $final_data[] = $zst_row_data;
        $final_data[] = $zlt_row_data;
    } else {
        $final_data = [];
    }
    
    // Instead of just outputting the matrix, we wrap it with chart data
    echo json_encode([
        "matrix" => $final_data,
        "actual_month" => $actual_month,
        "charts" => [
            "xbar" => $xbar_data,
            "r" => $r_data,
            "zst" => $zst_data,
            "zlt" => $zlt_data
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>

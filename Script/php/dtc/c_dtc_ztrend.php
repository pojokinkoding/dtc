<?php
require_once '../../../config/config.php';
header('Content-Type: application/json');

try {
    $param_id = isset($_GET['param_id']) ? intval($_GET['param_id']) : 0;
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');

    if ($param_id === 0) {
        throw new Exception("Invalid Parameter ID");
    }

    $conn = getDBConnection();

    $checkpoint_id = isset($_GET['checkpoint_id']) ? intval($_GET['checkpoint_id']) : 0;

    // Get spec_id from the given param_id (to find all related monthly params)
    $stmt_spec = $conn->prepare("SELECT spec_id FROM dtc_master_parameters WHERE parameter_id = :pid");
    $stmt_spec->execute([':pid' => $param_id]);
    $spec_id = $stmt_spec->fetchColumn();

    if (!$spec_id) {
        throw new Exception("Spec not found for param_id $param_id");
    }

    // Fetch monthly aggregated ZST/ZLT by joining all parameters under the same spec_id
    // Handles both CTQ (s.x_bar) and Checkpoints (dtc_measurements)
    if ($checkpoint_id > 0) {
        $sql = "
            SELECT 
                DATE_FORMAT(s.inspection_date, '%Y') as yr,
                DATE_FORMAT(s.inspection_date, '%m') as month_num,
                AVG(CAST(m.sample_value AS DECIMAL(10,4))) as avg_x_bar,
                STDDEV_SAMP(CAST(m.sample_value AS DECIMAL(10,4))) as avg_std_dev,
                MAX(COALESCE(c.lsl, p.lsl, spec.lsl)) as lsl,
                MAX(COALESCE(c.usl, p.usl, spec.usl)) as usl
            FROM dtc_measurements m
            JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
            JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            LEFT JOIN dtc_checkpoints c ON m.checkpoint_id = c.checkpoint_id
            WHERE m.checkpoint_id = :cpid
              AND DATE_FORMAT(s.inspection_date, '%Y') = :year
              AND s.is_active = 1
              AND m.sample_value REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$'
            GROUP BY DATE_FORMAT(s.inspection_date, '%Y'), DATE_FORMAT(s.inspection_date, '%m')
            ORDER BY month_num ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':cpid' => $checkpoint_id, ':year' => $year]);
    } else {
        $sql = "
            SELECT 
                DATE_FORMAT(s.inspection_date, '%Y') as yr,
                DATE_FORMAT(s.inspection_date, '%m') as month_num,
                COALESCE(AVG(s.x_bar), AVG(CAST(m.sample_value AS DECIMAL(10,4)))) as avg_x_bar,
                COALESCE(AVG(s.std_dev), STDDEV_SAMP(CAST(m.sample_value AS DECIMAL(10,4)))) as avg_std_dev,
                MAX(COALESCE(p.lsl, spec.lsl)) as lsl,
                MAX(COALESCE(p.usl, spec.usl)) as usl
            FROM dtc_inspection_sessions s
            JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            LEFT JOIN dtc_measurements m ON m.session_id = s.session_id
            WHERE p.spec_id = :spec_id
              AND DATE_FORMAT(s.inspection_date, '%Y') = :year
              AND s.is_active = 1
            GROUP BY DATE_FORMAT(s.inspection_date, '%Y'), DATE_FORMAT(s.inspection_date, '%m')
            ORDER BY month_num ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':spec_id' => $spec_id, ':year' => $year]);
    }
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize arrays for 12 months (null = no data)
    $zst_actual = array_fill(0, 12, null);
    $zlt_actual = array_fill(0, 12, null);

    foreach ($results as $row) {
        $month_index = intval($row['month_num']) - 1;
        $std  = floatval($row['avg_std_dev']);
        $xbar = floatval($row['avg_x_bar']);
        $lsl  = floatval($row['lsl']);
        $usl  = floatval($row['usl']);

        if ($std > 0 && $xbar !== null && $usl > $lsl) {
            $cpu = ($usl - $xbar) / (3 * $std);
            $cpl = ($xbar - $lsl) / (3 * $std);
            $cpk = min($cpu, $cpl);
            $cpVal = ($usl - $lsl) / (6 * $std);
            $zlt = 3 * $cpk;
            $zst = 3 * $cpVal;

            $zst_actual[$month_index] = round($zst, 3);
            $zlt_actual[$month_index] = round($zlt, 3);
        }
    }

    // --- Determine Historical Trend Conclusion ---
    $forecast_conclusion = "Data tidak cukup untuk menyimpulkan tren.";
    $actual_values = array_filter($zst_actual, function($v) { return $v !== null; });
    
    if (count($actual_values) >= 2) {
        $actual_keys = array_keys($actual_values);
        $last_key = end($actual_keys);
        $prev_key = prev($actual_keys); // Get the second to last key
        
        $last_val = $actual_values[$last_key];
        $prev_val = $actual_values[$prev_key];
        $trend = $last_val - $prev_val;
        
        if ($last_val < 3.0) {
            $forecast_conclusion = "Kritis: Performa (ZST) turun di bawah batas minimum 3.0 pada data terakhir. Segera lakukan evaluasi!";
        } else if ($last_val < 4.0 && $trend < 0) {
            $forecast_conclusion = "Waspada: Performa (ZST) menurun dan berada di bawah target 4.0. Perlu pemantauan ketat.";
        } else if ($last_val < 4.0 && $trend >= 0) {
            $forecast_conclusion = "Waspada: Performa (ZST) masih di bawah target 4.0 meskipun ada indikasi perbaikan.";
        } else if ($trend > 0) {
            $forecast_conclusion = "Positif: Performa (ZST) menunjukkan peningkatan dan berada di batas aman (>= 4.0).";
        } else {
            $forecast_conclusion = "Stabil: Performa (ZST) terpantau stabil dan terkendali di atas batas target 4.0.";
        }
    } else if (count($actual_values) == 1) {
        $last_val = end($actual_values);
        if ($last_val < 3.0) {
            $forecast_conclusion = "Kritis: Performa (ZST) di bawah batas minimum 3.0.";
        } else if ($last_val < 4.0) {
            $forecast_conclusion = "Waspada: Performa (ZST) berada di bawah target 4.0.";
        } else {
            $forecast_conclusion = "Stabil: Performa (ZST) terkendali di atas target 4.0.";
        }
    }

    echo json_encode([
        "zst_actual"   => $zst_actual,
        "zlt_actual"   => $zlt_actual,
        "spec_id"      => $spec_id,
        "year"         => $year,
        "forecast_conclusion" => $forecast_conclusion
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>

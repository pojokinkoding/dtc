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

    // Get spec_id from the given param_id
    $stmt_spec = $conn->prepare("SELECT spec_id FROM dtc_master_parameters WHERE parameter_id = :pid");
    $stmt_spec->execute([':pid' => $param_id]);
    $spec_id = $stmt_spec->fetchColumn();

    if (!$spec_id) {
        throw new Exception("Spec not found for param_id $param_id");
    }

    // Fetch monthly aggregated ZST/ZLT
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
    $validHistory = [];

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
            $zlt = round(3 * $cpk, 3);
            $zst = round(3 * $cpVal, 3);

            $zst_actual[$month_index] = $zst;
            $zlt_actual[$month_index] = $zlt;

            $validHistory[] = [
                'month_num' => intval($row['month_num']),
                'zst' => $zst,
                'zlt' => $zlt
            ];
        }
    }

    // Auto AI Model Training & Forecast Calculation
    $zst_forecast = array_fill(0, 12, null);
    $zlt_forecast = array_fill(0, 12, null);
    $ai_forecast_text = "";
    $has_forecast = false;

    if (count($validHistory) >= 2) {
        $n = count($validHistory);
        $sumX = 0; $sumY_zst = 0; $sumY_zlt = 0;
        $sumXY_zst = 0; $sumXY_zlt = 0; $sumXX = 0;

        foreach ($validHistory as $vh) {
            $x = $vh['month_num'];
            $yzst = $vh['zst'];
            $yzlt = $vh['zlt'];

            $sumX += $x;
            $sumXX += ($x * $x);
            $sumY_zst += $yzst;
            $sumY_zlt += $yzlt;
            $sumXY_zst += ($x * $yzst);
            $sumXY_zlt += ($x * $yzlt);
        }

        $denom = ($n * $sumXX - $sumX * $sumX);
        if ($denom == 0) $denom = 1;

        $m_zst = ($n * $sumXY_zst - $sumX * $sumY_zst) / $denom;
        $c_zst = ($sumY_zst - $m_zst * $sumX) / $n;

        $m_zlt = ($n * $sumXY_zlt - $sumX * $sumY_zlt) / $denom;
        $c_zlt = ($sumY_zlt - $m_zlt * $sumX) / $n;

        $lastActualMonth = max(array_column($validHistory, 'month_num'));
        $nextMonthIdx = $lastActualMonth + 1;

        // Forecast strictly for NEXT MONTH only (lastActualMonth + 1)
        for ($m = 1; $m <= 12; $m++) {
            if ($m === $nextMonthIdx && $m <= 12) {
                $predZst = round(max(0, $m_zst * $m + $c_zst), 3);
                $predZlt = round(max(0, $m_zlt * $m + $c_zlt), 3);
                $zst_forecast[$m - 1] = $predZst;
                $zlt_forecast[$m - 1] = $predZlt;
                $has_forecast = true;
            } else {
                $zst_forecast[$m - 1] = null;
                $zlt_forecast[$m - 1] = null;
            }
        }

        $nextMonthIdx = $lastActualMonth + 1;
        if ($nextMonthIdx <= 12) {
            $nextZst = $zst_forecast[$nextMonthIdx - 1];
            $monthNames = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            $nextMonthName = $monthNames[$nextMonthIdx - 1];

            if ($nextZst >= 4.0) {
                $ai_forecast_text = "🤖 <strong>AI Forecast ({$nextMonthName}):</strong> Diproyeksikan ZST = <strong>" . number_format($nextZst, 2) . "</strong> <span style=\"color:#34d399; margin-left: 4px;\">(✅ Stabil & Memenuhi Target ZST ≥ 4.0)</span>";
            } else if ($nextZst >= 3.0) {
                $ai_forecast_text = "🤖 <strong>AI Forecast ({$nextMonthName}):</strong> Diproyeksikan ZST = <strong>" . number_format($nextZst, 2) . "</strong> <span style=\"color:#f59e0b; margin-left: 4px;\">(⚠️ Waspada: Tren Menurun Mendekati Limit)</span>";
            } else {
                $ai_forecast_text = "🤖 <strong>AI Forecast ({$nextMonthName}):</strong> Diproyeksikan ZST = <strong>" . number_format($nextZst, 2) . "</strong> <span style=\"color:#f87171; margin-left: 4px;\">(🚨 Kritis: Diproyeksikan Di Bawah Spec 3.0)</span>";
            }
        }
    }

    // Determine Historical Trend Conclusion
    $forecast_conclusion = "Data tidak cukup untuk menyimpulkan tren.";
    $actual_values = array_filter($zst_actual, function($v) { return $v !== null; });

    if (count($actual_values) >= 2) {
        $actual_keys = array_keys($actual_values);
        $last_key = end($actual_keys);
        $prev_key = prev($actual_keys);

        $last_val = $actual_values[$last_key];
        $prev_val = $actual_values[$prev_key];
        $trend = $last_val - $prev_val;

        if ($last_val < 3.0) {
            $forecast_conclusion = "🚨 <strong>Kritis:</strong> Performa (ZST) turun di bawah batas minimum 3.0 pada data terakhir. Segera lakukan evaluasi!";
        } else if ($last_val < 4.0 && $trend < 0) {
            $forecast_conclusion = "⚠️ <strong>Waspada:</strong> Performa (ZST) menurun di bawah target 4.0. Perlu pemantauan ketat.";
        } else if ($last_val < 4.0 && $trend >= 0) {
            $forecast_conclusion = "⚠️ <strong>Waspada:</strong> Performa (ZST) masih di bawah target 4.0 meskipun ada tren perbaikan.";
        } else if ($trend > 0) {
            $forecast_conclusion = "✅ <strong>Positif:</strong> Performa (ZST) menunjukkan peningkatan dan berada di batas aman (≥ 4.0).";
        } else {
            $forecast_conclusion = "✅ <strong>Stabil:</strong> Performa (ZST) terpantau stabil dan terkendali di atas batas target 4.0.";
        }
    } else if (count($actual_values) == 1) {
        $last_val = end($actual_values);
        if ($last_val < 3.0) {
            $forecast_conclusion = "🚨 <strong>Kritis:</strong> Performa (ZST) di bawah batas minimum 3.0.";
        } else if ($last_val < 4.0) {
            $forecast_conclusion = "⚠️ <strong>Waspada:</strong> Performa (ZST) berada di bawah target 4.0.";
        } else {
            $forecast_conclusion = "✅ <strong>Stabil:</strong> Performa (ZST) terkendali di atas target 4.0.";
        }
    }

    echo json_encode([
        "zst_actual"          => $zst_actual,
        "zlt_actual"          => $zlt_actual,
        "zst_forecast"        => $zst_forecast,
        "zlt_forecast"        => $zlt_forecast,
        "has_forecast"        => $has_forecast,
        "ai_forecast_text"    => $ai_forecast_text,
        "spec_id"             => $spec_id,
        "year"                => $year,
        "forecast_conclusion" => $forecast_conclusion
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>

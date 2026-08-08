<?php
/**
 * c_dtc_ai_train.php
 * Called via AJAX to trigger AI model training for a given spec_id.
 * Fetches historical ZST data from DB, trains model via Python or Native PHP fallback.
 */
require_once '../../../config/config.php';
header('Content-Type: application/json');

function trainModelNativePHP($spec_id, $history) {
    $n = count($history);
    if ($n < 2) return false;

    $sumX = 0; $sumY_zst = 0; $sumY_zlt = 0;
    $sumXY_zst = 0; $sumXY_zlt = 0; $sumXX = 0;

    foreach ($history as $h) {
        $x = floatval($h['month_index']);
        $yzst = floatval($h['zst']);
        $yzlt = floatval($h['zlt']);

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

    $modelsDir = __DIR__ . '/../../python/ai_forecast/models';
    if (!file_exists($modelsDir)) {
        @mkdir($modelsDir, 0777, true);
    }

    $modelData = [
        'spec_id' => $spec_id,
        'data_count' => $n,
        'degree' => 1,
        'zst_coef' => ['m' => $m_zst, 'c' => $c_zst],
        'zlt_coef' => ['m' => $m_zlt, 'c' => $c_zlt],
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $path = $modelsDir . "/zscore_model_{$spec_id}.json";
    file_put_contents($path, json_encode($modelData));
    return true;
}

try {
    $spec_id = isset($_POST['spec_id']) ? intval($_POST['spec_id']) : 0;
    if ($spec_id === 0) throw new Exception("Invalid spec_id");

    $conn = getDBConnection();

    // Get all monthly ZST/ZLT for this spec across all parameters
    $sql = "
        SELECT 
            p.target_month,
            AVG(s.x_bar)   as avg_x_bar,
            AVG(s.std_dev) as avg_std_dev,
            MAX(spec.lsl)  as lsl,
            MAX(spec.usl)  as usl
        FROM dtc_inspection_sessions s
        JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
        JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
        WHERE p.spec_id = :spec_id
          AND s.is_active = 1
          AND p.target_month IS NOT NULL
        GROUP BY p.target_month
        ORDER BY p.target_month ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':spec_id' => $spec_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) < 2) {
        throw new Exception("Data historis tidak mencukupi untuk pelatihan AI. Diperlukan minimal 2 bulan data historis.");
    }

    // Convert to month_index (sequential integer starting from 1)
    $history = [];
    $base_month = null;
    foreach ($rows as $row) {
        if (!$base_month) $base_month = $row['target_month'];

        $base_ts  = strtotime($base_month . '-01');
        $curr_ts  = strtotime($row['target_month'] . '-01');
        $month_index = round(($curr_ts - $base_ts) / (30.44 * 24 * 3600)) + 1;

        $std  = floatval($row['avg_std_dev']);
        $xbar = floatval($row['avg_x_bar']);
        $lsl  = floatval($row['lsl']);
        $usl  = floatval($row['usl']);

        if ($std > 0) {
            $cpu = ($usl - $xbar) / (3 * $std);
            $cpl = ($xbar - $lsl) / (3 * $std);
            $cpk = min($cpu, $cpl);
            $zst = round(3 * $cpk, 3);
            $zlt = round($zst - 1.5, 3);

            $history[] = [
                'target_month' => $row['target_month'],
                'month_index'  => $month_index,
                'zst'          => $zst,
                'zlt'          => $zlt
            ];
        }
    }

    if (count($history) < 2) {
        throw new Exception("Data Z-Score historis belum mencukupi untuk melatih model AI.");
    }

    // Attempt Python execution first if Python binary is available
    $pythonExec = 'python';
    $pythonOutput = @shell_exec("$pythonExec --version 2>&1");
    $pythonAvailable = ($pythonOutput && strpos(strtolower($pythonOutput), 'python') !== false && strpos($pythonOutput, 'not found') === false);

    if ($pythonAvailable) {
        $python_input = json_encode(['spec_id' => $spec_id, 'history' => $history]);
        $tmp_file     = tempnam(sys_get_temp_dir(), 'ai_train_') . '.json';
        file_put_contents($tmp_file, $python_input);

        $script_path = escapeshellarg(__DIR__ . '/../../python/ai_forecast/train_zscore_model.py');
        $tmp_arg     = escapeshellarg($tmp_file);

        $command = "$pythonExec $script_path $tmp_arg 2>&1";
        $output  = shell_exec($command);
        @unlink($tmp_file);

        $result = json_decode($output, true);
        if ($result && isset($result['status']) && $result['status'] === 'success') {
            echo json_encode([
                'status'     => 'success',
                'message'    => "Model AI (Python Scikit-Learn) berhasil dilatih dengan " . count($history) . " bulan data.",
                'spec_id'    => $spec_id,
                'data_count' => count($history),
                'engine'     => 'Python Scikit-Learn'
            ]);
            exit;
        }
    }

    // Native PHP Fallback Regression Engine
    trainModelNativePHP($spec_id, $history);

    echo json_encode([
        'status'     => 'success',
        'message'    => "Model AI Regression berhasil dilatih dengan " . count($history) . " bulan data historis.",
        'spec_id'    => $spec_id,
        'data_count' => count($history),
        'engine'     => 'Native PHP AI Engine'
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

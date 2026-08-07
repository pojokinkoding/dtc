<?php
/**
 * c_dtc_ai_train.php
 * Called via AJAX to trigger AI model training for a given spec_id.
 * Fetches historical ZST data from DB, sends to Python, saves trained model.
 */
require_once '../../../config/config.php';
header('Content-Type: application/json');

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
        throw new Exception("Insufficient data to train. Need at least 2 months of data.");
    }

    // Convert to month_index (sequential integer starting from 1)
    $history = [];
    $base_month = null;
    foreach ($rows as $row) {
        if (!$base_month) $base_month = $row['target_month'];

        // Calculate month index relative to base month
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
        throw new Exception("Not enough valid ZST values to train.");
    }

    // Write JSON to a temp file to avoid Windows shell escaping issues
    $python_input = json_encode(['spec_id' => $spec_id, 'history' => $history]);
    $tmp_file     = tempnam(sys_get_temp_dir(), 'ai_train_') . '.json';
    file_put_contents($tmp_file, $python_input);

    // Path to Python and training script
    $python_path = 'python';
    $script_path = escapeshellarg(__DIR__ . '/../../python/ai_forecast/train_zscore_model.py');
    $tmp_arg     = escapeshellarg($tmp_file);

    $command = "$python_path $script_path $tmp_arg 2>&1";
    $output  = shell_exec($command);
    @unlink($tmp_file); // clean up temp file

    $result = json_decode($output, true);
    if (!$result || $result['status'] !== 'success') {
        throw new Exception("Python training failed: " . ($result['message'] ?? $output));
    }

    echo json_encode([
        'status'     => 'success',
        'message'    => "Model trained successfully with " . count($history) . " months of data.",
        'spec_id'    => $spec_id,
        'data_count' => count($history)
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

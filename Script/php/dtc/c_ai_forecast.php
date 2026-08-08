<?php
// c_ai_forecast.php - AI Forecast Predict Endpoint with Native PHP Fallback
header('Content-Type: application/json');

function predictNativePHP($spec_id, $forecast_indices) {
    $modelsDir = __DIR__ . '/../../python/ai_forecast/models';
    $jsonPath = $modelsDir . "/zscore_model_{$spec_id}.json";

    if (!file_exists($jsonPath)) {
        return ["status" => "error", "message" => "Model AI belum dilatih untuk spec_id={$spec_id}. Silakan klik tombol 'Train AI Model' terlebih dahulu."];
    }

    $model = json_decode(file_get_contents($jsonPath), true);
    $m_zst = $model['zst_coef']['m'] ?? 0;
    $c_zst = $model['zst_coef']['c'] ?? 0;
    $m_zlt = $model['zlt_coef']['m'] ?? 0;
    $c_zlt = $model['zlt_coef']['c'] ?? 0;

    $zst_forecast = [];
    $zlt_forecast = [];

    foreach ($forecast_indices as $x) {
        $zst_val = round($m_zst * $x + $c_zst, 3);
        $zlt_val = round($m_zlt * $x + $c_zlt, 3);
        $zst_forecast[] = max(0, $zst_val);
        $zlt_forecast[] = max(0, $zlt_val);
    }

    return [
        "status" => "success",
        "spec_id" => $spec_id,
        "month_indices" => $forecast_indices,
        "zst_forecast" => $zst_forecast,
        "zlt_forecast" => $zlt_forecast,
        "engine" => "Native PHP AI Engine"
    ];
}

try {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'predict') {
        $spec_id = isset($_GET['spec_id']) ? intval($_GET['spec_id']) : 0;
        $monthIndex = isset($_GET['month_index']) ? intval($_GET['month_index']) : 6;
        $forecast_indices = isset($_GET['forecast_indices']) ? json_decode($_GET['forecast_indices'], true) : [$monthIndex];
        if (!is_array($forecast_indices) || empty($forecast_indices)) {
            $forecast_indices = [$monthIndex];
        }

        // Test if Python is available
        $pythonExec = 'python';
        $pythonOutput = @shell_exec("$pythonExec --version 2>&1");
        $pythonAvailable = ($pythonOutput && strpos(strtolower($pythonOutput), 'python') !== false && strpos($pythonOutput, 'not found') === false);

        if ($pythonAvailable) {
            $scriptPath = realpath(__DIR__ . '/../../python/ai_forecast/predict_zscore.py');
            if ($scriptPath) {
                $inputPayload = json_encode(['spec_id' => $spec_id, 'forecast_month_indices' => $forecast_indices]);
                $tmpFile = tempnam(sys_get_temp_dir(), 'ai_pred_') . '.json';
                file_put_contents($tmpFile, $inputPayload);

                $command = escapeshellcmd("$pythonExec \"$scriptPath\" " . escapeshellarg($tmpFile));
                $output = shell_exec($command);
                @unlink($tmpFile);

                $res = json_decode($output, true);
                if ($res && isset($res['status']) && $res['status'] === 'success') {
                    echo json_encode($res);
                    exit;
                }
            }
        }

        // Native PHP Fallback Prediction
        $resNative = predictNativePHP($spec_id, $forecast_indices);
        echo json_encode($resNative);

    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

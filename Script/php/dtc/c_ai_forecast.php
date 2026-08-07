<?php
// c_ai_forecast.php
header('Content-Type: application/json');

try {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'predict') {
        $monthIndex = isset($_GET['month_index']) ? intval($_GET['month_index']) : 6;
        
        // Define paths
        $pythonExecutable = 'python'; // Assumes python is in system PATH. For XAMPP Windows, this is usually correct if installed globally.
        $scriptPath = realpath(__DIR__ . '/../../python/ai_forecast/predict_zscore.py');
        
        if (!$scriptPath) {
            throw new Exception("Python script not found.");
        }
        
        // Use escapeshellarg for safety
        $command = escapeshellcmd("$pythonExecutable \"$scriptPath\" " . escapeshellarg($monthIndex));
        
        // Execute the command
        $output = shell_exec($command);
        
        if ($output === null) {
            throw new Exception("Failed to execute python script or no output returned.");
        }
        
        // Return raw JSON output from Python
        echo $output;
    } else {
        echo json_encode(["error" => "Invalid action"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>

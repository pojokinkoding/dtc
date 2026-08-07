<?php
// Script/php/dtc/c_settings_get.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT setting_value FROM dtc_app_settings WHERE setting_key = 'time_matrix_labels'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $labels = [];
    if ($row && $row['setting_value']) {
        // Handle CLOB if necessary
        $val = is_resource($row['setting_value']) ? stream_get_contents($row['setting_value']) : $row['setting_value'];
        $labels = json_decode($val, true);
    }
    
    if (empty($labels)) {
        // Fallback default
        $labels = ['07:30', '09:40', '12:40', '14:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'time_matrix_labels' => $labels
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

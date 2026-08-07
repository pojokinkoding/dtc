<?php
// Script/php/dtc/c_settings_save.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }
    
    $conn = getDBConnection();
    
    $ref01_labels = isset($_POST['ref01_labels']) ? $_POST['ref01_labels'] : [];
    $ref02_labels = isset($_POST['ref02_labels']) ? $_POST['ref02_labels'] : [];
    
    // Clean up empty labels
    $ref01_labels = array_values(array_filter(array_map('trim', $ref01_labels)));
    $ref02_labels = array_values(array_filter(array_map('trim', $ref02_labels)));
    
    $settingsToSave = [
        'time_matrix_labels_REF 01' => json_encode($ref01_labels),
        'time_matrix_labels_REF 02' => json_encode($ref02_labels)
    ];
    
    foreach ($settingsToSave as $key => $jsonValue) {
        // Check if exists
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM dtc_app_settings WHERE setting_key = :key");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['cnt'] > 0) {
            $stmt_upd = $conn->prepare("UPDATE dtc_app_settings SET setting_value = :val WHERE setting_key = :key");
            $stmt_upd->execute([':val' => $jsonValue, ':key' => $key]);
        } else {
            $stmt_ins = $conn->prepare("INSERT INTO dtc_app_settings (setting_key, setting_value) VALUES (:key, :val)");
            $stmt_ins->execute([':val' => $jsonValue, ':key' => $key]);
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Settings saved successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

<?php
// Script/php/dtc/c_settings_save.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }
    
    $conn = getDBConnection();
    
    $action = $_POST['action'] ?? '';
    if ($action === 'set_refresh_interval') {
        $val = isset($_POST['bulk_auto_refresh_minutes']) ? (float)$_POST['bulk_auto_refresh_minutes'] : 1;
        if ($val <= 0) $val = 1;
        
        $stmt = $conn->prepare("
            INSERT INTO dtc_app_settings (setting_key, setting_value) 
            VALUES ('bulk_auto_refresh_minutes', :val)
            ON DUPLICATE KEY UPDATE setting_value = :val2
        ");
        $stmt->execute([':val' => (string)$val, ':val2' => (string)$val]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Interval auto refresh berhasil diperbarui.',
            'bulk_auto_refresh_minutes' => $val
        ]);
        exit;
    }
    
    $ref01_labels = isset($_POST['ref01_labels']) ? $_POST['ref01_labels'] : [];
    $ref02_labels = isset($_POST['ref02_labels']) ? $_POST['ref02_labels'] : [];
    $bulk_auto_refresh = isset($_POST['bulk_auto_refresh_minutes']) ? (float)$_POST['bulk_auto_refresh_minutes'] : 1;
    if ($bulk_auto_refresh <= 0) $bulk_auto_refresh = 1;
    
    // Clean up empty labels and normalize 24:30 -> 00:30
    $ref01_labels = array_values(array_filter(array_map('trim', $ref01_labels)));
    $ref02_labels = array_values(array_filter(array_map('trim', $ref02_labels)));
    $ref01_labels = normalizeTimeLabels($ref01_labels);
    $ref02_labels = normalizeTimeLabels($ref02_labels);
    
    $settingsToSave = [
        'time_matrix_labels_REF 01' => json_encode($ref01_labels),
        'time_matrix_labels_REF 02' => json_encode($ref02_labels),
        'bulk_auto_refresh_minutes' => (string)$bulk_auto_refresh
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

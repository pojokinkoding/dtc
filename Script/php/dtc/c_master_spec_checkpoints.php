<?php
require_once '../../../config/config.php';
header('Content-Type: application/json');

try {
    $specId = (int)($_GET['spec_id'] ?? 0);
    if ($specId <= 0) throw new Exception('Master Spec tidak valid.');
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT * FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id ORDER BY sort_order, master_checkpoint_id');
    $stmt->execute([':spec_id' => $specId]);
    $checkpoints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($checkpoints as &$cp) {
        if ($cp['lsl'] !== null) $cp['lsl'] = number_format((float)$cp['lsl'], 1, '.', '');
        if ($cp['target_value'] !== null) $cp['target_value'] = number_format((float)$cp['target_value'], 1, '.', '');
        if ($cp['usl'] !== null) $cp['usl'] = number_format((float)$cp['usl'], 1, '.', '');
    }
    echo json_encode(['status' => 'success', 'data' => $checkpoints]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

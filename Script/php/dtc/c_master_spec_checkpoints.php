<?php
require_once '../../../config/config.php';
header('Content-Type: application/json');

try {
    $specId = (int)($_GET['spec_id'] ?? 0);
    if ($specId <= 0) throw new Exception('Master Spec tidak valid.');
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT * FROM dtc_master_spec_checkpoints WHERE spec_id = :spec_id ORDER BY sort_order, master_checkpoint_id');
    $stmt->execute([':spec_id' => $specId]);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

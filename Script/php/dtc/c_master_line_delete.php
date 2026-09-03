<?php
// c_master_line_delete.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

try {
    $conn = getDBConnection();
    ensureMasterLinesAndSectionsTables($conn);

    $lineId = isset($_POST['line_id']) ? intval($_POST['line_id']) : 0;
    if ($lineId <= 0) {
        throw new Exception('ID Line tidak valid.');
    }

    $stmtLine = $conn->prepare("SELECT line_name FROM dtc_master_lines WHERE line_id = :id");
    $stmtLine->execute([':id' => $lineId]);
    $lineName = $stmtLine->fetchColumn();

    if (!$lineName) {
        throw new Exception('Data Line tidak ditemukan.');
    }

    // Check if line is referenced in Master Specs
    $stmtSpecs = $conn->prepare("SELECT COUNT(*) FROM dtc_master_dtc_specs WHERE UPPER(line_name) = UPPER(:line_name)");
    $stmtSpecs->execute([':line_name' => $lineName]);
    $specCount = (int)$stmtSpecs->fetchColumn();

    if ($specCount > 0) {
        throw new Exception("Line '$lineName' tidak dapat dihapus karena sedang digunakan pada $specCount Master Spec. Hapus atau ubah Master Spec terkait terlebih dahulu.");
    }

    // Delete Line
    $stmtDel = $conn->prepare("DELETE FROM dtc_master_lines WHERE line_id = :id");
    $stmtDel->execute([':id' => $lineId]);

    echo json_encode([
        'status' => 'success',
        'message' => "Line '$lineName' berhasil dihapus."
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

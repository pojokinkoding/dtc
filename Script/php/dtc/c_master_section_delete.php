<?php
// c_master_section_delete.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

try {
    $conn = getDBConnection();
    ensureMasterLinesAndSectionsTables($conn);

    $sectionId = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
    if ($sectionId <= 0) {
        throw new Exception('ID Section tidak valid.');
    }

    $stmtSection = $conn->prepare("SELECT section_name FROM dtc_master_sections WHERE section_id = :id");
    $stmtSection->execute([':id' => $sectionId]);
    $sectionName = $stmtSection->fetchColumn();

    if (!$sectionName) {
        throw new Exception('Data Section tidak ditemukan.');
    }

    // Check if section is referenced in Master Specs
    $stmtSpecs = $conn->prepare("SELECT COUNT(*) FROM dtc_master_dtc_specs WHERE UPPER(section_name) = UPPER(:section_name)");
    $stmtSpecs->execute([':section_name' => $sectionName]);
    $specCount = (int)$stmtSpecs->fetchColumn();

    if ($specCount > 0) {
        throw new Exception("Section '$sectionName' tidak dapat dihapus karena sedang digunakan pada $specCount Master Spec. Hapus atau ubah Master Spec terkait terlebih dahulu.");
    }

    // Delete Section
    $stmtDel = $conn->prepare("DELETE FROM dtc_master_sections WHERE section_id = :id");
    $stmtDel->execute([':id' => $sectionId]);

    echo json_encode([
        'status' => 'success',
        'message' => "Section '$sectionName' berhasil dihapus."
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

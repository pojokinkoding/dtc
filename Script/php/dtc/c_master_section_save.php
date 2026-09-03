<?php
// c_master_section_save.php
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
    $sectionName = trim($_POST['section_name'] ?? '');
    $lineName = trim($_POST['line_name'] ?? '') ?: null;
    $description = trim($_POST['description'] ?? '');
    $sortOrder = isset($_POST['sort_order']) && $_POST['sort_order'] !== '' ? intval($_POST['sort_order']) : 0;

    if ($sectionName === '') {
        throw new Exception('Nama Section wajib diisi.');
    }

    if ($sectionId > 0) {
        // Check duplicate excluding self
        if ($lineName) {
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM dtc_master_sections WHERE UPPER(section_name) = UPPER(:name) AND UPPER(COALESCE(line_name, '')) = UPPER(:line) AND section_id != :id");
            $stmtCheck->execute([':name' => $sectionName, ':line' => $lineName, ':id' => $sectionId]);
        } else {
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM dtc_master_sections WHERE UPPER(section_name) = UPPER(:name) AND line_name IS NULL AND section_id != :id");
            $stmtCheck->execute([':name' => $sectionName, ':id' => $sectionId]);
        }

        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new Exception("Section '$sectionName' sudah terdaftar.");
        }

        $stmt = $conn->prepare("UPDATE dtc_master_sections SET section_name = :name, line_name = :line, description = :desc, sort_order = :sort, updated_at = CURRENT_TIMESTAMP WHERE section_id = :id");
        $stmt->execute([
            ':name' => $sectionName,
            ':line' => $lineName,
            ':desc' => $description ?: null,
            ':sort' => $sortOrder,
            ':id' => $sectionId
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => "Section '$sectionName' berhasil diperbarui.",
            'section' => [
                'section_id' => $sectionId,
                'section_name' => $sectionName,
                'line_name' => $lineName,
                'description' => $description,
                'sort_order' => $sortOrder
            ]
        ]);
    } else {
        // Check duplicate
        if ($lineName) {
            $stmtCheck = $conn->prepare("SELECT section_id, section_name, line_name FROM dtc_master_sections WHERE UPPER(section_name) = UPPER(:name) AND UPPER(COALESCE(line_name, '')) = UPPER(:line)");
            $stmtCheck->execute([':name' => $sectionName, ':line' => $lineName]);
        } else {
            $stmtCheck = $conn->prepare("SELECT section_id, section_name, line_name FROM dtc_master_sections WHERE UPPER(section_name) = UPPER(:name) AND line_name IS NULL");
            $stmtCheck->execute([':name' => $sectionName]);
        }

        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            echo json_encode([
                'status' => 'success',
                'message' => "Section '$sectionName' sudah tersedia.",
                'section' => $existing
            ]);
            exit;
        }

        if ($sortOrder === 0) {
            $maxSort = $conn->query("SELECT COALESCE(MAX(sort_order), 0) FROM dtc_master_sections")->fetchColumn();
            $sortOrder = (int)$maxSort + 1;
        }

        $stmt = $conn->prepare("INSERT INTO dtc_master_sections (section_name, line_name, description, sort_order) VALUES (:name, :line, :desc, :sort)");
        $stmt->execute([
            ':name' => $sectionName,
            ':line' => $lineName,
            ':desc' => $description ?: null,
            ':sort' => $sortOrder
        ]);

        $newId = (int)$conn->lastInsertId();

        echo json_encode([
            'status' => 'success',
            'message' => "Section '$sectionName' berhasil ditambahkan.",
            'section' => [
                'section_id' => $newId,
                'section_name' => $sectionName,
                'line_name' => $lineName,
                'description' => $description,
                'sort_order' => $sortOrder
            ]
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

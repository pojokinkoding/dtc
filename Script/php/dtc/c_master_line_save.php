<?php
// c_master_line_save.php
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
    $lineName = trim($_POST['line_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sortOrder = isset($_POST['sort_order']) && $_POST['sort_order'] !== '' ? intval($_POST['sort_order']) : 0;

    if ($lineName === '') {
        throw new Exception('Nama Line wajib diisi.');
    }

    if ($lineId > 0) {
        // Check duplicate excluding self
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM dtc_master_lines WHERE UPPER(line_name) = UPPER(:name) AND line_id != :id");
        $stmtCheck->execute([':name' => $lineName, ':id' => $lineId]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new Exception("Line dengan nama '$lineName' sudah ada.");
        }

        // Get old line_name to cascade update if changed
        $stmtOld = $conn->prepare("SELECT line_name FROM dtc_master_lines WHERE line_id = :id");
        $stmtOld->execute([':id' => $lineId]);
        $oldLineName = $stmtOld->fetchColumn();

        $stmt = $conn->prepare("UPDATE dtc_master_lines SET line_name = :name, description = :desc, sort_order = :sort, updated_at = CURRENT_TIMESTAMP WHERE line_id = :id");
        $stmt->execute([
            ':name' => $lineName,
            ':desc' => $description ?: null,
            ':sort' => $sortOrder,
            ':id' => $lineId
        ]);

        // Cascade update in master sections if line_name matches
        if ($oldLineName && $oldLineName !== $lineName) {
            $stmtCascade = $conn->prepare("UPDATE dtc_master_sections SET line_name = :new_name WHERE line_name = :old_name");
            $stmtCascade->execute([':new_name' => $lineName, ':old_name' => $oldLineName]);
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Line '$lineName' berhasil diperbarui.",
            'line' => [
                'line_id' => $lineId,
                'line_name' => $lineName,
                'description' => $description,
                'sort_order' => $sortOrder
            ]
        ]);
    } else {
        // Check duplicate
        $stmtCheck = $conn->prepare("SELECT line_id, line_name FROM dtc_master_lines WHERE UPPER(line_name) = UPPER(:name)");
        $stmtCheck->execute([':name' => $lineName]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            // If already exists, return success with existing line info
            echo json_encode([
                'status' => 'success',
                'message' => "Line '$lineName' sudah tersedia.",
                'line' => $existing
            ]);
            exit;
        }

        if ($sortOrder === 0) {
            $maxSort = $conn->query("SELECT COALESCE(MAX(sort_order), 0) FROM dtc_master_lines")->fetchColumn();
            $sortOrder = (int)$maxSort + 1;
        }

        $stmt = $conn->prepare("INSERT INTO dtc_master_lines (line_name, description, sort_order) VALUES (:name, :desc, :sort)");
        $stmt->execute([
            ':name' => $lineName,
            ':desc' => $description ?: null,
            ':sort' => $sortOrder
        ]);

        $newId = (int)$conn->lastInsertId();

        echo json_encode([
            'status' => 'success',
            'message' => "Line '$lineName' berhasil ditambahkan.",
            'line' => [
                'line_id' => $newId,
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

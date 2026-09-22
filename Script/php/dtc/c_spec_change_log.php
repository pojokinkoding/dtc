<?php
// c_spec_change_log.php — Riwayat perubahan Master Spec (alasan/evident ikut tersimpan di sini)
require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    $specId = intval($_GET['spec_id'] ?? 0);
    if ($specId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'spec_id wajib diisi']);
        exit;
    }

    // Pastikan spec ada + dalam scope user (non-admin hanya area sendiri)
    $stmtSpec = $conn->prepare("SELECT spec_id, model_name, item_check_name, line_name, section_name FROM dtc_master_dtc_specs WHERE spec_id = :id");
    $stmtSpec->execute([':id' => $specId]);
    $spec = $stmtSpec->fetch(PDO::FETCH_ASSOC);
    if (!$spec) {
        echo json_encode(['status' => 'error', 'message' => 'Spec tidak ditemukan']);
        exit;
    }
    if (function_exists('isLineSectionAllowed') && !isLineSectionAllowed($spec['line_name'] ?? '', $spec['section_name'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda hanya dapat membuka data untuk area Anda.']);
        exit;
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS dtc_spec_change_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        spec_id INT NOT NULL,
        field_name VARCHAR(100) NOT NULL,
        old_value TEXT,
        new_value TEXT,
        change_reason TEXT,
        changed_by INT,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_spec_change_log_spec (spec_id),
        INDEX idx_spec_change_log_date (changed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $conn->prepare("
        SELECT l.log_id, l.field_name, l.old_value, l.new_value, l.change_reason, l.changed_at,
               COALESCE(u.full_name, u.username, CONCAT('User #', l.changed_by)) AS changed_by_name
        FROM dtc_spec_change_log l
        LEFT JOIN dtc_users u ON u.user_id = l.changed_by
        WHERE l.spec_id = :id
        ORDER BY l.changed_at DESC, l.log_id DESC
        LIMIT 200
    ");
    $stmt->execute([':id' => $specId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'spec' => $spec, 'data' => $logs], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

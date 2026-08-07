<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();
$target_month = '2026-01';

// Update all inspection sessions for target_month to is_closed = 1
$stmt = $conn->prepare("
    UPDATE dtc_inspection_sessions
    SET is_closed = 1
    WHERE inspection_date LIKE :month_like
      AND is_closed = 0
");
$stmt->execute([':month_like' => $target_month . '%']);
$affected = $stmt->rowCount();

echo "=== MONTH CLOSING (2026-01) ===\n";
echo "Successfully updated $affected open sessions to CLOSED (is_closed = 1) for $target_month.\n\n";

// Verify total status
$stmt_check = $conn->prepare("
    SELECT is_closed, COUNT(*) as cnt
    FROM dtc_inspection_sessions
    WHERE inspection_date LIKE :month_like
    GROUP BY is_closed
");
$stmt_check->execute([':month_like' => $target_month . '%']);
$summary = $stmt_check->fetchAll(PDO::FETCH_ASSOC);

echo "Final Status Summary for $target_month:\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
?>

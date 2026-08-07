<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

// Check is_closed status of sessions for 2026-01
$stmt = $conn->query("
    SELECT s.is_closed, COUNT(*) as total
    FROM dtc_inspection_sessions s
    WHERE s.inspection_date LIKE '2026-01%'
    GROUP BY s.is_closed
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== INSPECTION SESSIONS CLOSING STATUS (2026-01) ===\n";
echo json_encode($results, JSON_PRETTY_PRINT) . "\n\n";

// Check parameters in 2026-01
$stmt2 = $conn->query("
    SELECT s.parameter_id, p.section_name, p.line_name, p.model_name, COUNT(*) as total_sessions, 
           SUM(CASE WHEN s.is_closed = 1 THEN 1 ELSE 0 END) as closed_sessions,
           SUM(CASE WHEN s.is_closed = 0 THEN 1 ELSE 0 END) as open_sessions
    FROM dtc_inspection_sessions s
    JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
    WHERE s.inspection_date LIKE '2026-01%'
    GROUP BY s.parameter_id
");
$params = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "=== PARAMETER SESSIONS BREAKDOWN (2026-01) ===\n";
echo json_encode($params, JSON_PRETTY_PRINT) . "\n";
?>

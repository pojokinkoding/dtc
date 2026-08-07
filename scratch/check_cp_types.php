<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$stmt = $conn->query("
    SELECT c.checkpoint_id, p.section_name, c.checkpoint_name, c.checkpoint_type, c.spec_value, c.lsl, c.target_value, c.usl
    FROM dtc_checkpoints c
    JOIN dtc_master_parameters p ON c.parameter_id = p.parameter_id
    WHERE p.target_month = '2026-01' AND p.line_name = 'REF 01' AND p.section_name IN ('Clamping', 'Charging')
    ORDER BY p.parameter_id, c.sort_order ASC
");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

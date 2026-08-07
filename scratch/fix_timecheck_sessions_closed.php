<?php
// fix_timecheck_sessions_closed.php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

// Update dtc_inspection_sessions for Time Check parameters where measurements exist
$sql = "
    UPDATE dtc_inspection_sessions s
    JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    SET s.is_closed = 1
    WHERE spec.data_type = 'Time Check'
    AND EXISTS (
        SELECT 1 FROM dtc_measurements m 
        WHERE m.session_id = s.session_id AND m.sample_value != ''
    )
";
$affected = $conn->exec($sql);
echo "Updated $affected Time Check sessions to is_closed = 1.\n";

// Also update import_timecheck_generic.php so future imports insert is_closed = 1
?>

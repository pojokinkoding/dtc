<?php
ob_start();
require_once __DIR__ . '/../config/config.php';

$_SERVER['REQUEST_METHOD'] = 'POST';
$conn = getDBConnection();

// Find an existing measurement with sample_value != '' on an UNCLOSED session
$meas = $conn->query("
    SELECT m.measurement_id, m.session_id, m.checkpoint_id, m.sample_label, m.sample_value, s.parameter_id, s.inspection_date
    FROM dtc_measurements m
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
    WHERE m.sample_value IS NOT NULL AND m.sample_value != '' AND (s.is_closed = 0 OR s.is_closed IS NULL)
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$meas) {
    echo "No unclosed session with filled value found, fetching any session and ensuring is_closed = 0.\n";
    $meas = $conn->query("
        SELECT m.measurement_id, m.session_id, m.checkpoint_id, m.sample_label, m.sample_value, s.parameter_id, s.inspection_date
        FROM dtc_measurements m
        JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
        WHERE m.sample_value IS NOT NULL AND m.sample_value != ''
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    $conn->exec("UPDATE dtc_inspection_sessions SET is_closed = 0 WHERE session_id = " . intval($meas['session_id']));
}

$_POST = [
    'inspection_date' => $meas['inspection_date'],
    'time_label' => 'ALL',
    'items' => [
        [
            'parameter_id' => $meas['parameter_id'],
            'checkpoint_id' => $meas['checkpoint_id'] ?: 0,
            'sample_label' => $meas['sample_label'],
            'value' => '999.9',
            'checkpoint_type' => 'Quantitative',
            'name' => 'Test Item'
        ]
    ]
];

chdir(__DIR__ . '/../Script/php/dtc');

// Test as Operator
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'Operator';
ob_clean();
require 'c_dtc_bulk_save.php';
$outOp = ob_get_clean();
$resOp = json_decode($outOp, true);

// Test as Admin
ob_start();
$_SESSION['role'] = 'Admin';
require 'c_dtc_bulk_save.php';
$outAdmin = ob_get_clean();
$resAdmin = json_decode($outAdmin, true);

// Restore DB
$conn->prepare("UPDATE dtc_measurements SET sample_value = :val WHERE measurement_id = :mid")->execute([':val' => $meas['sample_value'], ':mid' => $meas['measurement_id']]);

echo "=== OPERATOR RESULT ===\n";
print_r($resOp);

echo "\n=== ADMIN RESULT ===\n";
print_r($resAdmin);

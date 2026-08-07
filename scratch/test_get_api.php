<?php
// test_get_api.php
require_once __DIR__ . '/../config/config.php';
$_GET['model'] = 'Time Check Proses MC 1';
$_GET['line'] = 'REF 01';
$_GET['section'] = 'V Forming';
$_GET['month'] = '2026-01';

chdir(__DIR__ . '/../Script/php/dtc');
ob_start();
require 'c_dtc_matrix_qualitative_get.php';
$output = ob_get_clean();

$res = json_decode($output, true);
foreach ($res['data'] as $cp) {
    echo "Checkpoint: " . $cp['checkpoint_name'] . " (Type: " . $cp['checkpoint_type'] . ")\n";
    echo "  Xbar: " . json_encode(array_slice($cp['chart_data']['xbar'], 0, 10)) . "\n";
    echo "  R:    " . json_encode(array_slice($cp['chart_data']['r'], 0, 10)) . "\n";
}
?>

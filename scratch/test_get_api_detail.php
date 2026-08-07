<?php
require_once __DIR__ . '/../config/config.php';

$_GET['param_id'] = 3769;
$_GET['model'] = 'Y-331';
$_GET['line'] = 'REF 01';
$_GET['section'] = 'H Press Out Door';
$_GET['month'] = '2026-01';

ob_start();
require __DIR__ . '/../Script/php/dtc/c_dtc_matrix_qualitative_get.php';
$output = ob_get_clean();

$res = json_decode($output, true);
echo "Status: " . ($res['status'] ?? 'error') . "\n";
echo "Checkpoints returned: " . count($res['data'] ?? []) . "\n\n";

foreach ($res['data'] ?? [] as $cp) {
    echo "Checkpoint #{$cp['checkpoint_id']} [{$cp['checkpoint_name']}] | Type: {$cp['checkpoint_type']} | Spec: {$cp['spec_value']}\n";
    echo "  LSL: " . json_encode($cp['lsl']) . " | Target: " . json_encode($cp['target_value']) . " | USL: " . json_encode($cp['usl']) . "\n";
    
    // Sample matrix values
    $matrix = $cp['matrix'];
    $sampleVals = [];
    foreach ($matrix as $label => $days) {
        foreach ($days as $day => $val) {
            $sampleVals[] = "Day$day($label):$val";
            if (count($sampleVals) >= 5) break 2;
        }
    }
    echo "  Sample Matrix Values: " . implode(', ', $sampleVals) . "\n";
    echo "  Chart Xbar sample: " . json_encode(array_slice($cp['chart_data']['xbar'] ?? [], 0, 10)) . "\n\n";
}
?>

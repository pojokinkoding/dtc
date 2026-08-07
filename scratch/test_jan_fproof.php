<?php
chdir(__DIR__ . '/../Script/php/dtc');
$_GET['month'] = '2026-01';
ob_start();
require_once 'c_missing_data.php';
$output = ob_get_clean();

$json = json_decode($output, true);
echo "Status: " . ($json['status'] ?? 'error') . "\n";
$fproofItems = [];
if (isset($json['data'])) {
    foreach ($json['data'] as $item) {
        if ($item['data_type'] === 'F/Proof') {
            $fproofItems[] = $item['line_name'] . ' ___ ' . $item['section_name'] . ' ___ ' . $item['item_check_name'];
        }
    }
}

echo "F/Proof items in 2026-01 c_missing_data:\n";
echo json_encode($fproofItems, JSON_PRETTY_PRINT) . "\n";
?>

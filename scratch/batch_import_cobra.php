<?php
$files = [
    '2026-03' => '202603 CHECK SHEET CYCLE COBRA REF01.xlsx',
    '2026-04' => '202604 Check Sheet Cycle COBRA REF01.xlsx',
    '2026-05' => '202605 Check Sheet Cycle COBRA REF01.xlsx',
    '2026-06' => '202606 CHECK SHEET CYCLE COBRA REF01.xlsx',
];

foreach ($files as $month => $fileName) {
    echo "====================================================\n";
    echo "IMPORTING FOR MONTH: $month ($fileName)\n";
    echo "====================================================\n";
    $cmd = sprintf('C:\xampp\php\php.exe %s "%s" "%s" "REF 01"', __DIR__ . '/import_cobra_generic.php', $fileName, $month);
    system($cmd);
    echo "\n\n";
}
?>

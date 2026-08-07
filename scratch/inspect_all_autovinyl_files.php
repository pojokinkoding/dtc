<?php
$files = [
    '202602 Check Sheet AutoVinyl Cutting REF02.xlsx',
    '202603 CHECK SHEET AUTO VINYL CUTTING REF02.xlsx',
    '202604  CHECK SHEET AUTO CUTTING VINYL REF02.xlsx',
    '202605  AutoVinyl Cutting Checksheet NR1_NR2_New.xlsx',
    '202606  CHECK SHEET AutoVinyl Cutting  NR1_NR2.xlsx'
];

foreach ($files as $fName) {
    $file = __DIR__ . '/../' . $fName;
    echo "========================================================\n";
    echo "=== FILE: '$fName' ===\n";
    echo "========================================================\n";
    if (!file_exists($file)) {
        echo "FILE NOT FOUND!\n\n";
        continue;
    }

    $zip = new ZipArchive();
    if ($zip->open($file) !== TRUE) {
        echo "FAILED TO OPEN ZIP!\n\n";
        continue;
    }

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $wb = simplexml_load_string($workbookXml);

    $sheets = [];
    foreach ($wb->sheets->sheet as $sheet) {
        $name = (string)$sheet['name'];
        $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $sheets[] = ['name' => trim($name), 'rId' => $rId];
    }

    echo "Sheets found (" . count($sheets) . "): ";
    $sNames = array_map(function($s){ return "'{$s['name']}'"; }, $sheets);
    echo implode(', ', $sNames) . "\n\n";
}
?>

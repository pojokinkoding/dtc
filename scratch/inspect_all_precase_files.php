<?php
$files = glob(__DIR__ . '/../ctp_precase_*.xlsx');

foreach ($files as $file) {
    echo "========================================================\n";
    echo "File: " . basename($file) . "\n";
    $zip = new ZipArchive();
    if ($zip->open($file) !== TRUE) {
        echo "Failed to open zip\n";
        continue;
    }

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $wb = simplexml_load_string($workbookXml);
    $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $sheets = [];
    foreach ($wb->sheets->sheet as $sheet) {
        $sheets[] = (string)$sheet['name'];
    }

    echo "Total sheets: " . count($sheets) . " | Sheets: " . implode(', ', $sheets) . "\n";
    $zip->close();
}

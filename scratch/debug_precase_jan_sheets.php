<?php
$file = __DIR__ . '/../ctp_precase_jan.xlsx';
$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Failed to open zip\n");

$workbookXml = $zip->getFromName('xl/workbook.xml');
echo "xl/workbook.xml content:\n";
echo substr($workbookXml, 0, 3000) . "\n\n";

for ($i = 0; $i < $zip->numFiles; $i++) {
    $filename = $zip->getNameIndex($i);
    if (strpos($filename, 'xl/worksheets/') === 0) {
        echo "Worksheet file: $filename\n";
    }
}

$zip->close();

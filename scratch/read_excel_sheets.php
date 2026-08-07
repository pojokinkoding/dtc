<?php
// read_excel_sheets.php
$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
if (!file_exists($file)) die("File not found\n");
$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Failed to open excel zip file.\n");
$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);
$wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $sheets[] = ['name' => (string)$sheet['name'], 'rId' => (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id']];
}
echo json_encode($sheets, JSON_PRETTY_PRINT) . "\n";
$zip->close();
?>

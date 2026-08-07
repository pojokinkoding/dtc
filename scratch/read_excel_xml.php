<?php
// read_excel_xml.php
$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
$zip = new ZipArchive();
$zip->open($file);
$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$rels = simplexml_load_string($relsXml);
$targetMap = [];
foreach ($rels->Relationship as $rel) {
    $targetMap[(string)$rel['Id']] = (string)$rel['Target'];
}
$wbXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($wbXml);
$sheetName = 'Time Check Proses MC 2';
$rId = '';
foreach ($wb->sheets->sheet as $sheet) {
    if ((string)$sheet['name'] === $sheetName) {
        $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    }
}
$sheetPath = 'xl/' . $targetMap[$rId];
$sheetXml = $zip->getFromName($sheetPath);
$xml = simplexml_load_string($sheetXml);

foreach ($xml->sheetData->row as $row) {
    $rNum = (int)$row['r'];
    if ($rNum >= 8 && $rNum <= 9) {
        echo $row->asXML() . "\n";
    }
}
?>

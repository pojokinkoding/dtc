<?php
$file = __DIR__ . '/../ctp_pucase_apr.xlsx';
$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Failed to open zip\n");

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);
$wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $sheets[] = [
        'name' => (string)$sheet['name'],
        'rId' => (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id']
    ];
}

$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$rels = simplexml_load_string($relsXml);
$targetMap = [];
foreach ($rels->Relationship as $rel) {
    $targetMap[(string)$rel['Id']] = (string)$rel['Target'];
}

$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
    $ss = simplexml_load_string($ssXml);
    foreach ($ss->si as $si) {
        if (isset($si->t)) {
            $sharedStrings[] = (string)$si->t;
        } else {
            $t = '';
            foreach ($si->r as $r) { $t .= (string)$r->t; }
            $sharedStrings[] = $t;
        }
    }
}

function getCellValue($cell, $sharedStrings) {
    $type = (string)$cell['t'];
    $v = (string)$cell->v;
    if ($type === 's') return isset($sharedStrings[intval($v)]) ? $sharedStrings[intval($v)] : $v;
    return $v;
}

for ($idx = 5; $idx <= 8; $idx++) { // Sheets 6, 7, 8, 9
    $s = $sheets[$idx];
    $sheetNum = $idx + 1;
    $sheetName = $s['name'];

    $sheetXml = $zip->getFromName('xl/' . $targetMap[$s['rId']]);
    $xml = simplexml_load_string($sheetXml);

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        $rowCells = [];
        foreach ($row->c as $c) {
            $colRef = (string)$c['r'];
            preg_match('/([A-Z]+)(\d+)/', $colRef, $matches);
            $colLetter = $matches[1] ?? $colRef;
            $rowCells[$colLetter] = getCellValue($c, $sharedStrings);
        }
        $rows[$rNum] = $rowCells;
    }

    $item_check_name = '';
    for ($r = 1; $r <= 6; $r++) {
        if (isset($rows[$r]['L']) && trim($rows[$r]['L']) !== '') {
            $item_check_name = trim($rows[$r]['L']);
            break;
        }
    }
    echo "Sheet $sheetNum: '$sheetName' | Item Check Name: '$item_check_name'\n";
}

$zip->close();

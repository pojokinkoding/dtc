<?php
$file = __DIR__ . '/../ctp_pucase_jan.xlsx';
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

$sample_sheet_indices = [5, 9, 21]; // 0-based for Sheet 6, Sheet 10, Sheet 22

foreach ($sample_sheet_indices as $idx) {
    $s = $sheets[$idx];
    $sheetNum = $idx + 1;
    $sheetName = $s['name'];
    echo "========================================================\n";
    echo "Sheet $sheetNum: '$sheetName'\n";

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

    for ($r = 10; $r <= 22; $r++) {
        if (isset($rows[$r])) {
            $nonEmpty = array_filter($rows[$r], function($v) { return $v !== '' && $v !== null; });
            if (!empty($nonEmpty)) {
                echo "Row $r: " . json_encode(array_slice($nonEmpty, 0, 12), JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
}

$zip->close();

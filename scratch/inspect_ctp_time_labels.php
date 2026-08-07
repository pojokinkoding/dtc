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

for ($idx = 5; $idx < 24; $idx++) { // Sheets 6 to 24
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

    // Find Date row
    $dateRow = null;
    for ($r = 10; $r <= 15; $r++) {
        if (isset($rows[$r]['B']) && strcasecmp(trim($rows[$r]['B']), 'Date') === 0) {
            $dateRow = $r;
            break;
        }
    }

    $sample_b_vals = [];
    if ($dateRow) {
        for ($sr = $dateRow + 1; $sr <= $dateRow + 10; $sr++) {
            if (isset($rows[$sr]['B'])) {
                $bVal = trim($rows[$sr]['B']);
                if ($bVal === 'Xbar' || $bVal === 'R' || stristr($bVal, 'StdDEV') !== false) break;
                if ($bVal !== '') {
                    $sample_b_vals[] = "Row $sr: $bVal";
                }
            }
        }
    }

    echo "Sheet $sheetNum: '$sheetName' | DateRow: " . ($dateRow ?: 'NONE') . " | Column B Labels: " . implode(', ', $sample_b_vals) . "\n";
}

$zip->close();

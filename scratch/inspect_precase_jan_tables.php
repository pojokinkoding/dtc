<?php
$file = __DIR__ . '/../ctp_precase_jan.xlsx';
$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Failed to open zip\n");

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

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$xml = simplexml_load_string($sheetXml);

$rows = [];
foreach ($xml->sheetData->row as $row) {
    $rNum = (int)$row['r'];
    $rowCells = [];
    foreach ($row->c as $c) {
        $colRef = (string)$c['r'];
        preg_match('/([A-Z]+)(\d+)/', $colRef, $matches);
        $colLetter = $matches[1] ?? $colRef;
        $val = getCellValue($c, $sharedStrings);
        if ($val !== '' && $val !== null) {
            $rowCells[$colLetter] = $val;
        }
    }
    if (!empty($rowCells)) {
        $rows[$rNum] = $rowCells;
    }
}

echo "Searching for table headers / models in ctp_precase_jan.xlsx:\n\n";

foreach ($rows as $rNum => $rowCells) {
    foreach ($rowCells as $col => $val) {
        $valStr = trim((string)$val);
        if (stristr($valStr, 'Nama Model') !== false || stristr($valStr, 'Nama CTQ') !== false || stristr($valStr, 'Nama Section') !== false || stristr($valStr, 'Jam') !== false) {
            echo "Row $rNum, Col $col: '$valStr'\n";
        }
    }
}

$zip->close();

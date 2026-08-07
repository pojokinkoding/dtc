<?php
$file = __DIR__ . '/../202601 Foolproof Hinge Lower REF01.xlsx';

$zip = new ZipArchive();
$zip->open($file);

$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
$sharedStrings = [];
if ($sharedStringsXml) {
    $ss = simplexml_load_string($sharedStringsXml);
    foreach ($ss->si as $val) {
        if (isset($val->t)) {
            $sharedStrings[] = (string)$val->t;
        } else if (isset($val->r)) {
            $txt = '';
            foreach ($val->r as $r) { $txt .= (string)$r->t; }
            $sharedStrings[] = $txt;
        } else {
            $sharedStrings[] = '';
        }
    }
}

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$sXml = simplexml_load_string($sheetXml);

foreach ($sXml->sheetData->row as $row) {
    $rNum = (int)$row['r'];
    $rowCells = [];
    foreach ($row->c as $c) {
        $cellRef = (string)$c['r'];
        $t = (string)$c['t'];
        $v = (string)$c->v;
        $val = ($t === 's' && isset($sharedStrings[(int)$v])) ? $sharedStrings[(int)$v] : $v;
        if (trim($val) !== '') {
            $rowCells[] = "$cellRef: $val";
        }
    }
    if (!empty($rowCells)) {
        echo "Row $rNum -> " . implode(' | ', $rowCells) . "\n";
    }
}
?>

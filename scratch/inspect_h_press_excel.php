<?php
$file = __DIR__ . '/../202601 Time_Check H Press out_door REF01.xlsx';
if (!file_exists($file)) die("File not found: $file\n");

$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Zip open failed\n");

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $name = (string)$sheet['name'];
    $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $sheets[] = ['name' => $name, 'rId' => $rId];
}

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

$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$rels = simplexml_load_string($relsXml);
$targetMap = [];
foreach ($rels->Relationship as $rel) {
    $targetMap[(string)$rel['Id']] = (string)$rel['Target'];
}

echo "Sheet Names (" . count($sheets) . "): " . json_encode(array_column($sheets, 'name')) . "\n\n";

// Inspect first 3 sheets in detail
$sampleSheets = array_slice($sheets, 0, 3);
foreach ($sampleSheets as $sh) {
    $sheetName = $sh['name'];
    $targetFile = 'xl/' . $targetMap[$sh['rId']];
    $sheetXml = $zip->getFromName($targetFile);
    if (!$sheetXml) continue;
    $sXml = simplexml_load_string($sheetXml);

    $grid = [];
    foreach ($sXml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        foreach ($row->c as $c) {
            $cellRef = (string)$c['r'];
            preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $m);
            if (!$m) continue;
            $colLetter = $m[1];
            $t = (string)$c['t'];
            $v = (string)$c->v;
            $val = ($t === 's' && isset($sharedStrings[(int)$v])) ? $sharedStrings[(int)$v] : $v;
            $grid[$rNum][$colLetter] = trim($val);
        }
    }

    echo "====================================================\n";
    echo "--- SHEET: '$sheetName' ---\n";
    echo "Top 20 rows:\n";
    for ($r = 1; $r <= 20; $r++) {
        if (!isset($grid[$r])) continue;
        $rowVal = [];
        foreach ($grid[$r] as $col => $val) {
            if ($val !== '') $rowVal[] = "$col$r: '$val'";
        }
        if (!empty($rowVal)) {
            echo "Row " . sprintf('%02d', $r) . " -> " . implode(' | ', array_slice($rowVal, 0, 15)) . "\n";
        }
    }

    echo "\nRows 21 to 50:\n";
    for ($r = 21; $r <= 50; $r++) {
        if (!isset($grid[$r])) continue;
        $rowVal = [];
        foreach ($grid[$r] as $col => $val) {
            if ($val !== '') $rowVal[] = "$col$r: '$val'";
        }
        if (!empty($rowVal)) {
            echo "Row " . sprintf('%02d', $r) . " -> " . implode(' | ', array_slice($rowVal, 0, 15)) . "\n";
        }
    }
    echo "\n";
}
?>

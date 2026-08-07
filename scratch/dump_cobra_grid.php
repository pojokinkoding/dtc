<?php
$file = __DIR__ . '/../202601 Check Sheet Cycle COBRA REF01.xlsx';
$zip = new ZipArchive();
$zip->open($file);

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $sheets[] = [
        'name' => (string)$sheet['name'],
        'rId' => (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id']
    ];
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

foreach ($sheets as $sh) {
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

    echo "========================================\n";
    echo "SHEET: '$sheetName'\n";
    echo "Title Rows (1 to 9):\n";
    for ($r=1; $r<=9; $r++) {
        if (!isset($grid[$r])) continue;
        $rowStr = [];
        foreach ($grid[$r] as $col => $val) {
            if ($val !== '') $rowStr[] = "$col$r: '$val'";
        }
        if (!empty($rowStr)) echo "R$r -> " . implode(' | ', $rowStr) . "\n";
    }

    echo "\nRows 10 to 35:\n";
    for ($r=10; $r<=35; $r++) {
        if (!isset($grid[$r])) continue;
        $rowStr = [];
        foreach (['A','B','C','D','E','F','G','H','I','J'] as $col) {
            $val = $grid[$r][$col] ?? '';
            if ($val !== '') $rowStr[] = "$col$r: '$val'";
        }
        if (!empty($rowStr)) echo "R" . sprintf('%02d', $r) . " -> " . implode(' | ', $rowStr) . "\n";
    }
    echo "\n";
}
?>

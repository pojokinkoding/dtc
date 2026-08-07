<?php
$file = __DIR__ . '/../202601 Check Sheet Cycle COBRA REF01.xlsx';
if (!file_exists($file)) {
    die("File not found: $file\n");
}

$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) {
    die("Failed to open excel zip file.\n");
}

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

echo "Found " . count($sheets) . " sheets:\n";

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

    echo "====================================================\n";
    echo "SHEET: '$sheetName'\n";
    // Look for header info in rows 1 to 8
    for ($r = 1; $r <= 8; $r++) {
        if (!isset($grid[$r])) continue;
        $info = [];
        foreach ($grid[$r] as $col => $val) {
            if ($val !== '') $info[] = "$col$r: '$val'";
        }
        if (!empty($info)) echo "Header R$r -> " . implode(' | ', $info) . "\n";
    }

    // Find date row (row 9 usually) and count date columns
    $dateRow = 9;
    $days = [];
    if (isset($grid[$dateRow])) {
        foreach ($grid[$dateRow] as $col => $val) {
            if (is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
                $days[$col] = (int)$val;
            }
        }
    }
    echo "Date columns at row $dateRow: " . count($days) . " days\n";

    // Scan rows 10 to 60 for Checkpoints / Items
    echo "Checkpoints / Items:\n";
    for ($r = 10; $r <= 60; $r++) {
        if (!isset($grid[$r])) continue;
        $no = $grid[$r]['B'] ?? '';
        $cp = $grid[$r]['C'] ?? '';
        $spec = $grid[$r]['E'] ?? ($grid[$r]['D'] ?? '');
        $time = $grid[$r]['F'] ?? '';
        
        if ($cp !== '' || $no !== '') {
            echo "Row " . sprintf('%02d', $r) . " -> No: '$no' | CP: '$cp' | Spec: '$spec' | Time: '$time'\n";
        }
    }
    echo "\n";
}
?>

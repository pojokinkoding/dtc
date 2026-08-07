<?php
$file = __DIR__ . '/../202601 Check Sheet Cycle COBRA REF01.xlsx';
if (!file_exists($file)) die("File not found\n");

$zip = new ZipArchive();
$zip->open($file);

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
    echo "DETAILED ANALYSIS OF SHEET: '$sheetName'\n";
    echo "B6: '" . ($grid[6]['B'] ?? '') . "'\n";
    echo "Month cell: '" . ($grid[6]['O'] ?? ($grid[6]['M'] ?? '')) . "'\n";
    echo "Year cell: '" . ($grid[6]['AC'] ?? ($grid[6]['Z'] ?? '')) . "'\n";

    // Row 8/9 headers
    $dateRow = 9;
    $days = [];
    if (isset($grid[$dateRow])) {
        foreach ($grid[$dateRow] as $col => $val) {
            if (is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
                $days[$col] = (int)$val;
            }
        }
    }
    echo "Days found (" . count($days) . "): " . implode(', ', array_map(fn($c, $d) => "$c=$d", array_keys($days), array_values($days))) . "\n\n";

    // Scan all rows from 10 to 35
    $currentNo = '';
    $currentCP = '';
    $currentSpec = '';
    
    for ($r = 10; $r <= 33; $r++) {
        if (!isset($grid[$r])) continue;
        $no = $grid[$r]['B'] ?? '';
        $cp = $grid[$r]['C'] ?? '';
        $spec = $grid[$r]['E'] ?? ($grid[$r]['D'] ?? '');
        $time = $grid[$r]['F'] ?? '';
        
        if ($no !== '' && is_numeric($no)) {
            $currentNo = $no;
            $currentCP = $cp;
            $currentSpec = $spec;
        }
        
        // Count non-empty values in day columns
        $filledCount = 0;
        $sampleVals = [];
        foreach ($days as $col => $dayNum) {
            $v = $grid[$r][$col] ?? '';
            if ($v !== '') {
                $filledCount++;
                if (count($sampleVals) < 5) $sampleVals[] = "D$dayNum($col):$v";
            }
        }
        
        echo "Row " . sprintf('%02d', $r) . " [No $currentNo - $currentCP] | Time/Label: '$time' | Filled: $filledCount | Samples: " . implode(', ', $sampleVals) . "\n";
    }
    echo "\n";
}
?>

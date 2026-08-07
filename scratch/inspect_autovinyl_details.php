<?php
$file = __DIR__ . '/../202601 Check Sheet AutoVinyl Cutting REF02.xlsx';
if (!file_exists($file)) die("File not found: $file\n");

$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Failed to open excel file\n");

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

function formatTimeLabel($val) {
    if (is_numeric($val)) {
        $floatVal = (float)$val;
        if ($floatVal < 1.0 || $floatVal >= 1.0) {
            $totalMins = round($floatVal * 1440);
            $h = floor($totalMins / 60) % 24;
            $m = $totalMins % 60;
            return sprintf('%02d:%02d', $h, $m);
        }
    }
    return trim($val);
}

foreach ($sheets as $sh) {
    $sheetName = trim($sh['name']);
    echo "========================================================\n";
    echo "=== INSPECTING SHEET: '$sheetName' ===\n";
    echo "========================================================\n";

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

    $secText = $grid[9]['B'] ?? '';
    echo "Section Header (Row 9 B): '$secText'\n";

    // Header dates in row 12 & 13
    $dateCols = [];
    foreach (range('K', 'Z') as $c) {
        $d = $grid[12][$c] ?? ($grid[13][$c] ?? '');
        if ($d !== '') $dateCols[$c] = (int)$d;
    }
    // Check AA to AI
    $colList = ['K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO'];
    foreach ($colList as $c) {
        $d = $grid[12][$c] ?? ($grid[13][$c] ?? '');
        if (is_numeric($d)) $dateCols[$c] = (int)$d;
    }
    echo "Dates mapped: " . count($dateCols) . " columns (Days " . min($dateCols) . " to " . max($dateCols) . ")\n\n";

    // Find Check Points and Time Check slots
    $checkpoints = [];
    $currentCp = null;
    $currentModel = '';
    $currentSpec = '';

    for ($r = 14; $r <= 100; $r++) {
        if (!isset($grid[$r])) continue;

        $cpName = $grid[$r]['D'] ?? '';
        $model = $grid[$r]['C'] ?? '';
        $specOK = $grid[$r]['E'] ?? '';
        $specNG = $grid[$r]['F'] ?? '';
        $itemType = $grid[$r]['J'] ?? '';
        $timeFrac = $grid[$r]['I'] ?? '';

        if ($cpName !== '') {
            $currentCp = $cpName;
            $currentModel = $model !== '' ? $model : 'All Model';
            $specText = "OK: $specOK" . ($specNG ? " / NG: $specNG" : "");
            echo "  [CP Row $r] Check Point: '$cpName' | Model: '$currentModel' | Spec: '$specText'\n";
        }

        if ($itemType === 'Result' && $timeFrac !== '') {
            $timeLabel = formatTimeLabel($timeFrac);
            // Count non-empty values across date columns
            $filledCount = 0;
            $sampleVals = [];
            foreach ($dateCols as $col => $dayNum) {
                $v = $grid[$r][$col] ?? '';
                if ($v !== '') {
                    $filledCount++;
                    if (count($sampleVals) < 5) $sampleVals[] = "Day$dayNum:$v";
                }
            }
            echo "    -> Time Slot: '$timeLabel' (Row $r) | Filled: $filledCount | Samples: " . implode(', ', $sampleVals) . "\n";
        }
    }
    echo "\n";
}
?>

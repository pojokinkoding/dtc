<?php
require_once __DIR__ . '/../config/config.php';

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

function formatTimeLabel($val) {
    if (is_numeric($val) && (float)$val < 1.0) {
        $totalMins = round((float)$val * 1440);
        $h = floor($totalMins / 60) % 24;
        $m = $totalMins % 60;
        return sprintf('%02d:%02d', $h, $m);
    }
    return trim($val);
}

// 1. Build Specs Map from Top Table of Sheet 1
$sh1 = $sheets[0];
$sheetXml1 = $zip->getFromName('xl/' . $targetMap[$sh1['rId']]);
$sXml1 = simplexml_load_string($sheetXml1);
$grid1 = [];
foreach ($sXml1->sheetData->row as $row) {
    $rNum = (int)$row['r'];
    foreach ($row->c as $c) {
        $cellRef = (string)$c['r'];
        preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $m);
        if (!$m) continue;
        $colLetter = $m[1];
        $t = (string)$c['t'];
        $v = (string)$c->v;
        $val = ($t === 's' && isset($sharedStrings[(int)$v])) ? $sharedStrings[(int)$v] : $v;
        $grid1[$rNum][$colLetter] = trim($val);
    }
}

$specsByModel = [];
for ($r = 5; $r <= 15; $r++) {
    if (!isset($grid1[$r])) continue;
    $modelCode = $grid1[$r]['C'] ?? '';
    if ($modelCode === '') continue;

    $specsByModel[strtoupper(preg_replace('/[\s\-]/', '', $modelCode))] = [
        'A' => $grid1[$r]['D'] ?? '',
        'B / R' => $grid1[$r]['E'] ?? '',
        'B / F' => $grid1[$r]['F'] ?? '',
        'C' => $grid1[$r]['G'] ?? '',
        'D' => $grid1[$r]['H'] ?? ''
    ];
}

$target_month = '2026-01';
$checkpointCols = [
    'D' => 'A',
    'E' => 'B / R',
    'F' => 'B / F',
    'G' => 'C',
    'H' => 'D',
    'I' => 'Bending'
];

// Map: model => dateStr => [ {label => ..., checkpoint => ..., val => ...}, ... ]
$modelData = [];

foreach ($sheets as $sh) {
    $sheetName = trim($sh['name']);
    if (!is_numeric($sheetName)) continue;

    $dayNum = (int)$sheetName;
    $dateStr = sprintf('%s-%02d', $target_month, $dayNum);

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

    for ($r = 21; $r <= 33; $r++) {
        if (!isset($grid[$r])) continue;
        $model = $grid[$r]['C'] ?? '';
        $timeFrac = $grid[$r]['B'] ?? '';

        if ($model === '' || $timeFrac === '') continue;

        $timeLabel = formatTimeLabel($timeFrac);

        if (!isset($modelData[$model])) {
            $modelData[$model] = [];
        }
        if (!isset($modelData[$model][$dateStr])) {
            $modelData[$model][$dateStr] = [];
        }

        foreach ($checkpointCols as $col => $cpName) {
            $rawVal = $grid[$r][$col] ?? '';
            if ($rawVal === '') continue;

            $finalVal = $rawVal;
            if ($cpName === 'B / F') {
                $finalVal = preg_match('/1\s*door/i', $rawVal) ? 'OK' : (is_numeric($rawVal) ? $rawVal : 'NG');
            }

            $modelData[$model][$dateStr][] = [
                'time' => $timeLabel,
                'cpName' => $cpName,
                'rawVal' => $rawVal,
                'val' => $finalVal
            ];
        }
    }
}

echo "=== SUMMARY OF MULTI-CHECKPOINT EXTRACTION BY MODEL ===\n";
echo "Total distinct models: " . count($modelData) . "\n\n";

foreach ($modelData as $model => $dates) {
    $totalMeas = 0;
    $cpCounts = [];
    foreach ($dates as $d => $items) {
        foreach ($items as $item) {
            $totalMeas++;
            $cp = $item['cpName'];
            $cpCounts[$cp] = ($cpCounts[$cp] ?? 0) + 1;
        }
    }
    echo "Model '$model': " . count($dates) . " days active | Total measurements: $totalMeas | Checkpoints: " . json_encode($cpCounts) . "\n";
}
?>

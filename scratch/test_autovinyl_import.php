<?php
require_once __DIR__ . '/../config/config.php';

$file = __DIR__ . '/../202601 Check Sheet AutoVinyl Cutting REF02.xlsx';
if (!file_exists($file)) die("File not found: $file\n");

$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Zip open failed\n");

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $name = (string)$sheet['name'];
    $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $sheets[] = ['name' => trim($name), 'rId' => $rId];
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
        $totalMins = round($floatVal * 1440);
        $h = floor($totalMins / 60) % 24;
        $m = $totalMins % 60;
        return sprintf('%02d:%02d', $h, $m);
    }
    return trim($val);
}

$target_month = '2026-01';
$parsedDataByModel = [];

foreach ($sheets as $sh) {
    $mcName = trim($sh['name']); // e.g. "MC 1", "MC 2", ...
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

    // Map Date Columns
    $dateCols = [];
    foreach ($grid[12] as $col => $val) {
        if (is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
            $dateCols[$col] = (int)$val;
        }
    }
    foreach ($grid[13] as $col => $val) {
        if (!isset($dateCols[$col]) && is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
            $dateCols[$col] = (int)$val;
        }
    }

    $modelDailyData = []; // dateStr => [ {label, cpName, specVal, val}, ... ]
    $checkpointsSpecMap = []; // cpName => specVal

    for ($r = 14; $r <= 60; $r++) {
        if (!isset($grid[$r])) continue;

        $cpName = $grid[$r]['D'] ?? '';
        $specOK = $grid[$r]['E'] ?? '';
        $specNG = $grid[$r]['F'] ?? '';
        $itemType = $grid[$r]['J'] ?? '';
        $timeFrac = $grid[$r]['I'] ?? '';

        if ($cpName !== '') {
            $currentCp = $cpName;
            $specValStr = "OK: $specOK" . ($specNG ? " / NG: $specNG" : "");
            $checkpointsSpecMap[$cpName] = $specValStr;
        }

        if ($itemType === 'Result' && $timeFrac !== '' && isset($currentCp)) {
            $timeLabel = formatTimeLabel($timeFrac);

            foreach ($dateCols as $col => $dayNum) {
                $rawVal = $grid[$r][$col] ?? '';
                if ($rawVal === '') continue;

                $dateStr = sprintf('%s-%02d', $target_month, $dayNum);
                $finalVal = (strcasecmp($rawVal, 'ok') === 0 || strcasecmp($rawVal, 'center') === 0) ? 'OK' : (strcasecmp($rawVal, 'ng') === 0 ? 'NG' : strtoupper($rawVal));

                if (!isset($modelDailyData[$dateStr])) {
                    $modelDailyData[$dateStr] = [];
                }

                $modelDailyData[$dateStr][] = [
                    'label' => $timeLabel,
                    'cpName' => $currentCp,
                    'val' => $finalVal
                ];
            }
        }
    }

    $parsedDataByModel[$mcName] = [
        'checkpoints' => $checkpointsSpecMap,
        'dailyData' => $modelDailyData
    ];
}

echo "=== SUMMARY OF PARSED AUTOVINYL CUTTING BY MACHINE / MODEL ===\n";
foreach ($parsedDataByModel as $mc => $info) {
    $cpList = array_keys($info['checkpoints']);
    $activeDays = count($info['dailyData']);
    $totalMeas = 0;
    foreach ($info['dailyData'] as $d => $mList) {
        $totalMeas += count($mList);
    }
    echo "Machine '$mc': " . count($cpList) . " Checkpoints (" . implode(', ', $cpList) . ") | Active Days: $activeDays | Total Measurements: $totalMeas\n";
}
?>

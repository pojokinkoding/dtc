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

$target_month = '2026-01';

// Map: model => dateStr => array of measurements [ {label => ..., value => ...}, ... ]
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
        $valF = $grid[$r]['F'] ?? '';

        if ($model === '' || $timeFrac === '') continue;

        $timeLabel = formatTimeLabel($timeFrac);
        $evalVal = preg_match('/1\s*door/i', $valF) ? 'OK' : 'NG';

        if (!isset($modelData[$model])) {
            $modelData[$model] = [];
        }
        if (!isset($modelData[$model][$dateStr])) {
            $modelData[$model][$dateStr] = [];
        }

        $modelData[$model][$dateStr][] = [
            'time' => $timeLabel,
            'colF' => $valF,
            'eval' => $evalVal
        ];
    }
}

echo "=== SUMMARY OF EXTRACTED DATA BY MODEL ===\n";
echo "Total distinct models: " . count($modelData) . "\n\n";

foreach ($modelData as $model => $dates) {
    $totalMeas = 0;
    $okCount = 0;
    $ngCount = 0;
    foreach ($dates as $d => $items) {
        foreach ($items as $item) {
            $totalMeas++;
            if ($item['eval'] === 'OK') $okCount++; else $ngCount++;
        }
    }
    echo "Model '$model': " . count($dates) . " days active | Total measurements: $totalMeas (OK: $okCount, NG: $ngCount)\n";
}
?>

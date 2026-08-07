<?php
require_once __DIR__ . '/../config/config.php';

$files = array_filter(glob(__DIR__ . '/../*202601*Check Sheet Out Door H Press REF02.xlsx'), function($f) {
    return strpos(basename($f), '~$') !== 0;
});
if (empty($files)) die("File not found via glob\n");
$file = array_values($files)[0];

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
    $str = trim($val);
    // Parse time strings like "13:40:00 PM" or "7:40:00 AM" or "07.40"
    if (preg_match('/^(\d{1,2})[:\.](\d{2})(?:[:\.]\d{2})?\s*(AM|PM)?$/i', $str, $m)) {
        $h = (int)$m[1];
        $min = (int)$m[2];
        $ampm = strtoupper($m[3] ?? '');
        if ($ampm === 'PM' && $h < 12) $h += 12;
        if ($ampm === 'AM' && $h === 12) $h = 0;
        return sprintf('%02d:%02d', $h, $min);
    }
    return $str;
}

$target_month = '2026-01';

// Top table specs from first sheet
$sh1 = $sheets[0];
$targetFile = 'xl/' . $targetMap[$sh1['rId']];
$sheetXml = $zip->getFromName($targetFile);
$sXml = simplexml_load_string($sheetXml);

$grid1 = [];
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
        $grid1[$rNum][$colLetter] = trim($val);
    }
}

$specsMap = []; // modelName => [ 'A' => spec, 'B / R' => spec, 'B / F' => spec, 'C' => spec, 'D' => spec, 'Bending' => 'OK' ]
for ($r = 5; $r <= 20; $r++) {
    if (!isset($grid1[$r])) continue;
    $model = $grid1[$r]['C'] ?? '';
    if ($model === '') continue;

    $specsMap[$model] = [
        'A' => $grid1[$r]['D'] ?? '',
        'B / R' => $grid1[$r]['E'] ?? '',
        'B / F' => $grid1[$r]['F'] ?? '',
        'C' => $grid1[$r]['G'] ?? '',
        'D' => $grid1[$r]['H'] ?? '',
        'Bending' => 'OK'
    ];
}

echo "=== SPECS PARSED FROM TOP TABLE (" . count($specsMap) . " models) ===\n";
foreach ($specsMap as $m => $sp) {
    echo "  Model '$m': " . json_encode($sp) . "\n";
}

// Parse daily data from all sheets
$checkpointCols = [
    'D' => 'A',
    'E' => 'B / R',
    'F' => 'B / F',
    'G' => 'C',
    'H' => 'D',
    'I' => 'Bending'
];

$modelDailyData = []; // modelName => dateStr => [ {label, cpName, rawVal, val}, ... ]

foreach ($sheets as $sh) {
    $shName = trim($sh['name']);

    // Extract day number from sheet name (e.g. "02" -> 2, "MASTER1 (3)" -> date in G23)
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

    $dayNum = null;
    if (is_numeric($shName)) {
        $dayNum = (int)$shName;
    } else {
        // Look in Row 23 Col G for "Tanggal : 02.01.2026"
        $tglStr = $grid[23]['G'] ?? '';
        if (preg_match('/(\d{1,2})[\.\/]\d{1,2}[\.\/]\d{4}/', $tglStr, $m)) {
            $dayNum = (int)$m[1];
        }
    }

    if ($dayNum === null || $dayNum < 1 || $dayNum > 31) continue;

    $dateStr = sprintf('%s-%02d', $target_month, $dayNum);

    for ($r = 26; $r <= 100; $r++) {
        if (!isset($grid[$r])) continue;
        $model = $grid[$r]['C'] ?? '';
        $timeFrac = $grid[$r]['B'] ?? '';

        if ($model === '' || $timeFrac === '') continue;

        $timeLabel = formatTimeLabel($timeFrac);

        if (!isset($modelDailyData[$model])) {
            $modelDailyData[$model] = [];
        }
        if (!isset($modelDailyData[$model][$dateStr])) {
            $modelDailyData[$model][$dateStr] = [];
        }

        foreach ($checkpointCols as $col => $cpName) {
            $rawVal = $grid[$r][$col] ?? '';
            if ($rawVal === '') continue;

            $finalVal = $rawVal;
            if ($cpName === 'B / F') {
                $finalVal = preg_match('/1\s*door/i', $rawVal) ? 'OK' : (is_numeric($rawVal) ? $rawVal : 'NG');
            } else if ($cpName === 'Bending') {
                $finalVal = (strcasecmp($rawVal, 'ok') === 0 || strcasecmp($rawVal, '1 door') === 0) ? 'OK' : 'NG';
            }

            $modelDailyData[$model][$dateStr][] = [
                'label' => $timeLabel,
                'cpName' => $cpName,
                'rawVal' => $rawVal,
                'val' => $finalVal
            ];
        }
    }
}

echo "\n=== SUMMARY OF PARSED DAILY DATA BY MODEL ===\n";
foreach ($modelDailyData as $model => $dates) {
    $activeDays = count($dates);
    $totalMeas = 0;
    $sampleTimes = [];
    foreach ($dates as $d => $mList) {
        $totalMeas += count($mList);
        foreach ($mList as $m) {
            if (!in_array($m['label'], $sampleTimes)) $sampleTimes[] = $m['label'];
        }
    }
    echo "Model '$model': $activeDays active days | Total Measurements: $totalMeas | Sample Times: " . implode(', ', array_slice($sampleTimes, 0, 8)) . "\n";
}
?>

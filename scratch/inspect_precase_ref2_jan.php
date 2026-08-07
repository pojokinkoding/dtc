<?php
$file = __DIR__ . '/../ctp_precase_jan.xlsx';
if (!file_exists($file)) die("File not found\n");

$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Failed to open zip\n");

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);
$wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $sheets[] = [
        'name' => (string)$sheet['name'],
        'rId' => (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id']
    ];
}

$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$rels = simplexml_load_string($relsXml);
$targetMap = [];
foreach ($rels->Relationship as $rel) {
    $targetMap[(string)$rel['Id']] = (string)$rel['Target'];
}

$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
    $ss = simplexml_load_string($ssXml);
    foreach ($ss->si as $si) {
        if (isset($si->t)) {
            $sharedStrings[] = (string)$si->t;
        } else {
            $t = '';
            foreach ($si->r as $r) { $t .= (string)$r->t; }
            $sharedStrings[] = $t;
        }
    }
}

function getCellValue($cell, $sharedStrings) {
    $type = (string)$cell['t'];
    $v = (string)$cell->v;
    if ($type === 's') return isset($sharedStrings[intval($v)]) ? $sharedStrings[intval($v)] : $v;
    return $v;
}

echo "Total sheets: " . count($sheets) . "\n\n";
for ($i = 0; $i < count($sheets); $i++) {
    echo "Sheet " . ($i+1) . ": '" . $sheets[$i]['name'] . "'\n";
}

echo "\n--- Inspecting all sheets ---\n\n";

for ($idx = 0; $idx < count($sheets); $idx++) {
    $s = $sheets[$idx];
    $sheetNum = $idx + 1;
    $sheetName = $s['name'];

    $sheetXml = $zip->getFromName('xl/' . $targetMap[$s['rId']]);
    if (!$sheetXml) continue;
    $xml = simplexml_load_string($sheetXml);

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        if ($rNum > 20) break;
        $rowCells = [];
        foreach ($row->c as $c) {
            $colRef = (string)$c['r'];
            preg_match('/([A-Z]+)(\d+)/', $colRef, $matches);
            $colLetter = $matches[1] ?? $colRef;
            $val = getCellValue($c, $sharedStrings);
            if ($val !== '' && $val !== null) $rowCells[$colLetter] = $val;
        }
        if (!empty($rowCells)) $rows[$rNum] = $rowCells;
    }

    // Find item/spec info
    $item_r3 = $rows[3]['L'] ?? '';
    $item_r4 = $rows[4]['L'] ?? '';
    $lsl = null; $usl = null;
    for ($r = 5; $r <= 12; $r++) {
        if (!isset($rows[$r])) continue;
        $k = strtoupper(trim($rows[$r]['K'] ?? ''));
        if ($k === 'USL') $usl = $rows[$r]['M'] ?? null;
        if ($k === 'LSL') $lsl = $rows[$r]['M'] ?? null;
    }

    // Find date row
    $dateRow = null;
    for ($r = 10; $r <= 20; $r++) {
        if (!isset($rows[$r])) continue;
        foreach ($rows[$r] as $col => $val) {
            if ($col === 'A' || $col === 'B') continue;
            if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) { $dateRow = $r; break 2; }
        }
    }

    // Count samples
    $sampleCount = 0;
    if ($dateRow) {
        for ($sr = $dateRow + 1; $sr <= $dateRow + 10; $sr++) {
            $lbl = $rows[$sr]['B'] ?? '';
            if (stristr($lbl, 'Xbar') !== false || stristr($lbl, 'Std') !== false) break;
            foreach ($rows[$dateRow] as $col => $val) {
                if ($col === 'A' || $col === 'B') continue;
                if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                    $rv = $rows[$sr][$col] ?? '';
                    if ($rv !== '' && is_numeric($rv)) $sampleCount++;
                }
            }
        }
    }

    echo "Sheet $sheetNum: '$sheetName' | Row3-L: '$item_r3' | Row4-L: '$item_r4' | LSL: " . ($lsl??'N/A') . " | USL: " . ($usl??'N/A') . " | DateRow: " . ($dateRow??'NONE') . " | Samples: $sampleCount\n";
}

$zip->close();

<?php
$file = __DIR__ . '/../ctp_pudoor_jan.xlsx';
if (!file_exists($file)) die("File ctp_pudoor_jan.xlsx not found\n");

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

echo "Total sheets in ctp_pudoor_jan.xlsx: " . count($sheets) . "\n\n";

$manualIndex = -1;
for ($i = 0; $i < count($sheets); $i++) {
    echo "Sheet " . ($i + 1) . ": '" . $sheets[$i]['name'] . "'\n";
    if (stristr($sheets[$i]['name'], 'manual') !== false) {
        $manualIndex = $i;
    }
}

echo "\nManual Sheet Index: " . ($manualIndex >= 0 ? ($manualIndex + 1) . " ('" . $sheets[$manualIndex]['name'] . "')" : "NOT FOUND") . "\n";
echo "Starting sheet index: " . ($manualIndex + 1) . " (Sheet " . ($manualIndex + 2) . ")\n\n";

// Inspect headers of sheets after manual
for ($idx = $manualIndex + 1; $idx < count($sheets); $idx++) {
    $s = $sheets[$idx];
    $sheetNum = $idx + 1;
    $sheetName = $s['name'];

    $sheetXml = $zip->getFromName('xl/' . $targetMap[$s['rId']]);
    if (!$sheetXml) continue;
    $xml = simplexml_load_string($sheetXml);

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        if ($rNum > 30) continue;
        $rowCells = [];
        foreach ($row->c as $c) {
            $colRef = (string)$c['r'];
            preg_match('/([A-Z]+)(\d+)/', $colRef, $matches);
            $colLetter = $matches[1] ?? $colRef;
            $val = getCellValue($c, $sharedStrings);
            if ($val !== '' && $val !== null) {
                $rowCells[$colLetter] = $val;
            }
        }
        if (!empty($rowCells)) $rows[$rNum] = $rowCells;
    }

    $line_excel = $rows[1]['O'] ?? ($rows[1]['B'] ?? 'N/A');
    $model_excel = $rows[5]['E'] ?? ($rows[5]['F'] ?? 'N/A');
    $item_excel = $rows[4]['E'] ?? ($rows[4]['F'] ?? 'N/A');
    $proc_excel = $rows[6]['E'] ?? ($rows[6]['F'] ?? 'N/A');
    $lsl = $rows[5]['L'] ?? 'N/A';
    $usl = $rows[6]['L'] ?? 'N/A';
    $uom = $rows[5]['M'] ?? 'N/A';

    // Find date row
    $dateRow = null;
    for ($r = 10; $r <= 25; $r++) {
        if (isset($rows[$r])) {
            foreach ($rows[$r] as $col => $val) {
                if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                    $dateRow = $r;
                    break 2;
                }
            }
        }
    }

    $sample_count = 0;
    $time_slots = [];
    if ($dateRow) {
        $sample_row_indices = [];
        for ($sr = $dateRow + 1; $sr <= $dateRow + 10; $sr++) {
            $lbl = isset($rows[$sr]['B']) ? trim((string)$rows[$sr]['B']) : '';
            if (strcasecmp($lbl, 'Xbar') === 0 || strcasecmp($lbl, 'R') === 0 || stristr($lbl, 'Std') !== false || stristr($lbl, 'Max') !== false || stristr($lbl, 'Min') !== false || stristr($lbl, 'Average') !== false) break;
            if ($lbl !== '') {
                $sample_row_indices[] = $sr;
                $time_slots[] = $lbl;
            }
        }

        foreach ($rows[$dateRow] as $col => $val) {
            if ($col === 'A' || $col === 'B' || $val === null || $val === '') continue;
            if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                foreach ($sample_row_indices as $sr) {
                    if (isset($rows[$sr][$col]) && is_numeric($rows[$sr][$col])) {
                        $sample_count++;
                    }
                }
            }
        }
    }

    echo "Sheet $sheetNum: '$sheetName' | LineExcel: '$line_excel' | Model: '$model_excel' | Item: '$item_excel' | Proc: '$proc_excel' | Spec: LSL=$lsl, USL=$usl $uom | DateRow: " . ($dateRow ?: 'NONE') . " | Times: " . count($time_slots) . " | Samples: $sample_count\n";
}

$zip->close();

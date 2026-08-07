<?php
$file = __DIR__ . '/../ctp_pudoor_jan.xlsx';
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

$manualIndex = -1;
for ($i = 0; $i < count($sheets); $i++) {
    if (stristr($sheets[$i]['name'], 'manual') !== false) {
        $manualIndex = $i;
    }
}

echo "Sheets starting after Manual (Index > $manualIndex):\n\n";

for ($idx = $manualIndex + 1; $idx < count($sheets); $idx++) {
    $sheetNum = $idx + 1;
    $sheetName = $sheets[$idx]['name'];

    $sheetXml = $zip->getFromName('xl/' . $targetMap[$sheets[$idx]['rId']]);
    if (!$sheetXml) continue;
    $xml = simplexml_load_string($sheetXml);

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        $rowCells = [];
        foreach ($row->c as $c) {
            $colRef = (string)$c['r'];
            preg_match('/([A-Z]+)(\d+)/', $colRef, $matches);
            $colLetter = $matches[1] ?? $colRef;
            $val = getCellValue($c, $sharedStrings);
            $rowCells[$colLetter] = $val;
        }
        $rows[$rNum] = $rowCells;
    }

    $item_check = $rows[4]['L'] ?? ($rows[4]['H'] ?? ($rows[4]['E'] ?? ''));
    $process_name = $rows[6]['E'] ?? ($rows[6]['F'] ?? ($rows[5]['E'] ?? ''));
    $model_row = $rows[8]['U'] ?? ($rows[5]['U'] ?? ($rows[5]['E'] ?? $sheetName));

    $usl = $rows[8]['M'] ?? ($rows[6]['L'] ?? null);
    $lsl = $rows[9]['M'] ?? ($rows[5]['L'] ?? null);
    $uom = '℃'; // Default temperature unit for PU door CTP after manual sheet

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

    $sample_row_indices = [];
    $time_slots = [];
    $sample_count = 0;

    if ($dateRow) {
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

    echo "Sheet $sheetNum '$sheetName': Item='$item_check' | Model='$model_row' | LSL=$lsl, USL=$usl $uom | DateRow: " . ($dateRow ?: 'NONE') . " | TimeSlots: " . count($time_slots) . " | Valid Samples: $sample_count\n";
}

$zip->close();

<?php
$file = __DIR__ . '/../ctp_acc_jan.xlsx';
if (!file_exists($file)) die("File ctp_acc_jan.xlsx not found\n");

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

function getCellValueAcc($cell, $sharedStrings) {
    $type = (string)$cell['t'];
    $v = (string)$cell->v;
    if ($type === 's') return isset($sharedStrings[intval($v)]) ? $sharedStrings[intval($v)] : $v;
    return $v;
}

echo "Total sheets in ctp_acc_jan.xlsx: " . count($sheets) . "\n\n";

for ($idx = 0; $idx < count($sheets); $idx++) {
    $s = $sheets[$idx];
    $sheetNum = $idx + 1;
    $sheetName = $s['name'];

    $sheetXml = $zip->getFromName('xl/' . $targetMap[$s['rId']]);
    if (!$sheetXml) {
        echo "Sheet $sheetNum '$sheetName': NO XML\n";
        continue;
    }
    $xml = simplexml_load_string($sheetXml);

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        $rowCells = [];
        foreach ($row->c as $c) {
            $colRef = (string)$c['r'];
            preg_match('/([A-Z]+)(\d+)/', $colRef, $matches);
            $colLetter = $matches[1] ?? $colRef;
            $rowCells[$colLetter] = getCellValueAcc($c, $sharedStrings);
        }
        $rows[$rNum] = $rowCells;
    }

    // Inspect Item Check / Process Name from header
    $check_point = '';
    for ($r = 1; $r <= 10; $r++) {
        if (isset($rows[$r]['L']) && trim($rows[$r]['L']) !== '') {
            $check_point = trim($rows[$r]['L']);
            break;
        }
        if (isset($rows[$r]['E']) && trim($rows[$r]['E']) !== '') {
            $check_point = trim($rows[$r]['E']);
        }
    }

    $model_excel = '';
    for ($r = 5; $r <= 10; $r++) {
        if (isset($rows[$r]['U']) && trim($rows[$r]['U']) !== '') {
            $model_excel = trim($rows[$r]['U']);
            break;
        }
    }

    // Find Spec LSL / USL
    $lsl = null;
    $usl = null;
    for ($r = 5; $r <= 10; $r++) {
        if (isset($rows[$r]['K'])) {
            $kVal = strtoupper(trim($rows[$r]['K']));
            if ($kVal === 'USL') {
                $usl = $rows[$r]['M'] ?? ($rows[$r]['N'] ?? null);
            } else if ($kVal === 'LSL') {
                $lsl = $rows[$r]['M'] ?? ($rows[$r]['N'] ?? null);
            }
        }
    }

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

    // Check sample rows
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

    echo "Sheet $sheetNum: '$sheetName' | Item: '$check_point' | Model Excel: '$model_excel' | LSL: '$lsl', USL: '$usl' | DateRow: " . ($dateRow ?: 'NONE') . " | Time Slots: " . count($time_slots) . " | Samples: $sample_count\n";
}

$zip->close();

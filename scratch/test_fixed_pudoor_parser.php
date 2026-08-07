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

function formatTimeLabel($bVal, $seq) {
    if (is_numeric($bVal) && floatval($bVal) > 0 && floatval($bVal) < 1) {
        $totalMinutes = round(floatval($bVal) * 24 * 60);
        $hours = floor($totalMinutes / 60);
        $mins = $totalMinutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }
    return $bVal !== '' ? $bVal : "Sample $seq";
}

echo "Testing fixed CTP PU Door parser:\n\n";

for ($idx = 4; $idx < count($sheets); $idx++) {
    $sheetNum = $idx + 1;
    $sheetName = $sheets[$idx]['name'];
    $sheetPath = 'xl/' . $targetMap[$sheets[$idx]['rId']];
    $sheetXml = $zip->getFromName($sheetPath);
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
            $rowCells[$colLetter] = getCellValue($c, $sharedStrings);
        }
        $rows[$rNum] = $rowCells;
    }

    $dateRow = null;
    for ($r = 10; $r <= 15; $r++) {
        if (isset($rows[$r])) {
            foreach ($rows[$r] as $col => $val) {
                if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                    $dateRow = $r;
                    break 2;
                }
            }
        }
    }

    if (!$dateRow) continue;

    $sample_row_indices = [];
    $time_labels = [];
    for ($sr = $dateRow + 1; $sr <= $dateRow + 10; $sr++) {
        $lbl = isset($rows[$sr]['B']) ? trim((string)$rows[$sr]['B']) : '';
        if (strcasecmp($lbl, 'Xbar') === 0 || strcasecmp($lbl, 'R') === 0 || stristr($lbl, 'StdDEV') !== false) {
            break;
        }
        if ($lbl !== '') {
            $sample_row_indices[] = $sr;
            $time_labels[] = formatTimeLabel($lbl, count($sample_row_indices));
        }
    }

    $valid_columns = [];
    $total_measurements = 0;
    foreach ($rows[$dateRow] as $col => $val) {
        if ($col === 'A' || $col === 'B' || $val === null || $val === '') continue;
        $val_str = trim((string)$val);
        if (!is_numeric($val_str)) continue;
        $day = intval($val_str);
        if ($day < 1 || $day > 31) continue;

        $numeric_count = 0;
        foreach ($sample_row_indices as $sr) {
            $raw_val = isset($rows[$sr][$col]) ? trim((string)$rows[$sr][$col]) : '';
            if ($raw_val !== '' && is_numeric($raw_val)) {
                $numeric_count++;
                $total_measurements++;
            }
        }

        if ($numeric_count > 0) {
            $valid_columns[$col] = $day;
        }
    }

    echo "Sheet $sheetNum: '$sheetName' | DateRow: $dateRow | Time slots (" . count($time_labels) . "): [" . implode(', ', $time_labels) . "] | Days: " . count($valid_columns) . " | Total Measurements: $total_measurements\n";
}

$zip->close();

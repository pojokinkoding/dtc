<?php
$file = __DIR__ . '/../ctp_pucase_jan.xlsx';
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

echo "Testing CTP parser starting from sheet 5 (index 4):\n\n";

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

    // Extract item_check_name / process_name
    $item_check_name = '';
    for ($r = 1; $r <= 6; $r++) {
        if (isset($rows[$r]['L']) && trim($rows[$r]['L']) !== '') {
            $item_check_name = trim($rows[$r]['L']);
            break;
        }
    }
    if (!$item_check_name) $item_check_name = $sheetName;
    $process_name = $item_check_name;

    // LSL / USL
    $lsl = 0; $usl = 0; $uom = '℃';
    for ($r = 5; $r <= 10; $r++) {
        if (isset($rows[$r]['K'])) {
            $kVal = strtoupper(trim($rows[$r]['K']));
            if ($kVal === 'USL') {
                $rawUsl = $rows[$r]['M'] ?? ($rows[$r]['N'] ?? '0');
                if (is_numeric($rawUsl)) $usl = floatval($rawUsl);
            } else if ($kVal === 'LSL') {
                $rawLsl = $rows[$r]['M'] ?? ($rows[$r]['N'] ?? '0');
                if (is_numeric($rawLsl)) $lsl = floatval($rawLsl);
            }
        }
        if (isset($rows[$r]['H']) && strpos($rows[$r]['H'], 'sec') !== false) {
            $uom = 'sec';
        }
    }

    // Find date row
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

    if (!$dateRow) {
        echo "Sheet $sheetNum: '$sheetName' -> SKIPPED (No date row)\n";
        continue;
    }

    // Find sample rows
    $sample_row_indices = [];
    for ($sr = $dateRow + 1; $sr <= $dateRow + 10; $sr++) {
        $lbl = isset($rows[$sr]['B']) ? trim((string)$rows[$sr]['B']) : '';
        if (strcasecmp($lbl, 'Xbar') === 0 || strcasecmp($lbl, 'R') === 0 || stristr($lbl, 'StdDEV') !== false) {
            break;
        }
        $sample_row_indices[] = $sr;
    }

    // Process day columns
    $valid_columns = [];
    foreach ($rows[$dateRow] as $col => $val) {
        if ($col === 'A' || $col === 'B' || $val === null || $val === '') continue;
        $val_str = trim((string)$val);
        if (!is_numeric($val_str)) continue;
        $day = intval($val_str);
        if ($day < 1 || $day > 31) continue;

        $num_cnt = 0;
        foreach ($sample_row_indices as $sr) {
            if (isset($rows[$sr][$col]) && is_numeric(trim((string)$rows[$sr][$col]))) {
                $num_cnt++;
            }
        }
        if ($num_cnt > 0) {
            $valid_columns[$col] = $day;
        }
    }

    echo "Sheet $sheetNum: '$sheetName' | Item: '$item_check_name' | LSL: $lsl, USL: $usl $uom | Days: " . count($valid_columns) . " | SampleRows: " . count($sample_row_indices) . "\n";
}

$zip->close();

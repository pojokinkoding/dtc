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

echo "Total sheets: " . count($sheets) . "\n\n";

for ($idx = 4; $idx < count($sheets); $idx++) { // Sheet 5 is index 4
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
            $rowCells[$colLetter] = getCellValue($c, $sharedStrings);
        }
        $rows[$rNum] = $rowCells;
    }

    // Inspect Item Check / Process Name from header
    $check_point = '';
    for ($r = 1; $r <= 6; $r++) {
        if (isset($rows[$r]['L']) && trim($rows[$r]['L']) !== '') {
            $check_point = trim($rows[$r]['L']);
            break;
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
    for ($r = 10; $r <= 22; $r++) {
        if (isset($rows[$r])) {
            foreach ($rows[$r] as $col => $val) {
                if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                    $dateRow = $r;
                    break 2;
                }
            }
        }
    }

    // Check sample rows and sample counts
    $sample_count = 0;
    $valid_days = [];
    if ($dateRow) {
        foreach ($rows[$dateRow] as $col => $val) {
            if ($col === 'A' || $col === 'B' || $val === null || $val === '') continue;
            if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                $day = intval($val);
                $cnt = 0;
                for ($sr = $dateRow + 1; $sr <= $dateRow + 15; $sr++) {
                    if (isset($rows[$sr][$col]) && is_numeric($rows[$sr][$col])) {
                        $cnt++;
                        $sample_count++;
                    }
                }
                if ($cnt > 0) $valid_days[] = "$day($cnt)";
            }
        }
    }

    echo "Sheet $sheetNum: '$sheetName' | Item: '$check_point' | Model Excel: '$model_excel' | LSL: '$lsl', USL: '$usl' | DateRow: " . ($dateRow ?: 'NONE') . " | Days: " . count($valid_days) . " | Samples: $sample_count\n";
}

$zip->close();

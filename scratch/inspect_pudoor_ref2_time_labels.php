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

echo "=== CHECKING COLUMN B TIME LABELS IN CTP PU DOOR REF 02 SHEETS ===\n\n";

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

    // Find date row
    $dateRow = null;
    for ($r = 10; $r <= 20; $r++) {
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

    echo "Sheet $sheetNum: '$sheetName' (DateRow: $dateRow)\n";
    for ($sr = $dateRow + 1; $sr <= $dateRow + 10; $sr++) {
        if (!isset($rows[$sr])) continue;
        $rawB = isset($rows[$sr]['B']) ? $rows[$sr]['B'] : '';
        $rawB_str = (string)$rawB;

        // format calculation check
        $formatted = '';
        if (is_numeric($rawB_str) && floatval($rawB_str) > 0 && floatval($rawB_str) < 1) {
            $totalMinutes = round(floatval($rawB_str) * 24 * 60);
            $hours = floor($totalMinutes / 60);
            $mins = $totalMinutes % 60;
            $formatted = sprintf('%02d:%02d', $hours, $mins);
        } else {
            $formatted = str_replace('.', ':', trim($rawB_str));
        }

        echo "  Row $sr | Column B Raw: '$rawB_str' => Calculated Format: '$formatted'\n";
    }
    echo "\n";
}

$zip->close();

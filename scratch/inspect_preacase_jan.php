<?php
// Detect the correct filename - either ctp_precase_jan.xlsx or ctp_preacase_jan.xlsx
$possibleFiles = [
    __DIR__ . '/../ctp_precase_jan.xlsx',
    __DIR__ . '/../ctp_preacase_jan.xlsx'
];
$file = null;
foreach ($possibleFiles as $f) {
    if (file_exists($f)) { $file = $f; break; }
}
if (!$file) die("File not found in any expected path\n");
echo "Using file: $file\n";

$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Failed\n");

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);
$wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $sheets[] = ['name'=>(string)$sheet['name'],'rId'=>(string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id']];
}

$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$rels = simplexml_load_string($relsXml);
$targetMap = [];
foreach ($rels->Relationship as $rel) { $targetMap[(string)$rel['Id']] = (string)$rel['Target']; }

$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
    $ss = simplexml_load_string($ssXml);
    foreach ($ss->si as $si) {
        if (isset($si->t)) $sharedStrings[] = (string)$si->t;
        else { $t=''; foreach($si->r as $r){$t.=(string)$r->t;} $sharedStrings[]=$t; }
    }
}

function getCellValue($cell, $sharedStrings) {
    $type = (string)$cell['t']; $v = (string)$cell->v;
    if ($type === 's') return isset($sharedStrings[intval($v)]) ? $sharedStrings[intval($v)] : $v;
    return $v;
}

echo "Total sheets: " . count($sheets) . "\n\n";
for ($i = 0; $i < count($sheets); $i++) {
    echo "Sheet " . ($i+1) . ": '" . $sheets[$i]['name'] . "'\n";
}

// Inspect each sheet
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
        if ($rNum > 35) break;
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

    echo "\n===== Sheet $sheetNum: '$sheetName' =====\n";
    foreach ($rows as $r => $cells) {
        echo "  Row $r: " . json_encode($cells, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
$zip->close();

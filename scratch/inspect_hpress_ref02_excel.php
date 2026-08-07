<?php
$files = array_filter(glob(__DIR__ . '/../*202601*Check Sheet Out Door H Press REF02.xlsx'), function($f) {
    return strpos(basename($f), '~$') !== 0;
});
if (empty($files)) die("File not found via glob\n");
$file = array_values($files)[0];
echo "Found file path: $file\n";

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

echo "=== SHEETS IN 202601 Check Sheet Out Door H Press REF02.xlsx ===\n";
echo "Total sheets: " . count($sheets) . "\n";
$sNames = array_map(function($s){ return "'{$s['name']}'"; }, array_slice($sheets, 0, 15));
echo "First 15 sheets: " . implode(', ', $sNames) . "\n";

// Inspect Sheet 1
$sh1 = $sheets[0];
$targetFile = 'xl/' . $targetMap[$sh1['rId']];
$sheetXml = $zip->getFromName($targetFile);
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

echo "\n=== FIRST SHEET ('{$sh1['name']}') ROWS 1 TO 30 ===\n";
for ($r = 1; $r <= 30; $r++) {
    if (!isset($grid[$r])) continue;
    $rowVals = [];
    foreach ($grid[$r] as $col => $val) {
        if ($val !== '') $rowVals[] = "$col:'$val'";
    }
    if (!empty($rowVals)) {
        echo "Row " . sprintf('%02d', $r) . " -> " . implode(' | ', array_slice($rowVals, 0, 10)) . "\n";
    }
}
?>

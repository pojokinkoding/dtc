<?php
// Verify actual raw values from ctp_precase_jun.xlsx
$file = __DIR__ . '/../ctp_precase_jun.xlsx';
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

$s = $sheets[0];
$sheetXml = $zip->getFromName('xl/' . $targetMap[$s['rId']]);
$xml = simplexml_load_string($sheetXml);

$rows = [];
foreach ($xml->sheetData->row as $row) {
    $rNum = (int)$row['r'];
    if ($rNum > 55) break;
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

// Show rows 20-35 (data area)
echo "Sheet: '{$s['name']}'\n";
echo "Rows 20-35 (data area):\n\n";
for ($r = 20; $r <= 35; $r++) {
    if (isset($rows[$r])) {
        echo "Row $r: " . json_encode($rows[$r], JSON_UNESCAPED_UNICODE) . "\n";
    }
}

$zip->close();

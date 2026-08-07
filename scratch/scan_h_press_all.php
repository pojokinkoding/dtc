<?php
$file = __DIR__ . '/../202601 Time_Check H Press out_door REF01.xlsx';
if (!file_exists($file)) die("File not found\n");

$zip = new ZipArchive();
$zip->open($file);

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $name = (string)$sheet['name'];
    $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $sheets[] = ['name' => $name, 'rId' => $rId];
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

$modelsFound = [];
$valuesF = [];
$valuesI = [];
$rowCount = 0;

foreach ($sheets as $sh) {
    $sheetName = $sh['name'];
    if (!is_numeric($sheetName)) continue;

    $targetFile = 'xl/' . $targetMap[$sh['rId']];
    $sheetXml = $zip->getFromName($targetFile);
    if (!$sheetXml) continue;
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

    for ($r = 21; $r <= 33; $r++) {
        if (!isset($grid[$r])) continue;
        $model = $grid[$r]['C'] ?? '';
        $timeFraction = $grid[$r]['B'] ?? '';
        $valF = $grid[$r]['F'] ?? '';
        $valI = $grid[$r]['I'] ?? '';

        if ($model !== '') {
            $rowCount++;
            $modelsFound[$model] = ($modelsFound[$model] ?? 0) + 1;
            if ($valF !== '') $valuesF[$valF] = ($valuesF[$valF] ?? 0) + 1;
            if ($valI !== '') $valuesI[$valI] = ($valuesI[$valI] ?? 0) + 1;

            if ($rowCount <= 20) {
                echo "Sheet '$sheetName' Row $r -> TimeFrac: '$timeFraction' | Model: '$model' | Col F: '$valF' | Col I: '$valI'\n";
            }
        }
    }
}

echo "\nTotal rows with model: $rowCount\n";
echo "Distinct Models found: " . json_encode(array_keys($modelsFound)) . "\n";
echo "Distinct Col F values: " . json_encode($valuesF) . "\n";
echo "Distinct Col I values: " . json_encode($valuesI) . "\n";
?>

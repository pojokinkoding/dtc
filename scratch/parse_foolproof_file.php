<?php
$file = __DIR__ . '/../202601 Foolproof Hinge Lower REF01.xlsx';

$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) {
    die("Failed to open excel zip file.\n");
}

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $name = (string)$sheet['name'];
    $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $sheets[] = ['name' => $name, 'rId' => $rId];
}

echo "Sheet List:\n";
echo json_encode($sheets, JSON_PRETTY_PRINT) . "\n\n";

$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
$sharedStrings = [];
if ($sharedStringsXml) {
    $ss = simplexml_load_string($sharedStringsXml);
    foreach ($ss->si as $val) {
        if (isset($val->t)) {
            $sharedStrings[] = (string)$val->t;
        } else if (isset($val->r)) {
            $txt = '';
            foreach ($val->r as $r) {
                $txt .= (string)$r->t;
            }
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

foreach ($sheets as $s) {
    $targetFile = 'xl/' . $targetMap[$s['rId']];
    $sheetXml = $zip->getFromName($targetFile);
    if (!$sheetXml) continue;
    $sXml = simplexml_load_string($sheetXml);
    
    echo "=== SHEET: {$s['name']} ===\n";
    $rowCount = 0;
    foreach ($sXml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        if ($rNum > 15) continue; // print first 15 rows
        $rowCells = [];
        foreach ($row->c as $c) {
            $cellRef = (string)$c['r'];
            $t = (string)$c['t'];
            $v = (string)$c->v;
            $val = '';
            if ($t === 's' && isset($sharedStrings[(int)$v])) {
                $val = $sharedStrings[(int)$v];
            } else {
                $val = $v;
            }
            if (trim($val) !== '') {
                $rowCells[] = "$cellRef: $val";
            }
        }
        if (!empty($rowCells)) {
            echo "Row $rNum -> " . implode(' | ', $rowCells) . "\n";
        }
    }
    echo "\n";
}
?>

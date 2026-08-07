<?php
// read_excel_data.php
$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
if (!file_exists($file)) die("File not found\n");
$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Failed to open excel zip file.\n");
$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);
$wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

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
            foreach ($si->r as $r) {
                $t .= (string)$r->t;
            }
            $sharedStrings[] = $t;
        }
    }
}

function getCellValue($cell, $sharedStrings) {
    $type = (string)$cell['t'];
    $v = (string)$cell->v;
    if ($type === 's') {
        return isset($sharedStrings[intval($v)]) ? $sharedStrings[intval($v)] : $v;
    }
    return $v;
}

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $sheets[] = ['name' => (string)$sheet['name'], 'rId' => (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id']];
}

foreach ([0, 2] as $idx) {
    $sheetName = $sheets[$idx]['name'];
    echo "==== SHEET: $sheetName ====\n";
    $sheetPath = 'xl/' . $targetMap[$sheets[$idx]['rId']];
    $sheetXml = $zip->getFromName($sheetPath);
    $xml = simplexml_load_string($sheetXml);
    
    $rowCount = 0;
    foreach ($xml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        if ($rNum > 35) continue; // Only first 35 rows
        $rowCells = [];
        foreach ($row->c as $c) {
            $colRef = (string)$c['r'];
            preg_match('/([A-Z]+)(\d+)/', $colRef, $matches);
            $colLetter = $matches[1] ?? $colRef;
            $val = getCellValue($c, $sharedStrings);
            // limit to standard columns to avoid giant dump
            if (strlen($colLetter) == 1 || (strlen($colLetter) == 2 && $colLetter <= 'AG')) {
                 if (trim($val) !== '') {
                     $rowCells[$colLetter] = $val;
                 }
            }
        }
        if (!empty($rowCells)) {
            echo "Row $rNum: " . json_encode($rowCells) . "\n";
        }
    }
    echo "\n";
}

$zip->close();
?>

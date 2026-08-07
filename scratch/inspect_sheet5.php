<?php
// inspect_sheet5.php
$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
$zip = new ZipArchive();
$zip->open($file);

$sheetXml = $zip->getFromName('xl/worksheets/sheet5.xml');
$xml = simplexml_load_string($sheetXml);

$ssXml = $zip->getFromName('xl/sharedStrings.xml');
$ss = simplexml_load_string($ssXml);
$sharedStrings = [];
foreach ($ss->si as $si) {
    $t = '';
    if (isset($si->t)) $t = (string)$si->t;
    else { foreach ($si->r as $r) $t .= (string)$r->t; }
    $sharedStrings[] = $t;
}

function getCellValue($cell, $sharedStrings) {
    $type = (string)$cell['t'];
    $v = (string)$cell->v;
    if ($type === 's') return $sharedStrings[intval($v)] ?? $v;
    return $v;
}

foreach ($xml->sheetData->row as $row) {
    $rNum = (int)$row['r'];
    if ($rNum > 25) continue;
    foreach ($row->c as $c) {
        $colRef = (string)$c['r'];
        $val = getCellValue($c, $sharedStrings);
        if (trim($val) !== '') {
            echo "Cell $colRef = " . json_encode($val) . "\n";
        }
    }
}
?>

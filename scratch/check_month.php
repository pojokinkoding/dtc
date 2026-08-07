<?php
// check_month.php
$file = __DIR__ . '/../202603 TIME CHECK V FORMING MALE A REF01.xlsx';
$zip = new ZipArchive();
$zip->open($file);
$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
$ss = simplexml_load_string($ssXml);
$sharedStrings = [];
foreach ($ss->si as $si) {
    $t = '';
    if (isset($si->t)) $t = (string)$si->t;
    else { foreach ($si->r as $r) $t .= (string)$r->t; }
    $sharedStrings[] = $t;
}
foreach ($sharedStrings as $str) {
    if (strpos($str, 'Maret') !== false || strpos($str, 'March') !== false || strpos($str, 'Februari') !== false) {
        echo "Found Month in Shared Strings: $str\n";
    }
}
?>

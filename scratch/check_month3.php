<?php
// check_month3.php
$file = __DIR__ . '/../202603 TIME CHECK V FORMING MALE A REF01.xlsx';
$zip = new ZipArchive();
$zip->open($file);
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
$ss = simplexml_load_string($ssXml);
$i = 0;
foreach ($ss->si as $si) {
    $t = '';
    if (isset($si->t)) $t = (string)$si->t;
    else { foreach ($si->r as $r) $t .= (string)$r->t; }
    echo "[$i] => " . json_encode($t) . "\n";
    $i++;
}
?>

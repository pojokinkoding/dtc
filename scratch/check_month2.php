<?php
// check_month2.php
$file = __DIR__ . '/../202603 TIME CHECK V FORMING MALE A REF01.xlsx';
$zip = new ZipArchive();
$zip->open($file);
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
$ss = simplexml_load_string($ssXml);
foreach ($ss->si as $si) {
    $t = (string)($si->t ?? '');
    if (trim($t) !== '') {
        if (preg_match('/(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des)/i', $t)) {
            echo "String: $t\n";
        }
    }
}
?>

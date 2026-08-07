<?php
// dump_shared_strings_utf8.php
$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
$zip = new ZipArchive();
$zip->open($file);
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
$ss = simplexml_load_string($ssXml);
$out = "";
$i = 0;
foreach ($ss->si as $si) {
    $t = '';
    if (isset($si->t)) {
        $t = (string)$si->t;
    } else {
        foreach ($si->r as $r) {
            $t .= (string)$r->t;
        }
    }
    $out .= "[$i] => " . json_encode($t) . "\n";
    $i++;
}
file_put_contents(__DIR__ . '/ss_dump.txt', $out);
?>

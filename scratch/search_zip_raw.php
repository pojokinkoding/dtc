<?php
// search_zip_raw.php
$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
$zip = new ZipArchive();
$zip->open($file);

for ($i = 0; $i < $zip->numFiles; $i++) {
    $filename = $zip->getNameIndex($i);
    $content = $zip->getFromIndex($i);
    if (strpos($content, 'Hasil') !== false || strpos($content, 'Door') !== false || strpos($content, 'Banding') !== false) {
        echo "FOUND IN FILE: $filename\n";
    }
}
?>

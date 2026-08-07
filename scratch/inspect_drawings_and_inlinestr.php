<?php
// inspect_drawings_and_inlinestr.php
$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
$zip = new ZipArchive();
$zip->open($file);

for ($i = 0; $i < $zip->numFiles; $i++) {
    $filename = $zip->getNameIndex($i);
    if (strpos($filename, 'xl/drawings/') !== false || strpos($filename, 'xl/worksheets/') !== false) {
        $content = $zip->getFromIndex($i);
        preg_match_all('/<t[^>]*>(.*?)<\/t>/i', $content, $matches);
        if (!empty($matches[1])) {
            echo "FILE: $filename\n";
            foreach ($matches[1] as $t) {
                if (trim($t) !== '') {
                    echo "   - " . json_encode($t) . "\n";
                }
            }
        }
    }
}
?>

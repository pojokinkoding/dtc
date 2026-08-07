<?php
// dump_drawings.php
$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
$zip = new ZipArchive();
$zip->open($file);

foreach (['xl/drawings/drawing1.xml', 'xl/drawings/drawing2.xml', 'xl/drawings/drawing3.xml', 'xl/drawings/drawing4.xml', 'xl/drawings/drawing5.xml'] as $df) {
    $content = $zip->getFromName($df);
    if ($content) {
        echo "=== FILE: $df ===\n";
        preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/i', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $t) {
                echo "   - " . json_encode($t) . "\n";
            }
        }
    }
}
?>

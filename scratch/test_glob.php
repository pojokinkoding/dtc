<?php
function find_all_xlsx($dir) {
    $results = [];
    $files = scandir($dir);
    foreach ($files as $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            if (pathinfo($path, PATHINFO_EXTENSION) === 'xlsx') {
                $results[] = $path;
            }
        } else if ($value != "." && $value != "..") {
            $results = array_merge($results, find_all_xlsx($path));
        }
    }
    return $results;
}

$allXlsx = find_all_xlsx('c:/xampp/htdocs/dtq');
echo "=== ALL XLSX FILES FOUND IN PROJECT (" . count($allXlsx) . ") ===\n";
foreach ($allXlsx as $f) {
    echo "  " . $f . "\n";
}
?>

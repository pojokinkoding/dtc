<?php
$dir = __DIR__ . '/../';
$files = scandir($dir);

echo "Matching Excel files:\n";
foreach ($files as $f) {
    if (preg_match('/Time_Check.*H.*Press/i', $f) || preg_match('/202601.*Time/i', $f) || preg_match('/out_door/i', $f)) {
        echo " - $f\n";
    }
}
?>

<?php
require_once __DIR__ . '/../config/config.php';
$c = getDBConnection();
$s = $c->query("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE '%label%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($s as $r) {
    echo "KEY: " . $r['setting_key'] . "\n";
    $val = is_resource($r['setting_value']) ? stream_get_contents($r['setting_value']) : $r['setting_value'];
    echo "VAL: " . $val . "\n\n";
}

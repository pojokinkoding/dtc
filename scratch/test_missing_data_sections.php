<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_GET['month'] = '2026-06';

require_once __DIR__ . '/../config/config.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Admin';

chdir(__DIR__ . '/../Script/php/dtc');
ob_start();
require 'c_missing_data.php';
$output = ob_get_clean();

$res = json_decode($output, true);
echo "Status: " . $res['status'] . "\n";
echo "Total Parameters Returned: " . count($res['data']) . "\n";

$sections = [];
foreach ($res['data'] as $p) {
    $sec = $p['line_name'] . ' ___ ' . $p['section_name'];
    if (!isset($sections[$sec])) $sections[$sec] = 0;
    $sections[$sec]++;
}
echo "Sections & Parameter counts:\n";
echo json_encode($sections, JSON_PRETTY_PRINT) . "\n";
?>

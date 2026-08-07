<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_GET['date'] = date('Y-m-d');

chdir(__DIR__ . '/../Script/php/dtc');
ob_start();
require 'c_dtc_bulk_get.php';
$out = ob_get_clean();

$res = json_decode($out, true);
echo "Status: " . ($res['status'] ?? 'err') . "\n";
echo "Time Labels Returned:\n";
print_r($res['time_labels'] ?? []);

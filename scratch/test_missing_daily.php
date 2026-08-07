<?php
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'user';
$_SESSION['section_name'] = 'PU Door';
$_SESSION['line_name'] = 'REF 01';
$_SERVER['REMOTE_ADDR'] = '10.221.176.36'; // IP for REF 01 PU Door

chdir(__DIR__ . '/../Script/php/dtc');
ob_start();
include 'c_missing_data_daily.php';
$out = ob_get_clean();
echo $out . "\n";

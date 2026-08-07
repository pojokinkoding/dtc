<?php
// Script/php/dtc/c_dtc_download_template.php

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="DTC_Measurement_Import_Template.csv"');

$output = fopen('php://output', 'w');

// Write the headers
fputcsv($output, array('Date', 'S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'S9', 'S10', 'Remarks'));

// Optionally write a dummy row to guide the user
fputcsv($output, array('2026-07-01', '10.5', '10.6', '', '', '', '', '', '', '', '', 'Optional remarks here'));

fclose($output);
?>

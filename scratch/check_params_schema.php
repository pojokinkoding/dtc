<?php
require 'config/config.php';
$c = getDBConnection();
$r = $c->query('DESCRIBE dtc_master_parameters')->fetchAll(PDO::FETCH_ASSOC);
foreach($r as $f) echo $f['Field'].': '.$f['Type']."\n";

echo "\n--- Sample measuring_item values ---\n";
$r2 = $c->query("SELECT DISTINCT measuring_item, data_type FROM dtc_master_parameters LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
foreach($r2 as $row) echo "measuring_item=".$row['measuring_item']." | data_type=".$row['data_type']."\n";

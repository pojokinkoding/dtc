<?php
// Generic CTP Pre Case REF 02 importer
// Usage: php import_precase_ref2_generic.php <filename.xlsx> <YYYY-MM>
require_once __DIR__ . '/../config/config.php';

if ($argc < 3) die("Usage: php import_precase_ref2_generic.php <filename.xlsx> <YYYY-MM>\n");

$filename = $argv[1];
$target_month = $argv[2];
[$year, $month] = explode('-', $target_month);
$month_int = intval($month);

// Support both precase and preacase naming
$file = __DIR__ . '/../' . $filename;
if (!file_exists($file)) {
    // Try alternate spelling
    $alt = str_replace('precase', 'preacase', $filename);
    $altFile = __DIR__ . '/../' . $alt;
    if (file_exists($altFile)) { $file = $altFile; $filename = $alt; }
    else die("Error: File $filename not found.\n");
}

echo "Starting CTP Pre Case Import (REF 02) - $target_month...\n";
echo "Using file: $filename\n\n";

$line_name = 'REF 02';
$section_name = 'Pre Case';
$data_type = 'CTP';

$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) die("Failed to open excel zip file.\n");

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);
$wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $name = (string)$sheet['name'];
    $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $sheets[] = ['name' => $name, 'rId' => $rId];
}

$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$rels = simplexml_load_string($relsXml);
$targetMap = [];
foreach ($rels->Relationship as $rel) { $targetMap[(string)$rel['Id']] = (string)$rel['Target']; }

$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
    $ss = simplexml_load_string($ssXml);
    foreach ($ss->si as $si) {
        if (isset($si->t)) $sharedStrings[] = (string)$si->t;
        else { $t = ''; foreach ($si->r as $r) { $t .= (string)$r->t; } $sharedStrings[] = $t; }
    }
}

function getCellValue($cell, $sharedStrings) {
    $type = (string)$cell['t']; $v = (string)$cell->v;
    if ($type === 's') return isset($sharedStrings[intval($v)]) ? $sharedStrings[intval($v)] : $v;
    return $v;
}

function cleanTimeLabel($lbl) {
    $lbl = trim($lbl);
    if (preg_match('/^(\d{1,2})\.(\d{2})$/', $lbl, $m)) {
        return sprintf('%02d:%02d', intval($m[1]), intval($m[2]));
    }
    return $lbl;
}

$conn = getDBConnection();
$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;

$summary_results = [];
echo "Total sheets: " . count($sheets) . "\n\n";

for ($idx = 0; $idx < count($sheets); $idx++) {
    $sheetNum = $idx + 1;
    $sheetName = $sheets[$idx]['name'];

    $sheetPath = 'xl/' . $targetMap[$sheets[$idx]['rId']];
    $sheetXml = $zip->getFromName($sheetPath);
    if (!$sheetXml) { echo "Sheet $sheetNum '$sheetName': no XML, skipping.\n"; continue; }
    $xml = simplexml_load_string($sheetXml);

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        $rowCells = [];
        foreach ($row->c as $c) {
            $colRef = (string)$c['r'];
            preg_match('/([A-Z]+)(\d+)/', $colRef, $matches);
            $colLetter = $matches[1] ?? $colRef;
            $val = getCellValue($c, $sharedStrings);
            $rowCells[$colLetter] = $val;
        }
        $rows[$rNum] = $rowCells;
    }

    // Model name = sheet name
    $model_name = trim($sheetName);

    // Item check name = Row 5 col E
    $item_check_name = trim($rows[5]['E'] ?? '');
    if (!$item_check_name) $item_check_name = $model_name;

    // Process name = Row 7 col E
    $process_name = trim($rows[7]['E'] ?? 'Foam Injection');
    if (!$process_name) $process_name = 'Foam Injection';

    // LSL/USL from rows 5-10, col I (label) + col L (value), col M (UOM)
    $lsl = null; $usl = null; $uom = '';
    for ($r = 5; $r <= 10; $r++) {
        if (!isset($rows[$r])) continue;
        $specLabel = strtoupper(trim(preg_replace('/\s+/', '', $rows[$r]['I'] ?? '')));
        if (strpos($specLabel, 'LSL') !== false) {
            $lsl = isset($rows[$r]['L']) && is_numeric($rows[$r]['L']) ? floatval($rows[$r]['L']) : null;
            if (!$uom && !empty($rows[$r]['M'])) $uom = trim($rows[$r]['M']);
        }
        if (strpos($specLabel, 'USL') !== false) {
            $usl = isset($rows[$r]['L']) && is_numeric($rows[$r]['L']) ? floatval($rows[$r]['L']) : null;
            if (!$uom && !empty($rows[$r]['M'])) $uom = trim($rows[$r]['M']);
        }
    }

    if ($lsl === null || $usl === null) {
        echo "Sheet $sheetNum '$sheetName': LSL/USL not found - SKIPPED.\n";
        $summary_results[$sheetName] = ['status' => 'SKIPPED_NO_SPEC', 'sessions' => 0, 'measurements' => 0];
        continue;
    }
    $target_val = ($lsl + $usl) / 2;

    // Find date row (col B contains "Jam")
    $dateRow = null;
    for ($r = 18; $r <= 30; $r++) {
        if (strpos(strtolower(trim($rows[$r]['B'] ?? '')), 'jam') !== false) { $dateRow = $r; break; }
    }
    if (!$dateRow) {
        echo "Sheet $sheetNum '$sheetName': Date row not found - SKIPPED.\n";
        $summary_results[$sheetName] = ['status' => 'SKIPPED_NO_DATA', 'sessions' => 0, 'measurements' => 0];
        continue;
    }

    // Sample rows
    $sample_rows = [];
    for ($sr = $dateRow + 1; $sr <= $dateRow + 20; $sr++) {
        $bLabel = trim($rows[$sr]['B'] ?? '');
        if ($bLabel === '') continue;
        if (stristr($bLabel, 'Max') !== false || stristr($bLabel, 'Min') !== false ||
            stristr($bLabel, 'Average') !== false || stristr($bLabel, 'Xbar') !== false ||
            stristr($bLabel, 'Std') !== false) break;
        $sample_rows[] = $sr;
    }

    // Valid day columns
    $valid_columns = [];
    foreach ($rows[$dateRow] as $col => $val) {
        if ($col === 'A' || $col === 'B') continue;
        $val_str = trim((string)$val);
        if (!is_numeric($val_str)) continue;
        $day = intval($val_str);
        if ($day < 1 || $day > 31) continue;
        $numeric_count = 0;
        foreach ($sample_rows as $sr) {
            $rv = trim($rows[$sr][$col] ?? '');
            if ($rv !== '' && is_numeric($rv)) $numeric_count++;
        }
        if ($numeric_count > 0) $valid_columns[$col] = $day;
    }

    if (empty($valid_columns) || empty($sample_rows)) {
        echo "Sheet $sheetNum '$sheetName': No data - SKIPPED.\n";
        $summary_results[$sheetName] = ['status' => 'SKIPPED_NO_DATA', 'sessions' => 0, 'measurements' => 0];
        continue;
    }

    echo "Processing Sheet $sheetNum: '$sheetName'\n";
    echo "  Model: '$model_name' | Item: '$item_check_name' | Process: '$process_name' | LSL: $lsl | USL: $usl $uom | DataCols: " . count($valid_columns) . "\n";

    // 1. Master Spec
    $stmt_spec_find = $conn->prepare("SELECT spec_id FROM dtc_master_dtc_specs 
        WHERE UPPER(model_name) = UPPER(:model) AND UPPER(item_check_name) = UPPER(:item) 
        AND UPPER(process_name) = UPPER(:proc) AND UPPER(line_name) = UPPER(:line) 
        AND UPPER(section_name) = UPPER(:sec) AND UPPER(data_type) = UPPER(:dt)
        AND ABS(lsl - :lsl) < 0.001 AND ABS(usl - :usl) < 0.001");
    $stmt_spec_find->execute([':model'=>$model_name,':item'=>$item_check_name,':proc'=>$process_name,':line'=>$line_name,':sec'=>$section_name,':dt'=>$data_type,':lsl'=>$lsl,':usl'=>$usl]);
    $spec_id = $stmt_spec_find->fetchColumn();

    if (!$spec_id) {
        $conn->prepare("INSERT INTO dtc_master_dtc_specs (model_name, item_check_name, data_type, line_name, section_name, process_name, measuring_item, lsl, usl, target_value, uom, target_zst, target_zlt)
            VALUES (:model_name, :item_check_name, :data_type, :line_name, :section_name, :process_name, 'Quantitative', :lsl, :usl, :target_value, :uom, 4.0, 3.0)")
            ->execute([':model_name'=>$model_name,':item_check_name'=>$item_check_name,':data_type'=>$data_type,':line_name'=>$line_name,':section_name'=>$section_name,':process_name'=>$process_name,':lsl'=>$lsl,':usl'=>$usl,':target_value'=>$target_val,':uom'=>$uom]);
        $spec_id = $conn->lastInsertId();
        echo "  Created NEW Spec ID: $spec_id\n";
    } else {
        echo "  Found existing Spec ID: $spec_id\n";
    }

    // 2. Parameter
    $stmt_param_find = $conn->prepare("SELECT parameter_id FROM dtc_master_parameters WHERE spec_id = :spec_id AND target_month = :target_month");
    $stmt_param_find->execute([':spec_id'=>$spec_id,':target_month'=>$target_month]);
    $parameter_id = $stmt_param_find->fetchColumn();

    if (!$parameter_id) {
        $conn->prepare("INSERT INTO dtc_master_parameters (spec_id, target_month, item_check_name, data_type, line_name, section_name, process_name, measuring_item, target_zst, target_zlt) 
            VALUES (:spec_id, :target_month, :item_check_name, :data_type, :line_name, :section_name, :process_name, 'Quantitative', 4.0, 3.0)")
            ->execute([':spec_id'=>$spec_id,':target_month'=>$target_month,':item_check_name'=>$item_check_name,':data_type'=>$data_type,':line_name'=>$line_name,':section_name'=>$section_name,':process_name'=>$process_name]);
        $parameter_id = $conn->lastInsertId();
        echo "  Created NEW Parameter ID: $parameter_id for $target_month\n";
    } else {
        echo "  Found existing Parameter ID: $parameter_id for $target_month\n";
    }

    // 3. Running model
    $conn->prepare("INSERT IGNORE INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active) VALUES (:tm, :ln, :sn, :mn, 1)")
        ->execute([':tm'=>$target_month,':ln'=>$line_name,':sn'=>$section_name,':mn'=>$model_name]);

    // 4. Process each day
    $sessions_created = 0; $measurements_created = 0;

    foreach ($valid_columns as $col => $day) {
        $inspection_date = sprintf('%04d-%02d-%02d', $year, $month_int, $day);

        $sample_inputs = []; $sample_labels = []; $numeric_samples = [];
        foreach ($sample_rows as $i => $sr) {
            $raw_val = trim($rows[$sr][$col] ?? '');
            $raw_lbl = trim($rows[$sr]['B'] ?? '');
            $lbl = cleanTimeLabel($raw_lbl) ?: "Sample " . ($i + 1);
            $sample_inputs[] = $raw_val;
            $sample_labels[] = $lbl;
            if ($raw_val !== '' && is_numeric($raw_val)) $numeric_samples[] = floatval($raw_val);
        }

        if (count($numeric_samples) === 0) continue;

        $n = count($numeric_samples);
        $max_val = max($numeric_samples); $min_val = min($numeric_samples);
        $x_bar = array_sum($numeric_samples) / $n;
        $range_val = $max_val - $min_val; $std_dev = 0;
        if ($n > 1) {
            $sum_sq = 0;
            foreach ($numeric_samples as $sv) $sum_sq += pow($sv - $x_bar, 2);
            $std_dev = sqrt($sum_sq / ($n - 1));
        }

        $stmt_check_s = $conn->prepare("SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id = :param_id AND DATE(inspection_date) = :idate");
        $stmt_check_s->execute([':param_id'=>$parameter_id,':idate'=>$inspection_date]);
        $existing_s = $stmt_check_s->fetch(PDO::FETCH_ASSOC);

        if ($existing_s) {
            $session_id = $existing_s['session_id'];
            $conn->prepare("UPDATE dtc_inspection_sessions SET is_active=1,max_value=:mx,min_value=:mn,x_bar=:xb,range_value=:rng,std_dev=:std,is_closed=0 WHERE session_id=:sid")
                ->execute([':mx'=>$max_val,':mn'=>$min_val,':xb'=>$x_bar,':rng'=>$range_val,':std'=>$std_dev,':sid'=>$session_id]);
            $conn->prepare("DELETE FROM dtc_measurements WHERE session_id=:sid")->execute([':sid'=>$session_id]);
        } else {
            $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, remarks, is_active, max_value, min_value, x_bar, range_value, std_dev, is_closed)
                VALUES (:param_id, :idate, :op_id, '', 1, :mx, :mn, :xb, :rng, :std, 0)")
                ->execute([':param_id'=>$parameter_id,':idate'=>$inspection_date,':op_id'=>$operator_id,':mx'=>$max_val,':mn'=>$min_val,':xb'=>$x_bar,':rng'=>$range_val,':std'=>$std_dev]);
            $session_id = $conn->lastInsertId();
        }
        $sessions_created++;

        $stmt_ins_m = $conn->prepare("INSERT INTO dtc_measurements (session_id, sample_sequence, sample_label, sample_value, created_by, modified_by, modified_date) VALUES (:sid, :seq, :lbl, :val, :cb, :mb, CURRENT_TIMESTAMP)");
        for ($i = 0; $i < count($sample_inputs); $i++) {
            $rv = $sample_inputs[$i] ?? '';
            if ($rv !== '') {
                $stmt_ins_m->execute([':sid'=>$session_id,':seq'=>$i+1,':lbl'=>$sample_labels[$i],':val'=>$rv,':cb'=>$operator_id,':mb'=>$operator_id]);
                $measurements_created++;
            }
        }
    }

    echo "  Finished: $sessions_created sessions, $measurements_created measurements.\n";
    $summary_results[$sheetName] = ['model'=>$model_name,'item'=>$item_check_name,'process'=>$process_name,'spec_id'=>$spec_id,'parameter_id'=>$parameter_id,'status'=>'IMPORTED','lsl'=>$lsl,'usl'=>$usl,'uom'=>$uom,'sessions'=>$sessions_created,'measurements'=>$measurements_created];
}

$zip->close();

echo "\n====================================================\n";
echo "CTP PRE CASE (REF 02) $target_month IMPORT COMPLETE:\n";
$total_sessions = array_sum(array_column($summary_results, 'sessions'));
$total_measurements = array_sum(array_column($summary_results, 'measurements'));
$imported = array_filter($summary_results, fn($r) => ($r['status'] ?? '') === 'IMPORTED');
echo "Sheets imported: " . count($imported) . " | Sessions: $total_sessions | Measurements: $total_measurements\n";
foreach ($summary_results as $name => $r) {
    $status = $r['status'] ?? 'UNKNOWN';
    if ($status === 'IMPORTED') {
        echo "  [OK] $name | Item: {$r['item']} | LSL: {$r['lsl']} | USL: {$r['usl']} {$r['uom']} | Spec: {$r['spec_id']} | Param: {$r['parameter_id']} | Sessions: {$r['sessions']} | Measurements: {$r['measurements']}\n";
    } else {
        echo "  [SKIP] $name - $status\n";
    }
}

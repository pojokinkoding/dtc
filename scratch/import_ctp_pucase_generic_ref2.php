<?php
// Generic CTP PU Case Importer - Line REF 02
// Usage: php import_ctp_pucase_generic_ref2.php <filename.xlsx> <YYYY-MM>
require_once __DIR__ . '/../config/config.php';

if ($argc < 3) {
    die("Usage: php import_ctp_pucase_generic_ref2.php <filename.xlsx> <YYYY-MM>\n");
}

$filename = $argv[1];
$target_month = $argv[2];

$file = __DIR__ . '/../' . $filename;
if (!file_exists($file)) {
    die("Error: File $filename not found.\n");
}

// Parse year and month from target_month
[$year, $month] = explode('-', $target_month);
$month_int = intval($month);

echo "Starting CTP PU Case Import for $target_month ($filename) - Line REF 02...\n";

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
foreach ($rels->Relationship as $rel) {
    $targetMap[(string)$rel['Id']] = (string)$rel['Target'];
}

$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
    $ss = simplexml_load_string($ssXml);
    foreach ($ss->si as $si) {
        if (isset($si->t)) {
            $sharedStrings[] = (string)$si->t;
        } else {
            $t = '';
            foreach ($si->r as $r) { $t .= (string)$r->t; }
            $sharedStrings[] = $t;
        }
    }
}

function getCellValue($cell, $sharedStrings) {
    $type = (string)$cell['t'];
    $v = (string)$cell->v;
    if ($type === 's') return isset($sharedStrings[intval($v)]) ? $sharedStrings[intval($v)] : $v;
    return $v;
}

function formatPucaseTimeLabel($bVal, $seq) {
    $bVal_raw = trim((string)$bVal);
    if (is_numeric($bVal_raw) && floatval($bVal_raw) > 0 && floatval($bVal_raw) < 1) {
        $totalMinutes = round(floatval($bVal_raw) * 24 * 60);
        $hours = floor($totalMinutes / 60);
        $mins = $totalMinutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }
    $bVal_str = str_replace('.', ':', $bVal_raw);
    return $bVal_str !== '' ? $bVal_str : "Sample $seq";
}

$conn = getDBConnection();
$line_name = 'REF 02';
$section_name = 'PU Case';
$data_type = 'CTP';
$process_name = 'Foam Injection';
$measuring_item = 'Quantitative';
$uom = '℃';

$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;

// Start after Sheet 3 (index >= 3)
$startIndex = 3;

echo "Total sheets: " . count($sheets) . ". Processing from sheet index $startIndex onwards...\n";

$summary_results = [];

for ($idx = $startIndex; $idx < count($sheets); $idx++) {
    $sheetNum = $idx + 1;
    $sheetName = $sheets[$idx]['name'];

    $sheetPath = 'xl/' . $targetMap[$sheets[$idx]['rId']];
    $sheetXml = $zip->getFromName($sheetPath);
    if (!$sheetXml) continue;

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

    $model_name = trim($sheetName);

    // Item check name: try Row 3 col L first, then Row 4 col L, fallback to sheet name
    $item_check_name = '';
    if (isset($rows[3]['L']) && trim((string)$rows[3]['L']) !== '') {
        $item_check_name = trim((string)$rows[3]['L']);
    } elseif (isset($rows[4]['L']) && trim((string)$rows[4]['L']) !== '') {
        $item_check_name = trim((string)$rows[4]['L']);
    }
    if (!$item_check_name) $item_check_name = $model_name;

    // LSL / USL from rows 5-12 col K (label) and M (value)
    $lsl = null;
    $usl = null;
    for ($r = 5; $r <= 12; $r++) {
        if (!isset($rows[$r])) continue;
        $kVal = isset($rows[$r]['K']) ? strtoupper(trim((string)$rows[$r]['K'])) : '';
        if ($kVal === 'USL') $usl = isset($rows[$r]['M']) && is_numeric($rows[$r]['M']) ? floatval($rows[$r]['M']) : null;
        if ($kVal === 'LSL') $lsl = isset($rows[$r]['M']) && is_numeric($rows[$r]['M']) ? floatval($rows[$r]['M']) : null;
    }

    if ($lsl === null || $usl === null) {
        echo "\n--> SKIPPED: Sheet $sheetNum '$sheetName' - Could not detect LSL/USL.\n";
        $summary_results[$sheetName] = ['status' => 'SKIPPED_NO_SPEC', 'sessions' => 0, 'measurements' => 0];
        continue;
    }

    $target_val = ($lsl + $usl) / 2;

    // Detect date header row (rows 10..20)
    $dateRow = null;
    for ($r = 10; $r <= 20; $r++) {
        if (isset($rows[$r])) {
            foreach ($rows[$r] as $col => $val) {
                if ($col === 'A' || $col === 'B') continue;
                if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                    $dateRow = $r; break 2;
                }
            }
        }
    }

    if (!$dateRow) {
        echo "\n--> SKIPPED: Sheet $sheetNum '$sheetName' - No date row found.\n";
        $summary_results[$sheetName] = ['model' => $model_name, 'status' => 'SKIPPED_NO_DATA', 'sessions' => 0, 'measurements' => 0];
        continue;
    }

    // Find sample rows below dateRow
    $sample_row_indices = [];
    for ($sr = $dateRow + 1; $sr <= $dateRow + 10; $sr++) {
        $lbl = isset($rows[$sr]['B']) ? trim((string)$rows[$sr]['B']) : '';
        if ($lbl === '') continue;
        if (strcasecmp($lbl, 'Xbar') === 0 || strcasecmp($lbl, 'R') === 0 ||
            stristr($lbl, 'Std') !== false || stristr($lbl, 'Max') !== false ||
            stristr($lbl, 'Min') !== false || stristr($lbl, 'Average') !== false ||
            stristr($lbl, 'Date') !== false) break;
        $sample_row_indices[] = $sr;
    }

    // Detect valid day columns
    $has_data = false;
    $valid_columns = [];
    foreach ($rows[$dateRow] as $col => $val) {
        if ($col === 'A' || $col === 'B' || $val === null || $val === '') continue;
        $val_str = trim((string)$val);
        if (!is_numeric($val_str)) continue;
        $day = intval($val_str);
        if ($day < 1 || $day > 31) continue;
        $numeric_count = 0;
        foreach ($sample_row_indices as $sr) {
            $raw_val = isset($rows[$sr][$col]) ? trim((string)$rows[$sr][$col]) : '';
            if ($raw_val !== '' && is_numeric($raw_val)) $numeric_count++;
        }
        if ($numeric_count > 0) { $has_data = true; $valid_columns[$col] = $day; }
    }

    if (!$has_data || empty($sample_row_indices)) {
        echo "\n--> SKIPPED: Sheet $sheetNum '$sheetName' has NO measurement data.\n";
        $summary_results[$sheetName] = ['model' => $model_name, 'status' => 'SKIPPED_NO_DATA', 'sessions' => 0, 'measurements' => 0];
        continue;
    }

    echo "\n----------------------------------------------------\n";
    echo "Processing Sheet $sheetNum: '$sheetName'\n";
    echo "Model: '$model_name' | Item: '$item_check_name' | LSL: $lsl | USL: $usl $uom | DataCols: " . count($valid_columns) . "\n";

    // 1. Find or Insert Master Spec
    $stmt_spec_find = $conn->prepare("SELECT spec_id FROM dtc_master_dtc_specs 
        WHERE UPPER(model_name) = UPPER(:model) 
        AND UPPER(item_check_name) = UPPER(:item) 
        AND UPPER(process_name) = UPPER(:proc)
        AND UPPER(line_name) = UPPER(:line) 
        AND UPPER(section_name) = UPPER(:sec)
        AND UPPER(data_type) = UPPER(:dt)
        AND ABS(lsl - :lsl) < 0.001
        AND ABS(usl - :usl) < 0.001");
    $stmt_spec_find->execute([':model'=>$model_name,':item'=>$item_check_name,':proc'=>$process_name,':line'=>$line_name,':sec'=>$section_name,':dt'=>$data_type,':lsl'=>$lsl,':usl'=>$usl]);
    $spec_id = $stmt_spec_find->fetchColumn();

    if (!$spec_id) {
        $conn->prepare("INSERT INTO dtc_master_dtc_specs (model_name, item_check_name, data_type, line_name, section_name, process_name, measuring_item, lsl, usl, target_value, uom, target_zst, target_zlt)
            VALUES (:model_name, :item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item, :lsl, :usl, :target_value, :uom, 4.0, 3.0)")
            ->execute([':model_name'=>$model_name,':item_check_name'=>$item_check_name,':data_type'=>$data_type,':line_name'=>$line_name,':section_name'=>$section_name,':process_name'=>$process_name,':measuring_item'=>$measuring_item,':lsl'=>$lsl,':usl'=>$usl,':target_value'=>$target_val,':uom'=>$uom]);
        $spec_id = $conn->lastInsertId();
        echo "Created NEW Master Spec ID: $spec_id\n";
    } else {
        echo "Found existing Master Spec ID: $spec_id\n";
    }

    // 2. Find or Insert Parameter
    $stmt_param_find = $conn->prepare("SELECT parameter_id FROM dtc_master_parameters WHERE spec_id = :spec_id AND target_month = :target_month");
    $stmt_param_find->execute([':spec_id'=>$spec_id,':target_month'=>$target_month]);
    $parameter_id = $stmt_param_find->fetchColumn();

    if (!$parameter_id) {
        $conn->prepare("INSERT INTO dtc_master_parameters (spec_id, target_month, item_check_name, data_type, line_name, section_name, process_name, measuring_item, target_zst, target_zlt) 
            VALUES (:spec_id, :target_month, :item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item, 4.0, 3.0)")
            ->execute([':spec_id'=>$spec_id,':target_month'=>$target_month,':item_check_name'=>$item_check_name,':data_type'=>$data_type,':line_name'=>$line_name,':section_name'=>$section_name,':process_name'=>$process_name,':measuring_item'=>$measuring_item]);
        $parameter_id = $conn->lastInsertId();
        echo "Created NEW Parameter ID: $parameter_id for $target_month\n";
    } else {
        echo "Found existing Parameter ID: $parameter_id for $target_month\n";
    }

    // 3. Ensure running model entry
    $conn->prepare("INSERT IGNORE INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active) VALUES (:tm, :ln, :sn, :mn, 1)")
        ->execute([':tm'=>$target_month,':ln'=>$line_name,':sn'=>$section_name,':mn'=>$model_name]);

    // 4. Process each valid day column
    $sessions_created = 0;
    $measurements_created = 0;

    foreach ($valid_columns as $col => $day) {
        $inspection_date = sprintf('%04d-%02d-%02d', $year, $month_int, $day);

        $sample_inputs = [];
        $sample_labels = [];
        $numeric_samples = [];

        $seq = 1;
        foreach ($sample_row_indices as $sr) {
            $raw_val = isset($rows[$sr][$col]) ? trim((string)$rows[$sr][$col]) : '';
            $raw_b   = isset($rows[$sr]['B'])  ? trim((string)$rows[$sr]['B'])  : '';
            $lbl = formatPucaseTimeLabel($raw_b, $seq);
            $sample_inputs[] = $raw_val;
            $sample_labels[] = $lbl;
            if ($raw_val !== '' && is_numeric($raw_val)) $numeric_samples[] = floatval($raw_val);
            $seq++;
        }

        if (count($numeric_samples) === 0) continue;

        $n = count($numeric_samples);
        $max_val   = max($numeric_samples);
        $min_val   = min($numeric_samples);
        $x_bar     = array_sum($numeric_samples) / $n;
        $range_val = $max_val - $min_val;
        $std_dev   = 0;
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
            $conn->prepare("UPDATE dtc_inspection_sessions SET is_active=1, max_value=:mx, min_value=:mn, x_bar=:xb, range_value=:rng, std_dev=:std, is_closed=0 WHERE session_id=:sid")
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
            $raw_val = $sample_inputs[$i] ?? '';
            if ($raw_val !== '') {
                $stmt_ins_m->execute([':sid'=>$session_id,':seq'=>$i+1,':lbl'=>$sample_labels[$i] ?? "Sample ".($i+1),':val'=>$raw_val,':cb'=>$operator_id,':mb'=>$operator_id]);
                $measurements_created++;
            }
        }
    }

    echo "Finished Sheet $sheetNum '$sheetName': $sessions_created sessions, $measurements_created measurements.\n";
    $summary_results[$sheetName] = ['model'=>$model_name,'item'=>$item_check_name,'spec_id'=>$spec_id,'parameter_id'=>$parameter_id,'status'=>'IMPORTED','lsl'=>$lsl,'usl'=>$usl,'sessions'=>$sessions_created,'measurements'=>$measurements_created];
}

$zip->close();

echo "\n====================================================\n";
echo "CTP PU CASE $target_month LINE REF 02 IMPORT COMPLETE:\n";
$total_sessions = array_sum(array_column($summary_results, 'sessions'));
$total_measurements = array_sum(array_column($summary_results, 'measurements'));
$imported = array_filter($summary_results, fn($r) => ($r['status'] ?? '') === 'IMPORTED');
echo "Sheets imported: " . count($imported) . " | Total sessions: $total_sessions | Total measurements: $total_measurements\n";
foreach ($summary_results as $name => $r) {
    $status = $r['status'] ?? 'UNKNOWN';
    if ($status === 'IMPORTED') {
        echo "  [OK] $name | Spec: {$r['spec_id']} | Param: {$r['parameter_id']} | Sessions: {$r['sessions']} | Measurements: {$r['measurements']}\n";
    } else {
        echo "  [SKIP] $name - $status\n";
    }
}

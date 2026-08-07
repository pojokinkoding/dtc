<?php
// Importer for CTP PU Door Excel file for May 2026 (ctp_pudoor_may.xlsx) - Line REF 02
require_once __DIR__ . '/../config/config.php';

echo "Starting CTP PU Door Import for May 2026 (ctp_pudoor_may.xlsx) - Line REF 02...\n";

$file = __DIR__ . '/../ctp_pudoor_may.xlsx';
if (!file_exists($file)) {
    die("Error: File ctp_pudoor_may.xlsx not found.\n");
}

$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) {
    die("Failed to open excel zip file.\n");
}

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
            foreach ($si->r as $r) {
                $t .= (string)$r->t;
            }
            $sharedStrings[] = $t;
        }
    }
}

function getCellValue($cell, $sharedStrings) {
    $type = (string)$cell['t'];
    $v = (string)$cell->v;
    if ($type === 's') {
        return isset($sharedStrings[intval($v)]) ? $sharedStrings[intval($v)] : $v;
    }
    return $v;
}

function formatPudoorTimeLabelRef2($bVal, $seq) {
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
$target_month = '2026-05';

// Find manual sheet index
$manualIndex = -1;
for ($i = 0; $i < count($sheets); $i++) {
    if (stristr($sheets[$i]['name'], 'manual') !== false) {
        $manualIndex = $i;
    }
}

if ($manualIndex < 0) {
    die("Error: Sheet 'Manual' not found in Excel workbook.\n");
}

echo "Found 'Manual' sheet at index " . ($manualIndex + 1) . ". Processing sheets starting from index " . ($manualIndex + 2) . "...\n";

// Get an operator ID from database
$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;

$summary_results = [];

for ($idx = $manualIndex + 1; $idx < count($sheets); $idx++) {
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

    $line_name = 'REF 02';
    $section_name = 'PU Door';
    $data_type = 'CTP';

    // Model name set to Sheet Name as requested by user
    $model_name = $sheetName;

    // Detect item check name from header
    $item_check_name = '';
    for ($r = 3; $r <= 6; $r++) {
        if (isset($rows[$r]['L']) && trim((string)$rows[$r]['L']) !== '') {
            $item_check_name = trim((string)$rows[$r]['L']);
            break;
        }
    }
    if (!$item_check_name) {
        $item_check_name = $sheetName;
    }

    $process_name = 'Foam Injection';

    // Detect LSL / USL
    $lsl = null;
    $usl = null;

    for ($r = 5; $r <= 10; $r++) {
        if (isset($rows[$r]['K']) && strtoupper(trim((string)$rows[$r]['K'])) === 'USL') {
            $usl = isset($rows[$r]['M']) ? floatval($rows[$r]['M']) : null;
        }
        if (isset($rows[$r]['K']) && strtoupper(trim((string)$rows[$r]['K'])) === 'LSL') {
            $lsl = isset($rows[$r]['M']) ? floatval($rows[$r]['M']) : null;
        }
    }

    if ($lsl === null) $lsl = 20.0;
    if ($usl === null) $usl = 50.0;
    $uom = '℃';
    $target_val = ($lsl + $usl) / 2;
    $measuring_item = 'Quantitative';

    // Detect date header row (row 10..20)
    $dateRow = null;
    for ($r = 10; $r <= 20; $r++) {
        if (isset($rows[$r])) {
            foreach ($rows[$r] as $col => $val) {
                if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                    $dateRow = $r;
                    break 2;
                }
            }
        }
    }

    if (!$dateRow) {
        echo "\n----------------------------------------------------\n";
        echo "Skipping non-data sheet $sheetNum: '$sheetName'\n";
        $summary_results[$sheetName] = [
            'status' => 'SKIPPED_NO_DATA',
            'sessions' => 0,
            'measurements' => 0
        ];
        continue;
    }

    // Find sample rows following dateRow (ONLY rows where Column B time label is present!)
    $sample_row_indices = [];
    for ($sr = $dateRow + 1; $sr <= $dateRow + 10; $sr++) {
        $lbl = isset($rows[$sr]['B']) ? trim((string)$rows[$sr]['B']) : '';
        if (strcasecmp($lbl, 'Xbar') === 0 || strcasecmp($lbl, 'R') === 0 || stristr($lbl, 'Std') !== false || stristr($lbl, 'Max') !== false || stristr($lbl, 'Min') !== false || stristr($lbl, 'Average') !== false) {
            break;
        }
        if ($lbl !== '') {
            $sample_row_indices[] = $sr;
        }
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
            if ($raw_val !== '' && is_numeric($raw_val)) {
                $numeric_count++;
            }
        }

        if ($numeric_count > 0) {
            $has_data = true;
            $valid_columns[$col] = $day;
        }
    }

    if (!$has_data) {
        echo "\n----------------------------------------------------\n";
        echo "--> SKIPPED: Sheet $sheetNum '$sheetName' has NO measurement data.\n";
        $summary_results[$sheetName] = [
            'model' => $model_name,
            'status' => 'SKIPPED_NO_DATA',
            'sessions' => 0,
            'measurements' => 0
        ];
        continue;
    }

    echo "\n----------------------------------------------------\n";
    echo "Processing Sheet $sheetNum: '$sheetName'\n";
    echo "Model: '$model_name', Spec: LSL=$lsl, USL=$usl $uom, Line: '$line_name', Section: '$section_name', Item: '$item_check_name', Process: '$process_name', Type: '$data_type'\n";

    // 1. Find or Insert Master Spec in dtc_master_dtc_specs
    $sql_spec_find = "SELECT spec_id FROM dtc_master_dtc_specs 
                      WHERE UPPER(model_name) = UPPER(:model) 
                      AND UPPER(item_check_name) = UPPER(:item) 
                      AND UPPER(process_name) = UPPER(:proc)
                      AND UPPER(line_name) = UPPER(:line) 
                      AND UPPER(section_name) = UPPER(:sec)
                      AND UPPER(data_type) = UPPER(:dt)
                      AND ABS(lsl - :lsl) < 0.001
                      AND ABS(usl - :usl) < 0.001";
    $stmt_spec_find = $conn->prepare($sql_spec_find);
    $stmt_spec_find->execute([
        ':model' => $model_name,
        ':item' => $item_check_name,
        ':proc' => $process_name,
        ':line' => $line_name,
        ':sec' => $section_name,
        ':dt' => $data_type,
        ':lsl' => $lsl,
        ':usl' => $usl
    ]);
    $spec_id = $stmt_spec_find->fetchColumn();

    if (!$spec_id) {
        $sql_ins_spec = "INSERT INTO dtc_master_dtc_specs (
                    model_name, item_check_name, data_type, line_name, section_name, process_name, measuring_item, 
                    lsl, usl, target_value, uom, target_zst, target_zlt
                ) VALUES (
                    :model_name, :item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item,
                    :lsl, :usl, :target_value, :uom, 4.0, 3.0
                )";
        $stmt_ins_spec = $conn->prepare($sql_ins_spec);
        $stmt_ins_spec->execute([
            ':model_name' => $model_name,
            ':item_check_name' => $item_check_name,
            ':data_type' => $data_type,
            ':line_name' => $line_name,
            ':section_name' => $section_name,
            ':process_name' => $process_name,
            ':measuring_item' => $measuring_item,
            ':lsl' => $lsl,
            ':usl' => $usl,
            ':target_value' => $target_val,
            ':uom' => $uom
        ]);
        $spec_id = $conn->lastInsertId();
        echo "Created NEW Master Spec ID: $spec_id\n";
    } else {
        echo "Found existing Master Spec ID: $spec_id\n";
    }

    // 2. Find or Insert Parameter in dtc_master_parameters for target_month = '2026-05'
    $sql_param_find = "SELECT parameter_id FROM dtc_master_parameters WHERE spec_id = :spec_id AND target_month = :target_month";
    $stmt_param_find = $conn->prepare($sql_param_find);
    $stmt_param_find->execute([':spec_id' => $spec_id, ':target_month' => $target_month]);
    $parameter_id = $stmt_param_find->fetchColumn();

    if (!$parameter_id) {
        $sql_ins_param = "INSERT INTO dtc_master_parameters 
                (spec_id, target_month, item_check_name, data_type, line_name, section_name, process_name, measuring_item, target_zst, target_zlt) 
                VALUES (:spec_id, :target_month, :item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item, 4.0, 3.0)";
        $stmt_ins_param = $conn->prepare($sql_ins_param);
        $stmt_ins_param->execute([
            ':spec_id' => $spec_id,
            ':target_month' => $target_month,
            ':item_check_name' => $item_check_name,
            ':data_type' => $data_type,
            ':line_name' => $line_name,
            ':section_name' => $section_name,
            ':process_name' => $process_name,
            ':measuring_item' => $measuring_item
        ]);
        $parameter_id = $conn->lastInsertId();
        echo "Created NEW Parameter ID: $parameter_id for May 2026\n";
    } else {
        echo "Found existing Parameter ID: $parameter_id for May 2026\n";
    }

    // 3. Ensure running model entry in dtc_running_models for target_month = '2026-05'
    $stmt_rm = $conn->prepare("INSERT IGNORE INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active) VALUES (:tm, :ln, :sn, :mn, 1)");
    $stmt_rm->execute([':tm' => $target_month, ':ln' => $line_name, ':sn' => $section_name, ':mn' => $model_name]);

    // 4. Process each valid day column
    $sessions_created = 0;
    $measurements_created = 0;

    foreach ($valid_columns as $col => $day) {
        $inspection_date = sprintf('2026-05-%02d', $day);

        $sample_inputs = [];
        $sample_labels = [];
        $numeric_samples = [];

        $seq = 1;
        foreach ($sample_row_indices as $sr) {
            $raw_val = isset($rows[$sr][$col]) ? trim((string)$rows[$sr][$col]) : '';
            $raw_b = isset($rows[$sr]['B']) ? trim((string)$rows[$sr]['B']) : '';
            $lbl = formatPudoorTimeLabelRef2($raw_b, $seq);

            $sample_inputs[] = $raw_val;
            $sample_labels[] = $lbl;

            if ($raw_val !== '' && is_numeric($raw_val)) {
                $numeric_samples[] = floatval($raw_val);
            }
            $seq++;
        }

        if (count($numeric_samples) === 0) continue;

        // Calculate statistics
        $n = count($numeric_samples);
        $max_val = max($numeric_samples);
        $min_val = min($numeric_samples);
        $sum = array_sum($numeric_samples);
        $x_bar = $sum / $n;
        $range_val = $max_val - $min_val;
        $std_dev = 0;
        if ($n > 1) {
            $sum_sq = 0;
            foreach ($numeric_samples as $sv) {
                $sum_sq += pow(($sv - $x_bar), 2);
            }
            $std_dev = sqrt($sum_sq / ($n - 1));
        }

        // Check if session exists
        $sql_check_s = "SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id = :param_id AND DATE(inspection_date) = :idate";
        $stmt_check_s = $conn->prepare($sql_check_s);
        $stmt_check_s->execute([':param_id' => $parameter_id, ':idate' => $inspection_date]);
        $existing_s = $stmt_check_s->fetch(PDO::FETCH_ASSOC);

        if ($existing_s) {
            $session_id = $existing_s['session_id'];
            $sql_upd_s = "UPDATE dtc_inspection_sessions 
                          SET is_active = 1, max_value = :mx, min_value = :mn, x_bar = :xb, range_value = :rng, std_dev = :std, is_closed = 0
                          WHERE session_id = :sid";
            $stmt_upd_s = $conn->prepare($sql_upd_s);
            $stmt_upd_s->execute([
                ':mx' => $max_val, ':mn' => $min_val, ':xb' => $x_bar, ':rng' => $range_val, ':std' => $std_dev,
                ':sid' => $session_id
            ]);

            $stmt_del = $conn->prepare("DELETE FROM dtc_measurements WHERE session_id = :sid");
            $stmt_del->execute([':sid' => $session_id]);
        } else {
            $sql_ins_s = "INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, remarks, is_active, max_value, min_value, x_bar, range_value, std_dev, is_closed)
                          VALUES (:param_id, :idate, :op_id, '', 1, :mx, :mn, :xb, :rng, :std, 0)";
            $stmt_ins_s = $conn->prepare($sql_ins_s);
            $stmt_ins_s->execute([
                ':param_id' => $parameter_id,
                ':idate' => $inspection_date,
                ':op_id' => $operator_id,
                ':mx' => $max_val, ':mn' => $min_val, ':xb' => $x_bar, ':rng' => $range_val, ':std' => $std_dev
            ]);
            $session_id = $conn->lastInsertId();
        }

        $sessions_created++;

        // Insert measurements
        $sql_ins_m = "INSERT INTO dtc_measurements (session_id, sample_sequence, sample_label, sample_value, created_by, modified_by, modified_date)
                      VALUES (:sid, :seq, :lbl, :val, :cb, :mb, CURRENT_TIMESTAMP)";
        $stmt_ins_m = $conn->prepare($sql_ins_m);

        for ($i = 0; $i < count($sample_inputs); $i++) {
            $raw_val = $sample_inputs[$i] ?? '';
            if ($raw_val !== '') {
                $mSeq = $i + 1;
                $mLbl = $sample_labels[$i] ?? "Sample $mSeq";
                $stmt_ins_m->execute([
                    ':sid' => $session_id,
                    ':seq' => $mSeq,
                    ':lbl' => $mLbl,
                    ':val' => $raw_val,
                    ':cb' => $operator_id,
                    ':mb' => $operator_id
                ]);
                $measurements_created++;
            }
        }
    }

    echo "Finished Sheet $sheetNum '$sheetName': $sessions_created sessions, $measurements_created measurements created/updated.\n";
    $summary_results[$sheetName] = [
        'model' => $model_name,
        'item' => $item_check_name,
        'process' => $process_name,
        'status' => 'IMPORTED',
        'lsl' => $lsl,
        'usl' => $usl,
        'spec_id' => $spec_id,
        'parameter_id' => $parameter_id,
        'sessions' => $sessions_created,
        'measurements' => $measurements_created
    ];
}

$zip->close();

echo "\n====================================================\n";
echo "CTP PU DOOR MAY 2026 LINE REF 02 IMPORT COMPLETE SUMMARY:\n";
echo json_encode($summary_results, JSON_PRETTY_PRINT) . "\n";

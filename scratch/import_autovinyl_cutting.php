<?php
// Importer for 202601 Check Sheet AutoVinyl Cutting REF02.xlsx
require_once __DIR__ . '/../config/config.php';

$file_name = '202601 Check Sheet AutoVinyl Cutting REF02.xlsx';
$target_month = '2026-01';
$line_name = 'REF 02';
$section_name = 'Cutting Vinyl';
$data_type = 'Time Check';
$process_name = 'Cutting Vinyl';
$item_check_name = 'Cutting Vinyl';

echo "=== STARTING CLEAN IMPORT OF AUTOVINYL CUTTING F/PROOF ===\n";
echo "File: $file_name\n";
echo "Target Month: $target_month\n";
echo "Line: $line_name\n";
echo "Section: $section_name\n";
echo "Data Type: $data_type\n\n";

$file = __DIR__ . '/../' . $file_name;
if (!file_exists($file)) {
    die("Error: File '$file_name' not found at: $file\n");
}

$conn = getDBConnection();

// 1. STEP 1: CLEAN PREVIOUS DATA FOR THIS MONTH, LINE & SECTION
echo "--- Step 1: Cleaning previous data for $target_month ($line_name - $section_name) ---\n";
$stmt_get_params = $conn->prepare("
    SELECT parameter_id FROM dtc_master_parameters 
    WHERE target_month = :tm AND line_name = :ln AND section_name = :sec AND data_type = :dt
");
$stmt_get_params->execute([':tm' => $target_month, ':ln' => $line_name, ':sec' => $section_name, ':dt' => $data_type]);
$old_params = $stmt_get_params->fetchAll(PDO::FETCH_COLUMN);

if (!empty($old_params)) {
    $in_params = implode(',', array_map('intval', $old_params));
    echo "Found old parameter IDs to delete: $in_params\n";
    $conn->exec("DELETE FROM dtc_measurements WHERE session_id IN (SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params))");
    $conn->exec("DELETE FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params)");
    $conn->exec("DELETE FROM dtc_checkpoints WHERE parameter_id IN ($in_params)");
    $conn->exec("DELETE FROM dtc_master_parameters WHERE parameter_id IN ($in_params)");
}

$stmt_del_spec = $conn->prepare("DELETE FROM dtc_master_dtc_specs WHERE line_name = :ln AND section_name = :sec AND data_type = :dt");
$stmt_del_spec->execute([':ln' => $line_name, ':sec' => $section_name, ':dt' => $data_type]);

$stmt_del_rm = $conn->prepare("DELETE FROM dtc_running_models WHERE target_month = :tm AND line_name = :ln AND section_name = :sec");
$stmt_del_rm->execute([':tm' => $target_month, ':ln' => $line_name, ':sec' => $section_name]);
echo "Previous data cleaned successfully.\n\n";

// 2. STEP 2: OPEN EXCEL & PARSE SHEETS
$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) {
    die("Failed to open excel file.\n");
}

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $name = (string)$sheet['name'];
    $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $sheets[] = ['name' => trim($name), 'rId' => $rId];
}

$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
$sharedStrings = [];
if ($sharedStringsXml) {
    $ss = simplexml_load_string($sharedStringsXml);
    foreach ($ss->si as $val) {
        if (isset($val->t)) {
            $sharedStrings[] = (string)$val->t;
        } else if (isset($val->r)) {
            $txt = '';
            foreach ($val->r as $r) { $txt .= (string)$r->t; }
            $sharedStrings[] = $txt;
        } else {
            $sharedStrings[] = '';
        }
    }
}

$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$rels = simplexml_load_string($relsXml);
$targetMap = [];
foreach ($rels->Relationship as $rel) {
    $targetMap[(string)$rel['Id']] = (string)$rel['Target'];
}

function formatTimeLabel($val) {
    if (is_numeric($val)) {
        $floatVal = (float)$val;
        $totalMins = round($floatVal * 1440);
        $h = floor($totalMins / 60) % 24;
        $m = $totalMins % 60;
        return sprintf('%02d:%02d', $h, $m);
    }
    return trim($val);
}

$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;
$days_in_month = (int)date('t', strtotime($target_month . '-01'));

$summary = [];

foreach ($sheets as $sh) {
    $modelName = trim($sh['name']); // e.g. "MC 1", "MC 2", ...
    echo "--- Processing Machine / Model: '$modelName' ---\n";

    $targetFile = 'xl/' . $targetMap[$sh['rId']];
    $sheetXml = $zip->getFromName($targetFile);
    if (!$sheetXml) continue;
    $sXml = simplexml_load_string($sheetXml);

    $grid = [];
    foreach ($sXml->sheetData->row as $row) {
        $rNum = (int)$row['r'];
        foreach ($row->c as $c) {
            $cellRef = (string)$c['r'];
            preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $m);
            if (!$m) continue;
            $colLetter = $m[1];
            $t = (string)$c['t'];
            $v = (string)$c->v;
            $val = ($t === 's' && isset($sharedStrings[(int)$v])) ? $sharedStrings[(int)$v] : $v;
            $grid[$rNum][$colLetter] = trim($val);
        }
    }

    // Map Date Columns
    $dateCols = [];
    foreach ($grid[12] as $col => $val) {
        if (is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
            $dateCols[$col] = (int)$val;
        }
    }
    foreach ($grid[13] as $col => $val) {
        if (!isset($dateCols[$col]) && is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
            $dateCols[$col] = (int)$val;
        }
    }

    $modelDailyData = [];
    $checkpointsSpecMap = [];

    for ($r = 14; $r <= 60; $r++) {
        if (!isset($grid[$r])) continue;

        $cpName = $grid[$r]['D'] ?? '';
        $specOK = $grid[$r]['E'] ?? '';
        $specNG = $grid[$r]['F'] ?? '';
        $itemType = $grid[$r]['J'] ?? '';
        $timeFrac = $grid[$r]['I'] ?? '';

        if ($cpName !== '') {
            $currentCp = $cpName;
            $specValStr = "OK: $specOK" . ($specNG ? " / NG: $specNG" : "");
            $checkpointsSpecMap[$cpName] = $specValStr;
        }

        if ($itemType === 'Result' && $timeFrac !== '' && isset($currentCp)) {
            $timeLabel = formatTimeLabel($timeFrac);

            foreach ($dateCols as $col => $dayNum) {
                $rawVal = $grid[$r][$col] ?? '';
                if ($rawVal === '') continue;

                $dateStr = sprintf('%s-%02d', $target_month, $dayNum);
                $finalVal = (strcasecmp($rawVal, 'ok') === 0 || strcasecmp($rawVal, 'center') === 0) ? 'OK' : (strcasecmp($rawVal, 'ng') === 0 ? 'NG' : strtoupper($rawVal));

                if (!isset($modelDailyData[$dateStr])) {
                    $modelDailyData[$dateStr] = [];
                }

                $modelDailyData[$dateStr][] = [
                    'label' => $timeLabel,
                    'cpName' => $currentCp,
                    'val' => $finalVal
                ];
            }
        }
    }

    // 1. Insert master spec
    $stmt_ins_spec = $conn->prepare("
        INSERT INTO dtc_master_dtc_specs (model_name, item_check_name, data_type, line_name, section_name, process_name, measuring_item, lsl, usl)
        VALUES (:model, :item, :dtype, :line, :sec, :proc, 'Qualitative', 0, 0)
    ");
    $stmt_ins_spec->execute([
        ':model' => $modelName,
        ':item' => $item_check_name,
        ':dtype' => $data_type,
        ':line' => $line_name,
        ':sec' => $section_name,
        ':proc' => $process_name
    ]);
    $spec_id = $conn->lastInsertId();

    // 2. Insert master parameter
    $stmt_ins_p = $conn->prepare("
        INSERT INTO dtc_master_parameters (spec_id, target_month, model_name, item_check_name, data_type, line_name, section_name, process_name, measuring_item)
        VALUES (:sid, :month, :model, :item, :dtype, :line, :sec, :proc, 'Qualitative')
    ");
    $stmt_ins_p->execute([
        ':sid' => $spec_id,
        ':month' => $target_month,
        ':model' => $modelName,
        ':item' => $item_check_name,
        ':dtype' => $data_type,
        ':line' => $line_name,
        ':sec' => $section_name,
        ':proc' => $process_name
    ]);
    $parameter_id = $conn->lastInsertId();

    // Register running model
    $stmt_rm = $conn->prepare("INSERT IGNORE INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active) VALUES (:tm, :ln, :sn, :mn, 1)");
    $stmt_rm->execute([':tm' => $target_month, ':ln' => $line_name, ':sn' => $section_name, ':mn' => $modelName]);

    // 3. Create Checkpoint entries
    $cp_id_map = [];
    $sort_order = 1;
    foreach ($checkpointsSpecMap as $cpName => $specStr) {
        $stmt_ins_cp = $conn->prepare("
            INSERT INTO dtc_checkpoints (parameter_id, checkpoint_name, checkpoint_type, spec_value, sort_order)
            VALUES (:pid, :name, 'Qualitative', :spec, :sort)
        ");
        $stmt_ins_cp->execute([
            ':pid' => $parameter_id,
            ':name' => $cpName,
            ':spec' => $specStr,
            ':sort' => $sort_order++
        ]);
        $cp_id_map[$cpName] = $conn->lastInsertId();
        echo "  Checkpoint: '$cpName' | Spec: '$specStr'\n";
    }

    // 4. Create Sessions & Measurements for all 31 days (set is_closed = 1)
    $total_measurements = 0;
    $stmt_ins_s = $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, is_closed) VALUES (:pid, :idate, :uid, 1)");
    $stmt_ins_m = $conn->prepare("
        INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by)
        VALUES (:sid, :cpid, :seq, :label, :val, :uid)
    ");

    for ($d = 1; $d <= $days_in_month; $d++) {
        $dateStr = sprintf('%s-%02d', $target_month, $d);

        $stmt_ins_s->execute([':pid' => $parameter_id, ':idate' => $dateStr, ':uid' => $operator_id]);
        $session_id = $conn->lastInsertId();

        if (isset($modelDailyData[$dateStr])) {
            $seq = 1;
            foreach ($modelDailyData[$dateStr] as $m) {
                $cpName = $m['cpName'];
                if (!isset($cp_id_map[$cpName])) continue;

                $stmt_ins_m->execute([
                    ':sid' => $session_id,
                    ':cpid' => $cp_id_map[$cpName],
                    ':seq' => $seq++,
                    ':label' => $m['label'],
                    ':val' => $m['val'],
                    ':uid' => $operator_id
                ]);
                $total_measurements++;
            }
        }
    }

    $summary[$modelName] = [
        'parameter_id' => $parameter_id,
        'checkpoints' => count($cp_id_map),
        'days_closed' => $days_in_month,
        'measurements' => $total_measurements
    ];
    echo "  Finished Machine '$modelName': " . count($cp_id_map) . " checkpoints, $days_in_month days closed, $total_measurements measurements.\n\n";
}

echo "====================================================\n";
echo "AUTOVINYL CUTTING IMPORT SUMMARY ($target_month):\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
?>

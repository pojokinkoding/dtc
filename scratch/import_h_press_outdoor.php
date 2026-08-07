<?php
// Importer for H Press Outdoor Time Check Excel Files (with Checkpoints A, B/R, B/F, C, D, Bending)
require_once __DIR__ . '/../config/config.php';

$file_name = $argv[1] ?? '202601 Time_Check H Press out_door REF01.xlsx';
$target_month = $argv[2] ?? '2026-01';
$line_name = $argv[3] ?? 'REF 01';
$section_name = $argv[4] ?? 'H Press Out Door';
$data_type = 'Time Check';
$process_name = 'Spec Bending Outdoor';
$item_check_name = 'Spec Bending Outdoor';

echo "=== STARTING CLEAN RE-IMPORT OF H PRESS OUTDOOR TIME CHECK ===\n";
echo "File: $file_name\n";
echo "Target Month: $target_month\n";
echo "Line: $line_name\n";
echo "Section: $section_name\n";
echo "Data Type: $data_type\n\n";

$file = __DIR__ . '/../' . $file_name;
if (!file_exists($file)) {
    die("Error: File '$file_name' not found at path: $file\n");
}

$conn = getDBConnection();

// 1. STEP 1: CLEAN PREVIOUS DATA FOR THIS MONTH & SECTION
echo "--- Step 1: Cleaning previous data for $target_month ($section_name) ---\n";
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

// 2. STEP 2: OPEN EXCEL & PARSE SPECS MAP + DAILY SHEETS
$zip = new ZipArchive();
if ($zip->open($file) !== TRUE) {
    die("Failed to open excel zip file.\n");
}

$workbookXml = $zip->getFromName('xl/workbook.xml');
$wb = simplexml_load_string($workbookXml);

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    $name = (string)$sheet['name'];
    $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $sheets[] = ['name' => $name, 'rId' => $rId];
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
    if (is_numeric($val) && (float)$val < 1.0) {
        $totalMins = round((float)$val * 1440);
        $h = floor($totalMins / 60) % 24;
        $m = $totalMins % 60;
        return sprintf('%02d:%02d', $h, $m);
    }
    return trim($val);
}

function parseToleranceSpec($specStr) {
    $lsl = null;
    $usl = null;
    $target = null;
    
    if (preg_match('/^\s*([\d\.]+)\s*[\±\+\/\-]+\s*([\d\.]+)/u', $specStr, $m)) {
        $target = (float)$m[1];
        $tol = (float)$m[2];
        $lsl = $target - $tol;
        $usl = $target + $tol;
    } else if (preg_match('/^\s*([\d\.]+)\s*-\s*([\d\.]+)/', $specStr, $m)) {
        $lsl = (float)$m[1];
        $usl = (float)$m[2];
        $target = ($lsl + $usl) / 2.0;
    } else if (preg_match('/^\s*([\d\.]+)/', $specStr, $m)) {
        $target = (float)$m[1];
    }

    return ['lsl' => $lsl, 'usl' => $usl, 'target' => $target];
}

// Build Specs Map from Top Table of Sheet 1
$sh1 = $sheets[0];
$sheetXml1 = $zip->getFromName('xl/' . $targetMap[$sh1['rId']]);
$sXml1 = simplexml_load_string($sheetXml1);
$grid1 = [];
foreach ($sXml1->sheetData->row as $row) {
    $rNum = (int)$row['r'];
    foreach ($row->c as $c) {
        $cellRef = (string)$c['r'];
        preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $m);
        if (!$m) continue;
        $colLetter = $m[1];
        $t = (string)$c['t'];
        $v = (string)$c->v;
        $val = ($t === 's' && isset($sharedStrings[(int)$v])) ? $sharedStrings[(int)$v] : $v;
        $grid1[$rNum][$colLetter] = trim($val);
    }
}

$specsByModel = [];
for ($r = 5; $r <= 15; $r++) {
    if (!isset($grid1[$r])) continue;
    $modelCode = $grid1[$r]['C'] ?? '';
    if ($modelCode === '') continue;

    $cleanKey = strtoupper(preg_replace('/[\s\-]/', '', $modelCode));
    $specsByModel[$cleanKey] = [
        'A' => $grid1[$r]['D'] ?? '',
        'B / R' => $grid1[$r]['E'] ?? '',
        'B / F' => $grid1[$r]['F'] ?? '',
        'C' => $grid1[$r]['G'] ?? '',
        'D' => $grid1[$r]['H'] ?? '',
        'Bending' => 'OK'
    ];
}

$checkpointCols = [
    'D' => 'A',
    'E' => 'B / R',
    'F' => 'B / F',
    'G' => 'C',
    'H' => 'D',
    'I' => 'Bending'
];

// Map: modelName => dateStr => [ {label => ..., cpName => ..., rawVal => ..., val => ...}, ... ]
$modelDailyData = [];

foreach ($sheets as $sh) {
    $sheetName = trim($sh['name']);
    if (!is_numeric($sheetName)) continue;

    $dayNum = (int)$sheetName;
    $dateStr = sprintf('%s-%02d', $target_month, $dayNum);

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

    // Read rows starting from A21
    for ($r = 21; $r <= 33; $r++) {
        if (!isset($grid[$r])) continue;
        $model = $grid[$r]['C'] ?? '';
        $timeFrac = $grid[$r]['B'] ?? '';

        if ($model === '' || $timeFrac === '') continue;

        $timeLabel = formatTimeLabel($timeFrac);

        if (!isset($modelDailyData[$model])) {
            $modelDailyData[$model] = [];
        }
        if (!isset($modelDailyData[$model][$dateStr])) {
            $modelDailyData[$model][$dateStr] = [];
        }

        foreach ($checkpointCols as $col => $cpName) {
            $rawVal = $grid[$r][$col] ?? '';
            if ($rawVal === '') continue;

            $finalVal = $rawVal;
            if ($cpName === 'B / F') {
                $finalVal = preg_match('/1\s*door/i', $rawVal) ? 'OK' : (is_numeric($rawVal) ? $rawVal : 'NG');
            }

            $modelDailyData[$model][$dateStr][] = [
                'label' => $timeLabel,
                'cpName' => $cpName,
                'rawVal' => $rawVal,
                'val' => $finalVal
            ];
        }
    }
}

echo "Found " . count($modelDailyData) . " distinct models across the month.\n\n";

$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;

$summary = [];

foreach ($modelDailyData as $modelName => $datesData) {
    echo "--- Processing Model: '$modelName' (" . count($datesData) . " active days) ---\n";

    // 1. Find or create master spec entry in dtc_master_dtc_specs
    $stmt_spec = $conn->prepare("
        SELECT spec_id FROM dtc_master_dtc_specs 
        WHERE line_name = :line AND section_name = :sec AND process_name = :proc AND item_check_name = :item AND model_name = :model AND data_type = :dtype
    ");
    $stmt_spec->execute([
        ':line' => $line_name,
        ':sec' => $section_name,
        ':proc' => $process_name,
        ':item' => $item_check_name,
        ':model' => $modelName,
        ':dtype' => $data_type
    ]);
    $spec_id = $stmt_spec->fetchColumn();

    if (!$spec_id) {
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
    }

    // 2. Create master parameter entry in dtc_master_parameters for target_month
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

    // Lookup specs for this model from top table map
    $cleanModelKey = strtoupper(preg_replace('/[\s\-]/', '', $modelName));
    $modelSpecMap = $specsByModel[$cleanModelKey] ?? [];

    // 3. Create Checkpoints entries in dtc_checkpoints
    $cp_id_map = [];
    $sort_order = 1;

    foreach ($checkpointCols as $col => $cpName) {
        // Collect recorded values for this checkpoint to determine if Quantitative vs Qualitative
        $sampleVals = [];
        foreach ($datesData as $dStr => $mList) {
            foreach ($mList as $m) {
                if ($m['cpName'] === $cpName) {
                    $sampleVals[] = $m['val'];
                }
            }
        }

        if (empty($sampleVals)) continue;

        $numCount = 0;
        foreach ($sampleVals as $sv) {
            if (is_numeric($sv)) $numCount++;
        }

        $cpType = (count($sampleVals) > 0 && ($numCount / count($sampleVals)) >= 0.5) ? 'Quantitative' : 'Qualitative';
        $specValStr = $modelSpecMap[$cpName] ?? ($cpName === 'Bending' ? 'OK' : '');
        $parsedSpec = parseToleranceSpec($specValStr);

        $stmt_ins_cp = $conn->prepare("
            INSERT INTO dtc_checkpoints (parameter_id, checkpoint_name, checkpoint_type, spec_value, lsl, usl, target_value, sort_order)
            VALUES (:pid, :name, :ctype, :spec, :lsl, :usl, :target, :sort)
        ");
        $stmt_ins_cp->execute([
            ':pid' => $parameter_id,
            ':name' => $cpName,
            ':ctype' => $cpType,
            ':spec' => $specValStr,
            ':lsl' => $parsedSpec['lsl'],
            ':usl' => $parsedSpec['usl'],
            ':target' => $parsedSpec['target'],
            ':sort' => $sort_order++
        ]);
        $checkpoint_id = $conn->lastInsertId();
        $cp_id_map[$cpName] = $checkpoint_id;

        echo "  Checkpoint: '$cpName' | Type: $cpType | Spec: '$specValStr'\n";
    }

    // 4. Create Sessions & Measurements per active day
    $total_measurements = 0;

    foreach ($datesData as $dateStr => $measurements) {
        // Group measurements by time label
        $byTime = [];
        foreach ($measurements as $m) {
            $t = $m['label'];
            if (!isset($byTime[$t])) $byTime[$t] = [];
            $byTime[$t][] = $m;
        }

        // Create session
        $stmt_ins_s = $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, is_closed) VALUES (:pid, :idate, :uid, 1)");
        $stmt_ins_s->execute([':pid' => $parameter_id, ':idate' => $dateStr, ':uid' => $operator_id]);
        $session_id = $conn->lastInsertId();

        $stmt_ins_m = $conn->prepare("
            INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by)
            VALUES (:sid, :cpid, :seq, :label, :val, :uid)
        ");

        $seq = 1;
        foreach ($byTime as $tLabel => $mList) {
            foreach ($mList as $m) {
                $cpName = $m['cpName'];
                if (!isset($cp_id_map[$cpName])) continue;

                $stmt_ins_m->execute([
                    ':sid' => $session_id,
                    ':cpid' => $cp_id_map[$cpName],
                    ':seq' => $seq++,
                    ':label' => $tLabel,
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
        'active_days' => count($datesData),
        'measurements' => $total_measurements
    ];
    echo "  Finished Model '$modelName': " . count($cp_id_map) . " checkpoints, " . count($datesData) . " days, $total_measurements measurements.\n\n";
}

echo "====================================================\n";
echo "H PRESS OUTDOOR RE-IMPORT COMPLETE SUMMARY ($target_month):\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
?>

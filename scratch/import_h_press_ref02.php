<?php
// Importer for 202601 Check Sheet Out Door H Press REF02.xlsx
require_once __DIR__ . '/../config/config.php';

function findExcelFile($p1, $p2 = '') {
    $dir = __DIR__ . '/..';
    $dh = opendir($dir);
    while (($file = readdir($dh)) !== false) {
        if (strpos($file, '~$') === 0) continue;
        if (strpos($file, $p1) !== false && ($p2 === '' || strpos($file, $p2) !== false)) {
            closedir($dh);
            return $dir . '/' . $file;
        }
    }
    closedir($dh);
    return null;
}

$target_month = '2026-01';
$line_name = 'REF 02';
$section_name = 'H Press Out Door';
$data_type = 'F/Proof';
$process_name = 'spec bending outdoor';
$item_check_name = 'spec bending outdoor';

echo "=== RE-STARTING CLEAN IMPORT OF H PRESS OUTDOOR REF02 (2026-01) ===\n";

$file = findExcelFile('202601', 'Out Door H Press');
if (!$file) die("Error: Excel file not found\n");
echo "Source File: " . basename($file) . "\n\n";

$conn = getDBConnection();

// Clean previous
$stmt_get_params = $conn->prepare("
    SELECT parameter_id FROM dtc_master_parameters 
    WHERE target_month = :tm AND line_name = :ln AND section_name = :sec AND data_type = :dt
");
$stmt_get_params->execute([':tm' => $target_month, ':ln' => $line_name, ':sec' => $section_name, ':dt' => $data_type]);
$old_params = $stmt_get_params->fetchAll(PDO::FETCH_COLUMN);

if (!empty($old_params)) {
    $in_params = implode(',', array_map('intval', $old_params));
    $conn->exec("DELETE FROM dtc_measurements WHERE session_id IN (SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params))");
    $conn->exec("DELETE FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params)");
    $conn->exec("DELETE FROM dtc_checkpoints WHERE parameter_id IN ($in_params)");
    $conn->exec("DELETE FROM dtc_master_parameters WHERE parameter_id IN ($in_params)");
}

$stmt_del_spec = $conn->prepare("DELETE FROM dtc_master_dtc_specs WHERE line_name = :ln AND section_name = :sec AND data_type = :dt");
$stmt_del_spec->execute([':ln' => $line_name, ':sec' => $section_name, ':dt' => $data_type]);

// Open Excel
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

function normalizeModelName($m) {
    return preg_replace('/\s*-\s*/', '-', trim($m));
}

function formatTimeLabel($val) {
    if (is_numeric($val)) {
        $floatVal = (float)$val;
        $totalMins = round($floatVal * 1440);
        $h = floor($totalMins / 60) % 24;
        $m = $totalMins % 60;
        return sprintf('%02d:%02d', $h, $m);
    }
    $str = trim($val);
    if (preg_match('/^(\d{1,2})[:\.](\d{2})(?:[:\.]\d{2})?\s*(AM|PM)?$/i', $str, $m)) {
        $h = (int)$m[1];
        $min = (int)$m[2];
        $ampm = strtoupper($m[3] ?? '');
        if ($ampm === 'PM' && $h < 12) $h += 12;
        if ($ampm === 'AM' && $h === 12) $h = 0;
        return sprintf('%02d:%02d', $h, $min);
    }
    return $str;
}

function parseToleranceSpec($specStr) {
    if (preg_match('/^([\d\.,]+)\s*[\±\+-]\s*([\d\.,]+)$/u', trim($specStr), $m)) {
        $target = (float)str_replace(',', '.', $m[1]);
        $tol = (float)str_replace(',', '.', $m[2]);
        return ['lsl' => $target - $tol, 'target' => $target, 'usl' => $target + $tol];
    }
    return ['lsl' => null, 'target' => null, 'usl' => null];
}

// Parse top table specs from sheet 1
$sh1 = $sheets[0];
$targetFile = 'xl/' . $targetMap[$sh1['rId']];
$sheetXml = $zip->getFromName($targetFile);
$sXml = simplexml_load_string($sheetXml);

$grid1 = [];
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
        $grid1[$rNum][$colLetter] = trim($val);
    }
}

$specsMap = [];
for ($r = 5; $r <= 20; $r++) {
    if (!isset($grid1[$r])) continue;
    $rawModel = $grid1[$r]['C'] ?? '';
    if ($rawModel === '' || strcasecmp($rawModel, 'Model') === 0) continue;

    $normM = normalizeModelName($rawModel);

    $specsMap[$normM] = [
        'A' => $grid1[$r]['D'] ?? '',
        'B / R' => $grid1[$r]['E'] ?? '',
        'B / F' => $grid1[$r]['F'] ?? '',
        'C' => $grid1[$r]['G'] ?? '',
        'D' => $grid1[$r]['H'] ?? '',
        'Bending' => 'OK'
    ];
}

// Parse daily data from all sheets
$checkpointCols = [
    'D' => 'A',
    'E' => 'B / R',
    'F' => 'B / F',
    'G' => 'C',
    'H' => 'D',
    'I' => 'Bending'
];

$modelDailyData = [];

foreach ($sheets as $sh) {
    $shName = trim($sh['name']);
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

    $dayNum = null;
    if (is_numeric($shName)) {
        $dayNum = (int)$shName;
    } else {
        $tglStr = $grid[23]['G'] ?? '';
        if (preg_match('/(\d{1,2})[\.\/]\d{1,2}[\.\/]\d{4}/', $tglStr, $m)) {
            $dayNum = (int)$m[1];
        }
    }

    if ($dayNum === null || $dayNum < 1 || $dayNum > 31) continue;

    $dateStr = sprintf('%s-%02d', $target_month, $dayNum);

    for ($r = 26; $r <= 100; $r++) {
        if (!isset($grid[$r])) continue;
        $rawModel = $grid[$r]['C'] ?? '';
        $timeFrac = $grid[$r]['B'] ?? '';

        if ($rawModel === '' || $timeFrac === '' || strcasecmp($rawModel, 'Tolling / Model') === 0 || strcasecmp($rawModel, 'Model') === 0) continue;

        $model = normalizeModelName($rawModel);
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
            } else if ($cpName === 'Bending') {
                $finalVal = (strcasecmp($rawVal, 'ok') === 0 || strcasecmp($rawVal, '1 door') === 0) ? 'OK' : 'NG';
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

$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;
$days_in_month = (int)date('t', strtotime($target_month . '-01'));

$summary = [];

foreach ($modelDailyData as $modelName => $datesData) {
    // Master spec
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

    // Master parameter
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

    // Checkpoints
    $modelSpecs = $specsMap[$modelName] ?? [
        'A' => '', 'B / R' => '', 'B / F' => '', 'C' => '', 'D' => '', 'Bending' => 'OK'
    ];

    $cp_id_map = [];
    $sort_order = 1;

    foreach ($checkpointCols as $col => $cpName) {
        $specStr = $modelSpecs[$cpName] ?? '';
        $isQual = (strcasecmp($cpName, 'Bending') === 0 || preg_match('/1\s*door/i', $specStr) || strcasecmp($specStr, 'OK') === 0);
        $cpType = $isQual ? 'Qualitative' : 'Quantitative';

        $tol = parseToleranceSpec($specStr);

        $stmt_ins_cp = $conn->prepare("
            INSERT INTO dtc_checkpoints (parameter_id, checkpoint_name, checkpoint_type, spec_value, lsl, usl, target_value, sort_order)
            VALUES (:pid, :name, :type, :spec, :lsl, :usl, :tgt, :sort)
        ");
        $stmt_ins_cp->execute([
            ':pid' => $parameter_id,
            ':name' => $cpName,
            ':type' => $cpType,
            ':spec' => $specStr,
            ':lsl' => $tol['lsl'],
            ':usl' => $tol['usl'],
            ':tgt' => $tol['target'],
            ':sort' => $sort_order++
        ]);
        $cp_id_map[$cpName] = $conn->lastInsertId();
    }

    // Sessions & Measurements (All 31 days CLOSED = 1)
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

        if (isset($datesData[$dateStr])) {
            $timeSlotMap = [];
            foreach ($datesData[$dateStr] as $m) {
                $lbl = $m['label'];
                if (!isset($timeSlotMap[$lbl])) $timeSlotMap[$lbl] = [];
                $timeSlotMap[$lbl][] = $m;
            }

            $seq = 1;
            foreach ($timeSlotMap as $lbl => $mList) {
                foreach ($mList as $m) {
                    $cpName = $m['cpName'];
                    if (!isset($cp_id_map[$cpName])) continue;

                    $stmt_ins_m->execute([
                        ':sid' => $session_id,
                        ':cpid' => $cp_id_map[$cpName],
                        ':seq' => $seq,
                        ':label' => $lbl,
                        ':val' => $m['val'],
                        ':uid' => $operator_id
                    ]);
                    $total_measurements++;
                }
                $seq++;
            }
        }
    }

    $summary[$modelName] = [
        'parameter_id' => $parameter_id,
        'checkpoints' => count($cp_id_map),
        'active_days' => count($datesData),
        'measurements' => $total_measurements
    ];
}

echo "H PRESS OUTDOOR REF02 IMPORT SUMMARY ($target_month):\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
?>

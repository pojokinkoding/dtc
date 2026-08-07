<?php
// Generic Importer for Cobra Check Sheet Cycle Excel Files (F/Proof)
require_once __DIR__ . '/../config/config.php';

$file_name = $argv[1] ?? '202602 Check Sheet Check Sheet Cycle COBRA REF01.xlsx';
$target_month = $argv[2] ?? '2026-02';
$line_name = $argv[3] ?? 'REF 01';
$data_type = 'Time Check';

echo "=== STARTING CLEAN RE-IMPORT OF COBRA F/PROOF ===\n";
echo "File: $file_name\n";
echo "Target Month: $target_month\n";
echo "Line: $line_name\n";
echo "Data Type: $data_type\n\n";

$file = __DIR__ . '/../' . $file_name;
if (!file_exists($file)) {
    die("Error: File '$file_name' not found at path: $file\n");
}

$conn = getDBConnection();

// 1. STEP 1: CLEAN PREVIOUS DATA FOR COBRA / CLAMPING / CHARGING FOR TARGET MONTH
echo "--- Step 1: Cleaning previous Cobra data for target month $target_month ---\n";
$stmt_get_params = $conn->prepare("
    SELECT parameter_id FROM dtc_master_parameters 
    WHERE target_month = :tm AND line_name = :ln AND section_name IN ('Clamping', 'Charging') AND data_type = :dt
");
$stmt_get_params->execute([':tm' => $target_month, ':ln' => $line_name, ':dt' => $data_type]);
$old_params = $stmt_get_params->fetchAll(PDO::FETCH_COLUMN);

if (!empty($old_params)) {
    $in_params = implode(',', array_map('intval', $old_params));
    echo "Found old parameter IDs to delete for month $target_month: $in_params\n";
    $conn->exec("DELETE FROM dtc_measurements WHERE session_id IN (SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params))");
    $conn->exec("DELETE FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params)");
    $conn->exec("DELETE FROM dtc_checkpoints WHERE parameter_id IN ($in_params)");
    $conn->exec("DELETE FROM dtc_master_parameters WHERE parameter_id IN ($in_params)");
}

$conn->exec("DELETE FROM dtc_running_models WHERE target_month = '$target_month' AND line_name = '$line_name' AND section_name IN ('Clamping', 'Charging')");
echo "Previous data for $target_month cleaned successfully.\n\n";

// 2. STEP 2: OPEN EXCEL FILE
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

$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;

function parseSpecValues($specStr) {
    $lsl = null;
    $usl = null;
    $target = null;
    
    if (preg_match('/^\s*([\d\.]+)\s*-\s*([\d\.]+)/', $specStr, $m)) {
        $lsl = (float)$m[1];
        $usl = (float)$m[2];
        $target = ($lsl + $usl) / 2.0;
    } else if (preg_match('/^\s*([\d\.]+)/', $specStr, $m)) {
        $target = (float)$m[1];
    }

    return ['lsl' => $lsl, 'usl' => $usl, 'target' => $target];
}

$summary = [];

foreach ($sheets as $sh) {
    $sheetName = trim($sh['name']);
    $targetFile = 'xl/' . $targetMap[$sh['rId']];
    $sheetXml = $zip->getFromName($targetFile);
    if (!$sheetXml) continue;
    $sXml = simplexml_load_string($sheetXml);

    // Build grid map [row][colLetter]
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

    // Determine Section Name from Cell B6 (e.g., "Section : Clamping" or "Section :   Charging")
    $section_name = "Cycle";
    if (isset($grid[6]['B']) && preg_match('/Section\s*:\s*(.+)/i', $grid[6]['B'], $pm)) {
        $section_name = trim($pm[1]);
    }

    // Determine Process Name / Item Check Name / Model Name
    $processName = $sheetName;
    $modelName = $sheetName;
    $itemCheckName = $sheetName;

    // Detect Spec Column from Row 8 header
    $specCol = 'D';
    if (isset($grid[8]['E']) && preg_match('/spec/i', $grid[8]['E'])) {
        $specCol = 'E';
    } else if (isset($grid[8]['D']) && preg_match('/spec/i', $grid[8]['D'])) {
        $specCol = 'D';
    }

    // Date row (row 9)
    $dateRow = 9;
    $dateCols = []; // colLetter => dayNum
    if (isset($grid[$dateRow])) {
        foreach ($grid[$dateRow] as $col => $val) {
            if (is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
                $dateCols[$col] = (int)$val;
            }
        }
    }

    if (empty($dateCols)) {
        echo "Sheet '$sheetName' has no date columns. Skipping.\n";
        continue;
    }

    echo "--- Processing Sheet: '$sheetName' (Section: '$section_name', SpecCol: '$specCol', Days: " . count($dateCols) . ") ---\n";

    // 1. Find or create master spec entry in dtc_master_dtc_specs
    $stmt_spec = $conn->prepare("
        SELECT spec_id FROM dtc_master_dtc_specs 
        WHERE line_name = :line AND section_name = :sec AND process_name = :proc AND item_check_name = :item AND data_type = :dtype
    ");
    $stmt_spec->execute([
        ':line' => $line_name,
        ':sec' => $section_name,
        ':proc' => $processName,
        ':item' => $itemCheckName,
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
            ':item' => $itemCheckName,
            ':dtype' => $data_type,
            ':line' => $line_name,
            ':sec' => $section_name,
            ':proc' => $processName
        ]);
        $spec_id = $conn->lastInsertId();
    }

    // 2. Create master parameter entry in dtc_master_parameters for target_month
    $stmt_ins_p = $conn->prepare("
        INSERT INTO dtc_master_parameters (spec_id, target_month, item_check_name, data_type, line_name, section_name, process_name, measuring_item)
        VALUES (:sid, :month, :item, :dtype, :line, :sec, :proc, 'Qualitative')
    ");
    $stmt_ins_p->execute([
        ':sid' => $spec_id,
        ':month' => $target_month,
        ':item' => $itemCheckName,
        ':dtype' => $data_type,
        ':line' => $line_name,
        ':sec' => $section_name,
        ':proc' => $processName
    ]);
    $parameter_id = $conn->lastInsertId();

    // Register running model
    $stmt_rm = $conn->prepare("INSERT IGNORE INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active) VALUES (:tm, :ln, :sn, :mn, 1)");
    $stmt_rm->execute([':tm' => $target_month, ':ln' => $line_name, ':sn' => $section_name, ':mn' => $modelName]);

    // 3. Scan Checkpoints
    $currentCPName = '';
    $currentCPSpec = '';
    $checkpoints_def = [];

    for ($r = 10; $r <= 33; $r++) {
        if (!isset($grid[$r])) continue;
        $no = $grid[$r]['B'] ?? '';
        $cpName = $grid[$r]['C'] ?? '';
        $specVal = $grid[$r][$specCol] ?? '';
        $timeLabel = $grid[$r]['F'] ?? '';

        if (preg_match('/Paraf/i', $no) || preg_match('/Paraf/i', $cpName)) continue;

        if ($cpName !== '' && $no !== '' && is_numeric($no)) {
            $currentCPName = trim($cpName);
            $currentCPSpec = trim($specVal);
        }

        if ($currentCPName !== '' && $timeLabel !== '') {
            if (!isset($checkpoints_def[$currentCPName])) {
                $checkpoints_def[$currentCPName] = [
                    'spec' => $currentCPSpec,
                    'rows' => []
                ];
            }
            $checkpoints_def[$currentCPName]['rows'][] = [
                'row' => $r,
                'label' => trim($timeLabel)
            ];
        }
    }

    $cp_map = [];
    $sort_order = 1;

    foreach ($checkpoints_def as $cpName => $cpInfo) {
        $sampleVals = [];
        foreach ($cpInfo['rows'] as $rInfo) {
            $rNum = $rInfo['row'];
            foreach ($dateCols as $col => $dayNum) {
                $v = strtoupper($grid[$rNum][$col] ?? '');
                if ($v !== '' && $v !== 'X' && $v !== '-') {
                    $sampleVals[] = $v;
                }
            }
        }

        $numCount = 0;
        $totalCount = count($sampleVals);
        
        foreach ($sampleVals as $sv) {
            if (is_numeric($sv)) {
                $numCount++;
            }
        }

        if ($totalCount > 0 && ($numCount / $totalCount) >= 0.5) {
            $cpType = 'Quantitative';
        } else {
            $cpType = 'Qualitative';
        }

        $parsedSpec = parseSpecValues($cpInfo['spec']);

        $stmt_ins_cp = $conn->prepare("
            INSERT INTO dtc_checkpoints (parameter_id, checkpoint_name, checkpoint_type, spec_value, lsl, usl, target_value, sort_order)
            VALUES (:pid, :name, :ctype, :spec, :lsl, :usl, :target, :sort)
        ");
        $stmt_ins_cp->execute([
            ':pid' => $parameter_id,
            ':name' => $cpName,
            ':ctype' => $cpType,
            ':spec' => $cpInfo['spec'],
            ':lsl' => $parsedSpec['lsl'],
            ':usl' => $parsedSpec['usl'],
            ':target' => $parsedSpec['target'],
            ':sort' => $sort_order++
        ]);
        $cp_id = $conn->lastInsertId();

        $cp_map[$cpName] = [
            'checkpoint_id' => $cp_id,
            'checkpoint_type' => $cpType,
            'rows' => $cpInfo['rows']
        ];
        
        echo "  Checkpoint: '$cpName' | Type: $cpType | Spec: '{$cpInfo['spec']}'\n";
    }

    // 4. Map Measurements per Day
    $total_measurements = 0;

    foreach ($dateCols as $col => $dayNum) {
        $dateStr = sprintf('%s-%02d', $target_month, $dayNum);
        
        $stmt_sess = $conn->prepare("SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id = :pid AND inspection_date = :idate");
        $stmt_sess->execute([':pid' => $parameter_id, ':idate' => $dateStr]);
        $sess_id = $stmt_sess->fetchColumn();

        if (!$sess_id) {
            $stmt_ins_s = $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, is_closed) VALUES (:pid, :idate, :uid, 1)");
            $stmt_ins_s->execute([':pid' => $parameter_id, ':idate' => $dateStr, ':uid' => $operator_id]);
            $sess_id = $conn->lastInsertId();
        }

        $stmt_ins_m = $conn->prepare("
            INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by)
            VALUES (:sid, :cpid, :seq, :label, :val, :uid)
        ");

        $seq = 1;
        foreach ($cp_map as $cpName => $cpData) {
            $cpid = $cpData['checkpoint_id'];
            foreach ($cpData['rows'] as $rInfo) {
                $rNum = $rInfo['row'];
                $label = $rInfo['label'];

                $val = strtoupper($grid[$rNum][$col] ?? '');
                if ($val === '' || $val === 'X' || $val === '-') continue;

                $evalVal = ($val === 'V' || $val === '√' || $val === 'GOOD') ? 'OK' : $val;

                $stmt_ins_m->execute([
                    ':sid' => $sess_id,
                    ':cpid' => $cpid,
                    ':seq' => $seq++,
                    ':label' => $label,
                    ':val' => $evalVal,
                    ':uid' => $operator_id
                ]);
                $total_measurements++;
            }
        }
    }

    $summary[$sheetName] = [
        'status' => 'IMPORTED',
        'section_name' => $section_name,
        'checkpoints_created' => count($cp_map),
        'measurements' => $total_measurements
    ];
    echo "Finished Sheet '$sheetName': " . count($cp_map) . " checkpoints, $total_measurements measurements.\n\n";
}

echo "====================================================\n";
echo "RE-IMPORT COMPLETE SUMMARY ($target_month):\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
?>

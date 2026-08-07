<?php
// Importer for F/Proof (Foolproof) Excel Files
require_once __DIR__ . '/../config/config.php';

$file_name = $argv[1] ?? '202601 Foolproof Hinge Lower REF01.xlsx';
$target_month = $argv[2] ?? '2026-01';
$line_name = $argv[3] ?? 'REF 01';
$section_name = $argv[4] ?? 'Pre Case';
$data_type = $argv[5] ?? 'F/Proof';

echo "=== STARTING F/PROOF IMPORT ===\n";
echo "File: $file_name\n";
echo "Target Month: $target_month\n";
echo "Line: $line_name\n";
echo "Section: $section_name\n";
echo "Data Type: $data_type\n\n";

$file = __DIR__ . '/../' . $file_name;
if (!file_exists($file)) {
    die("Error: File '$file_name' not found.\n");
}

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

$conn = getDBConnection();
$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;

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

    // Find Header / Title Info
    $processName = "Screw Hinge Lower";
    $modelName = "Foolproof Hinge Lower";
    $itemCheckName = "Foolproof Hinge Lower";

    if (isset($grid[2]['B']) && preg_match('/F\/Proof Process\s*:\s*(.+)/i', $grid[2]['B'], $pm)) {
        $processName = trim($pm[1]);
        $itemCheckName = $processName;
    }

    // Find Date Row & Date Columns
    $dateRow = 4;
    $dateCols = []; // colLetter => dayNum
    if (isset($grid[4])) {
        foreach ($grid[4] as $col => $val) {
            if (is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
                $dateCols[$col] = (int)$val;
            }
        }
    }

    if (empty($dateCols)) {
        echo "Sheet '$sheetName' has no date columns. Skipping.\n";
        continue;
    }

    echo "Processing Sheet: '$sheetName' (Process: '$processName', Days found: " . count($dateCols) . ")\n";

    // 1. Ensure master spec entry in dtc_master_dtc_specs
    $stmt_spec = $conn->prepare("
        SELECT spec_id FROM dtc_master_dtc_specs 
        WHERE line_name = :line AND section_name = :sec AND process_name = :proc AND model_name = :model AND item_check_name = :item
    ");
    $stmt_spec->execute([
        ':line' => $line_name,
        ':sec' => $section_name,
        ':proc' => $processName,
        ':model' => $modelName,
        ':item' => $itemCheckName
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

    // 2. Ensure master parameter entry in dtc_master_parameters
    $stmt_param = $conn->prepare("SELECT parameter_id FROM dtc_master_parameters WHERE spec_id = :sid AND target_month = :month");
    $stmt_param->execute([':sid' => $spec_id, ':month' => $target_month]);
    $parameter_id = $stmt_param->fetchColumn();

    if (!$parameter_id) {
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
    }

    // Clean previous session/measurement data for this parameter_id to prevent duplicates
    $stmt_del_meas = $conn->prepare("DELETE FROM dtc_measurements WHERE session_id IN (SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id = :pid)");
    $stmt_del_meas->execute([':pid' => $parameter_id]);
    $stmt_del_sess = $conn->prepare("DELETE FROM dtc_inspection_sessions WHERE parameter_id = :pid");
    $stmt_del_sess->execute([':pid' => $parameter_id]);

    // Register running model
    $stmt_rm = $conn->prepare("INSERT IGNORE INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active) VALUES (:tm, :ln, :sn, :mn, 1)");
    $stmt_rm->execute([':tm' => $target_month, ':ln' => $line_name, ':sn' => $section_name, ':mn' => $modelName]);

    // 3. Scan Checkpoints (Rows 5 to 15 where Col C or D contains checkpoint name)
    $checkpoints = [];
    for ($r = 5; $r <= 15; $r++) {
        $cpName = $grid[$r]['D'] ?? ($grid[$r]['C'] ?? '');
        if (trim($cpName) === '' || preg_match('/Beri|Jika|Operator|Name/i', $cpName)) continue;

        $cpTime = $grid[$r]['E'] ?? '07:30';
        if (strlen($cpTime) === 5 && strpos($cpTime, ':') !== false) {
            // Valid time format
        } else {
            $cpTime = '07:30';
        }

        $checkpoints[] = [
            'row' => $r,
            'name' => trim($cpName),
            'time' => $cpTime
        ];
    }

    $cp_map = [];
    $sort_order = 1;
    foreach ($checkpoints as $cpItem) {
        $cpName = $cpItem['name'];
        $stmt_cp = $conn->prepare("SELECT checkpoint_id FROM dtc_checkpoints WHERE parameter_id = :pid AND checkpoint_name = :name");
        $stmt_cp->execute([':pid' => $parameter_id, ':name' => $cpName]);
        $cp_id = $stmt_cp->fetchColumn();

        if (!$cp_id) {
            $stmt_ins_cp = $conn->prepare("
                INSERT INTO dtc_checkpoints (parameter_id, checkpoint_name, checkpoint_type, sort_order, target_value)
                VALUES (:pid, :name, 'Qualitative', :sort, 'OK')
            ");
            $stmt_ins_cp->execute([
                ':pid' => $parameter_id,
                ':name' => $cpName,
                ':sort' => $sort_order++
            ]);
            $cp_id = $conn->lastInsertId();
        }
        $cp_map[$cpItem['row']] = [
            'checkpoint_id' => $cp_id,
            'name' => $cpName,
            'time' => $cpItem['time']
        ];
    }

    // 4. Map Measurements per Day
    $total_measurements = 0;

    foreach ($dateCols as $col => $dayNum) {
        $dateStr = sprintf('%s-%02d', $target_month, $dayNum);
        
        // Create inspection session for the day if not exists
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
            VALUES (:sid, :cpid, 1, :label, :val, :uid)
        ");

        foreach ($cp_map as $rNum => $cpData) {
            $val = strtoupper($grid[$rNum][$col] ?? '');
            if ($val === '' || $val === 'X' || $val === '-') continue;

            $evalVal = ($val === 'OK' || $val === 'V' || $val === '√' || $val === '1' || $val === 'GOOD') ? 'OK' : $val;

            $stmt_ins_m->execute([
                ':sid' => $sess_id,
                ':cpid' => $cpData['checkpoint_id'],
                ':label' => $cpData['time'],
                ':val' => $evalVal,
                ':uid' => $operator_id
            ]);
            $total_measurements++;
        }
    }

    $summary[$sheetName] = [
        'status' => 'IMPORTED',
        'checkpoints_created' => count($cp_map),
        'measurements' => $total_measurements
    ];
    echo "Finished Sheet '$sheetName': " . count($cp_map) . " checkpoints, $total_measurements measurements imported.\n\n";
}

echo "====================================================\n";
echo "F/PROOF IMPORT COMPLETE SUMMARY:\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
?>

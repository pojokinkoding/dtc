<?php
require_once __DIR__ . '/../config/config.php';

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

$allFiles = [
    [
        'file' => '202602 Check Sheet AutoVinyl Cutting REF02.xlsx',
        'month' => '2026-02'
    ],
    [
        'file' => '202603 CHECK SHEET AUTO VINYL CUTTING REF02.xlsx',
        'month' => '2026-03'
    ],
    [
        'file' => '202604  CHECK SHEET AUTO CUTTING VINYL REF02.xlsx',
        'month' => '2026-04'
    ],
    [
        'file' => '202605  AutoVinyl Cutting Checksheet NR1_NR2_New.xlsx',
        'month' => '2026-05'
    ],
    [
        'file' => '202606  CHECK SHEET AutoVinyl Cutting  NR1_NR2.xlsx',
        'month' => '2026-06'
    ]
];

echo "======================================================================\n";
echo "=== BATCH IMPORT AUTOVINYL CUTTING F/PROOF (2026-02 TO 2026-06) ===\n";
echo "======================================================================\n\n";

$conn = getDBConnection();

foreach ($allFiles as $item) {
    $fName = $item['file'];
    $mStr = $item['month'];

    echo ">>> IMPORTING MONTH $mStr (File: $fName) <<<\n";
    
    $file_path = __DIR__ . '/../' . $fName;
    if (!file_exists($file_path)) {
        echo "Error: file not found: $file_path\n";
        continue;
    }

    $line_name = 'REF 02';
    $section_name = 'Cutting Vinyl';
    $data_type = 'F/Proof';
    $process_name = 'Cutting Vinyl';
    $item_check_name = 'Cutting Vinyl';

    // Clean previous
    $stmt_get_params = $conn->prepare("
        SELECT parameter_id FROM dtc_master_parameters 
        WHERE target_month = :tm AND line_name = :ln AND section_name = :sec AND data_type = :dt
    ");
    $stmt_get_params->execute([':tm' => $mStr, ':ln' => $line_name, ':sec' => $section_name, ':dt' => $data_type]);
    $old_params = $stmt_get_params->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($old_params)) {
        $in_params = implode(',', array_map('intval', $old_params));
        $conn->exec("DELETE FROM dtc_measurements WHERE session_id IN (SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params))");
        $conn->exec("DELETE FROM dtc_inspection_sessions WHERE parameter_id IN ($in_params)");
        $conn->exec("DELETE FROM dtc_checkpoints WHERE parameter_id IN ($in_params)");
        $conn->exec("DELETE FROM dtc_master_parameters WHERE parameter_id IN ($in_params)");
    }

    // Zip excel read
    $zip = new ZipArchive();
    if ($zip->open($file_path) !== TRUE) continue;

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

    $operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;
    $days_in_month = (int)date('t', strtotime($mStr . '-01'));

    foreach ($sheets as $sh) {
        $modelName = trim($sh['name']);
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

        $dateCols = [];
        foreach ($grid[12] ?? [] as $col => $val) {
            if (is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
                $dateCols[$col] = (int)$val;
            }
        }
        foreach ($grid[13] ?? [] as $col => $val) {
            if (!isset($dateCols[$col]) && is_numeric($val) && (int)$val >= 1 && (int)$val <= 31) {
                $dateCols[$col] = (int)$val;
            }
        }

        $modelDailyData = [];
        $checkpointsSpecMap = [];
        $currentCp = null;

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

                    $dateStr = sprintf('%s-%02d', $mStr, $dayNum);
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

        // Master Spec
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
            ':month' => $mStr,
            ':model' => $modelName,
            ':item' => $item_check_name,
            ':dtype' => $data_type,
            ':line' => $line_name,
            ':sec' => $section_name,
            ':proc' => $process_name
        ]);
        $parameter_id = $conn->lastInsertId();

        // Running model
        $stmt_rm = $conn->prepare("INSERT IGNORE INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active) VALUES (:tm, :ln, :sn, :mn, 1)");
        $stmt_rm->execute([':tm' => $mStr, ':ln' => $line_name, ':sn' => $section_name, ':mn' => $modelName]);

        // Checkpoints
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
        }

        // Sessions & Measurements (All days CLOSED = 1)
        $total_measurements = 0;
        $stmt_ins_s = $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, is_closed) VALUES (:pid, :idate, :uid, 1)");
        $stmt_ins_m = $conn->prepare("
            INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by)
            VALUES (:sid, :cpid, :seq, :label, :val, :uid)
        ");

        for ($d = 1; $d <= $days_in_month; $d++) {
            $dateStr = sprintf('%s-%02d', $mStr, $d);

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
        echo "  Finished Month $mStr Machine '$modelName': $days_in_month days closed, $total_measurements measurements.\n";
    }
    echo "-------------------------------------------------------\n\n";
}

echo "=======================================================\n";
echo "=== VERIFICATION OF ALL AUTOVINYL MONTHS (2026-01 TO 2026-06) ===\n";
echo "=======================================================\n";

$stmt_verify = $conn->query("
    SELECT p.target_month,
           COUNT(DISTINCT p.parameter_id) as total_machines, 
           SUM(CASE WHEN p.model_name IS NULL OR p.model_name = '' THEN 1 ELSE 0 END) as null_model_cnt,
           COUNT(s.session_id) as total_sessions,
           SUM(CASE WHEN s.is_closed = 1 THEN 1 ELSE 0 END) as closed_sessions,
           (SELECT COUNT(*) FROM dtc_measurements m WHERE m.session_id IN (SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id IN (SELECT parameter_id FROM dtc_master_parameters WHERE target_month = p.target_month AND section_name = 'Cutting Vinyl' AND line_name = 'REF 02'))) as total_measurements
    FROM dtc_master_parameters p
    JOIN dtc_inspection_sessions s ON p.parameter_id = s.parameter_id
    WHERE p.section_name = 'Cutting Vinyl' AND p.line_name = 'REF 02'
    GROUP BY p.target_month
    ORDER BY p.target_month ASC
");
echo json_encode($stmt_verify->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

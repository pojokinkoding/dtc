<?php
// Importer for Time Check Excel file for January 2026
require_once __DIR__ . '/../config/config.php';

echo "Starting Time Check Import for January 2026 (timecheck_v_forming_male_A_ref01_jan.xlsx)...\n";

$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
if (!file_exists($file)) {
    die("Error: File not found.\n");
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

$conn = getDBConnection();
$target_month = '2026-01';
$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;
$summary_results = [];

// Base identifiers
$line_name = 'REF 01';
$section_name = 'V Forming';
$data_type = 'Time Check';

// Delete previous test sessions/measurements from this file if needed
// Or let it cleanly overwrite because we use INSERT IGNORE/checks
// We will just let it run

for ($idx = 0; $idx < count($sheets); $idx++) {
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
            if (strlen($colLetter) == 1 || (strlen($colLetter) == 2 && $colLetter <= 'AJ')) {
                $rowCells[$colLetter] = $val;
            }
        }
        $rows[$rNum] = $rowCells;
    }

    // Process Name / Item Check Name
    $process_name = $sheetName;
    $item_check_name = $sheetName;
    $model_name = $sheetName; // Use sheet name for model name

    // Detect Date Row (usually around row 7 or 8)
    $dateRow = null;
    for ($r = 5; $r <= 15; $r++) {
        if (isset($rows[$r])) {
            $numeric_count = 0;
            foreach ($rows[$r] as $col => $val) {
                if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                    $numeric_count++;
                }
            }
            if ($numeric_count >= 5) {
                $dateRow = $r;
                break;
            }
        }
    }

    if (!$dateRow) {
        echo "\n----------------------------------------------------\n";
        echo "Skipping non-data sheet $sheetNum: '$sheetName'\n";
        $summary_results[$sheetName] = ['status' => 'SKIPPED_NO_DATA'];
        continue;
    }

    // Map columns to days
    $valid_columns = [];
    foreach ($rows[$dateRow] as $col => $val) {
        if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
            $valid_columns[$col] = intval($val);
        }
    }

    echo "\n----------------------------------------------------\n";
    echo "Processing Sheet $sheetNum: '$sheetName' (Date Row: $dateRow)\n";

    // 1. Find or Insert Master Spec
    $sql_spec_find = "SELECT spec_id FROM dtc_master_dtc_specs 
                      WHERE UPPER(model_name) = UPPER(:model) AND UPPER(item_check_name) = UPPER(:item) 
                      AND UPPER(line_name) = UPPER(:line) AND UPPER(section_name) = UPPER(:sec) AND UPPER(data_type) = UPPER(:dt)";
    $stmt_spec_find = $conn->prepare($sql_spec_find);
    $stmt_spec_find->execute([':model' => $model_name, ':item' => $item_check_name, ':line' => $line_name, ':sec' => $section_name, ':dt' => $data_type]);
    $spec_id = $stmt_spec_find->fetchColumn();

    if (!$spec_id) {
        $sql_ins_spec = "INSERT INTO dtc_master_dtc_specs (model_name, item_check_name, data_type, line_name, section_name, process_name, measuring_item, lsl, usl) 
                         VALUES (:model_name, :item_check_name, :data_type, :line_name, :section_name, :process_name, 'Qualitative', 0, 0)";
        $stmt_ins_spec = $conn->prepare($sql_ins_spec);
        $stmt_ins_spec->execute([':model_name' => $model_name, ':item_check_name' => $item_check_name, ':data_type' => $data_type, ':line_name' => $line_name, ':section_name' => $section_name, ':process_name' => $process_name]);
        $spec_id = $conn->lastInsertId();
    }

    // 2. Find or Insert Parameter
    $sql_param_find = "SELECT parameter_id FROM dtc_master_parameters WHERE spec_id = :spec_id AND target_month = :target_month";
    $stmt_param_find = $conn->prepare($sql_param_find);
    $stmt_param_find->execute([':spec_id' => $spec_id, ':target_month' => $target_month]);
    $parameter_id = $stmt_param_find->fetchColumn();

    if (!$parameter_id) {
        $sql_ins_param = "INSERT INTO dtc_master_parameters (spec_id, target_month, item_check_name, data_type, line_name, section_name, process_name, measuring_item) 
                          VALUES (:spec_id, :target_month, :item_check_name, :data_type, :line_name, :section_name, :process_name, 'Qualitative')";
        $stmt_ins_param = $conn->prepare($sql_ins_param);
        $stmt_ins_param->execute([':spec_id' => $spec_id, ':target_month' => $target_month, ':item_check_name' => $item_check_name, ':data_type' => $data_type, ':line_name' => $line_name, ':section_name' => $section_name, ':process_name' => $process_name]);
        $parameter_id = $conn->lastInsertId();
    }

    // Pre-create or Fetch sessions for the month
    $session_map = [];
    foreach ($valid_columns as $col => $day) {
        $date = sprintf('2026-01-%02d', $day);
        $sql_sess = "SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id = :pid AND inspection_date = :idate";
        $stmt_sess = $conn->prepare($sql_sess);
        $stmt_sess->execute([':pid' => $parameter_id, ':idate' => $date]);
        $sess_id = $stmt_sess->fetchColumn();
        if (!$sess_id) {
            $stmt_ins_s = $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id) VALUES (:pid, :idate, :uid)");
            $stmt_ins_s->execute([':pid' => $parameter_id, ':idate' => $date, ':uid' => $operator_id]);
            $sess_id = $conn->lastInsertId();
        }
        $session_map[$col] = $sess_id;
        
        // delete existing measurements for this param/session so we do clean import
        $stmt_del = $conn->prepare("
            DELETE FROM dtc_measurements 
            WHERE session_id = :sid AND checkpoint_id IN (SELECT checkpoint_id FROM dtc_checkpoints WHERE parameter_id = :pid)
        ");
        $stmt_del->execute([':sid' => $sess_id, ':pid' => $parameter_id]);
    }

    // 3. Ensure running model entry
    $stmt_rm = $conn->prepare("INSERT IGNORE INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active) VALUES (:tm, :ln, :sn, :mn, 1)");
    $stmt_rm->execute([':tm' => $target_month, ':ln' => $line_name, ':sn' => $section_name, ':mn' => $model_name]);

    // Track state during row iteration
    $current_checkpoint_id = null;
    $current_checkpoint_name = null;
    $cp_sort_order = 0;
    
    $measurements_created = 0;
    $checkpoints_created = 0;

    $maxRow = max(array_keys($rows));
    for ($r = $dateRow + 1; $r <= $maxRow; $r++) {
        if (!isset($rows[$r])) continue;
        
        $colB = isset($rows[$r]['B']) ? trim((string)$rows[$r]['B']) : '';
        $colC = isset($rows[$r]['C']) ? trim((string)$rows[$r]['C']) : '';
        $colD = isset($rows[$r]['D']) ? trim((string)$rows[$r]['D']) : '';
        $colE = isset($rows[$r]['E']) ? trim((string)$rows[$r]['E']) : '';

        // Check if this row defines a new Checkpoint block (B has number, C has text)
        // Note: Sometimes C might be merged, so we check if C is non-empty string and not time.
        if ($colC !== '' && strpos($colC, ':') === false && !preg_match('/^\d{1,2}:\d{2}$/', str_replace("'", ":", $colC))) {
            $current_checkpoint_name = $colC;
            
            // Parse Spec Column D
            $usl = null;
            $lsl = null;
            $checkpoint_type = 'Qualitative';
            
            if (preg_match('/USL\s*:\s*([-˗]?\s*[\d\.]+)/i', $colD, $m)) $usl = (float)str_replace([' ','˗'], ['','-'], $m[1]);
            if (preg_match('/LSL\s*:\s*([-˗]?\s*[\d\.]+)/i', $colD, $m)) $lsl = (float)str_replace([' ','˗'], ['','-'], $m[1]);
            if (preg_match('/^([-˗]?\s*[\d\.]+)\s*~\s*([-˗]?\s*[\d\.]+)/u', trim($colD), $m)) {
                $lsl = (float)str_replace([' ','˗'], ['','-'], $m[1]);
                $usl = (float)str_replace([' ','˗'], ['','-'], $m[2]);
            }
            // For Vacuum Pressure like: ˗ 76 cm/Hg          ˗60 cm/Hg
            if ($usl === null && $lsl === null && preg_match('/([-˗]?\s*[\d\.]+)\s+[c-zA-Z\/]+\s+([-˗]?\s*[\d\.]+)/u', trim($colD), $m)) {
                $lsl = (float)str_replace([' ','˗'], ['','-'], $m[1]);
                $usl = (float)str_replace([' ','˗'], ['','-'], $m[2]);
            }
            if ($usl !== null || $lsl !== null) {
                $checkpoint_type = 'Quantitative';
            }
            
            // Find or Insert Checkpoint
            $stmtChk = $conn->prepare("SELECT checkpoint_id FROM dtc_checkpoints WHERE parameter_id = :pid AND checkpoint_name = :name");
            $stmtChk->execute([':pid' => $parameter_id, ':name' => $current_checkpoint_name]);
            $current_checkpoint_id = $stmtChk->fetchColumn();
            
            if (!$current_checkpoint_id) {
                $cp_sort_order++;
                $stmtInsCp = $conn->prepare("INSERT INTO dtc_checkpoints (parameter_id, checkpoint_name, checkpoint_type, sort_order, lsl, usl, spec_value) VALUES (:pid, :name, :ctype, :so, :lsl, :usl, :spec)");
                $stmtInsCp->execute([
                    ':pid' => $parameter_id, 
                    ':name' => $current_checkpoint_name, 
                    ':ctype' => $checkpoint_type,
                    ':so' => $cp_sort_order,
                    ':lsl' => $lsl,
                    ':usl' => $usl,
                    ':spec' => empty($colD) ? null : $colD
                ]);
                $current_checkpoint_id = $conn->lastInsertId();
                $checkpoints_created++;
            } else {
                // Update existing checkpoint with parsed type/spec just in case it was imported incorrectly before
                $stmtUpdCp = $conn->prepare("UPDATE dtc_checkpoints SET checkpoint_type = :ctype, lsl = :lsl, usl = :usl, spec_value = :spec WHERE checkpoint_id = :cid");
                $stmtUpdCp->execute([
                    ':ctype' => $checkpoint_type,
                    ':lsl' => $lsl,
                    ':usl' => $usl,
                    ':spec' => empty($colD) ? null : $colD,
                    ':cid' => $current_checkpoint_id
                ]);
            }
        }

        // If we have a checkpoint block active and E has a time value
        if ($current_checkpoint_id && $colE !== '') {
            $time_label = $colE; // e.g. "07:30" or "05'30"
            // fix weird typo in excel "05'30" -> "05:30"
            $time_label = str_replace("'", ":", $time_label);

            if (preg_match('/^\d{1,2}:\d{2}$/', $time_label)) {
                // This is a valid time row, extract values for each day
                foreach ($valid_columns as $col => $day) {
                    $val = isset($rows[$r][$col]) ? trim((string)$rows[$r][$col]) : '';
                    if ($val !== '') {
                        $sess_id = $session_map[$col];
                        
                        $stmt_ins_m = $conn->prepare("
                            INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by) 
                            VALUES (:sid, :cpid, 1, :lbl, :val, :uid)
                        ");
                        $stmt_ins_m->execute([
                            ':sid' => $sess_id,
                            ':cpid' => $current_checkpoint_id,
                            ':lbl' => $time_label,
                            ':val' => $val,
                            ':uid' => $operator_id
                        ]);
                        $measurements_created++;
                    }
                }
            }
        }
    }

    echo "Finished Sheet $sheetNum: $checkpoints_created new checkpoints, $measurements_created measurements imported.\n";
    $summary_results[$sheetName] = [
        'status' => 'IMPORTED',
        'checkpoints_created' => $checkpoints_created,
        'measurements' => $measurements_created
    ];
}

$zip->close();
echo "\n====================================================\n";
echo "TIME CHECK IMPORT COMPLETE SUMMARY:\n";
echo json_encode($summary_results, JSON_PRETTY_PRINT) . "\n";
?>

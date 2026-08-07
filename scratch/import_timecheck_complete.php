<?php
// Complete Importer for Time Check Excel file (All 5 Sheets)
require_once __DIR__ . '/../config/config.php';

echo "Starting Complete Time Check Import for January 2026...\n";

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

function formatTimeFromDecimal($val) {
    if (is_numeric($val) && floatval($val) >= 0 && floatval($val) <= 1.5) {
        $totalMinutes = round(floatval($val) * 24 * 60);
        $hours = floor($totalMinutes / 60) % 24;
        $mins = $totalMinutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }
    return str_replace("'", ":", trim((string)$val));
}

// Function to extract text from drawing XML associated with a sheet
function getDrawingTextsByRow($zip, $targetMap, $rId) {
    $sheetPath = 'xl/' . $targetMap[$rId];
    $sheetXmlStr = $zip->getFromName($sheetPath);
    if (!$sheetXmlStr) return [];
    
    $sheetXml = simplexml_load_string($sheetXmlStr);
    $drawingRelId = null;
    if (isset($sheetXml->drawing)) {
        $drawingRelId = (string)$sheetXml->drawing->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    }
    if (!$drawingRelId) return [];
    
    // Find drawing XML target
    $sheetRelsPath = 'xl/worksheets/_rels/' . basename($sheetPath) . '.rels';
    $sheetRelsXmlStr = $zip->getFromName($sheetRelsPath);
    if (!$sheetRelsXmlStr) return [];
    
    $sheetRels = simplexml_load_string($sheetRelsXmlStr);
    $drawingTarget = null;
    foreach ($sheetRels->Relationship as $rel) {
        if ((string)$rel['Id'] === $drawingRelId) {
            $drawingTarget = (string)$rel['Target'];
            break;
        }
    }
    if (!$drawingTarget) return [];
    
    $drawingPath = 'xl/drawings/' . basename($drawingTarget);
    $drawingXmlStr = $zip->getFromName($drawingPath);
    if (!$drawingXmlStr) return [];
    
    $dXml = simplexml_load_string($drawingXmlStr);
    $dXml->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
    $dXml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
    
    $rowTexts = [];
    foreach ($dXml->children('xdr', true) as $anchor) {
        $fromRow = (int)($anchor->from->row ?? 0) + 1; // 1-indexed row
        $texts = [];
        foreach ($anchor->xpath('.//a:t') as $t) {
            $str = trim((string)$t);
            if ($str !== '' && strcasecmp($str, 'TIME CHECK') !== 0 && strcasecmp($str, 'CHECK SHEET / MANAGEMENT TIME CHECK') !== 0 && strcasecmp($str, 'SIGN') !== 0 && strcasecmp($str, 'Draft by') !== 0) {
                $texts[] = $str;
            }
        }
        if (!empty($texts)) {
            $rowTexts[$fromRow] = implode(" ", $texts);
        }
    }
    return $rowTexts;
}

$conn = getDBConnection();
$target_month = '2026-01';
$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;
$summary_results = [];

$line_name = 'REF 01';
$section_name = 'V Forming Male A';
$data_type = 'Time Check';

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

    // Use sheet name as Model Name per explicit user request
    $model_name = $sheetName;

    $process_name = $sheetName;
    $item_check_name = $sheetName;

    // Detect Date Row (row with numbers 1..31)
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
    echo "Processing Sheet $sheetNum: '$sheetName' (Model: '$model_name', Date Row: $dateRow)\n";

    // 1. Master Spec & Parameter
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

    // Sessions map
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
        
        // Clear previous measurements for clean re-import
        $stmt_del = $conn->prepare("
            DELETE FROM dtc_measurements 
            WHERE session_id = :sid AND checkpoint_id IN (SELECT checkpoint_id FROM dtc_checkpoints WHERE parameter_id = :pid)
        ");
        $stmt_del->execute([':sid' => $sess_id, ':pid' => $parameter_id]);
    }

    $stmt_rm = $conn->prepare("INSERT IGNORE INTO dtc_running_models (target_month, line_name, section_name, model_name, is_active) VALUES (:tm, :ln, :sn, :mn, 1)");
    $stmt_rm->execute([':tm' => $target_month, ':ln' => $line_name, ':sn' => $section_name, ':mn' => $model_name]);

    // Fetch Drawing text map for this sheet
    $drawingTexts = getDrawingTextsByRow($zip, $targetMap, $sheets[$idx]['rId']);

    $current_checkpoint_id = null;
    $current_checkpoint_name = null;
    $cp_sort_order = 0;
    $measurements_created = 0;
    $checkpoints_created = 0;

    $maxRow = max(array_keys($rows));

    // SPECIAL HANDLING FOR DOOR SWITCH SHEET (Sheet 5)
    if (strpos($sheetName, 'Door Switch') !== false) {
        $cp_name = '1. Door Switch Hole';
        $stmtChk = $conn->prepare("SELECT checkpoint_id FROM dtc_checkpoints WHERE parameter_id = :pid AND checkpoint_name = :name");
        $stmtChk->execute([':pid' => $parameter_id, ':name' => $cp_name]);
        $current_checkpoint_id = $stmtChk->fetchColumn();
        if (!$current_checkpoint_id) {
            $cp_sort_order++;
            $stmtInsCp = $conn->prepare("INSERT INTO dtc_checkpoints (parameter_id, checkpoint_name, checkpoint_type, sort_order) VALUES (:pid, :name, 'Qualitative', :so)");
            $stmtInsCp->execute([':pid' => $parameter_id, ':name' => $cp_name, ':so' => $cp_sort_order]);
            $current_checkpoint_id = $conn->lastInsertId();
            $checkpoints_created++;
        }

        for ($r = $dateRow + 1; $r <= $maxRow; $r++) {
            if (!isset($rows[$r])) continue;
            $raw_time = isset($rows[$r]['C']) ? $rows[$r]['C'] : '';
            $cond = isset($rows[$r]['D']) ? trim((string)$rows[$r]['D']) : '';

            if ($raw_time !== '' && strcasecmp($cond, 'OK') === 0) {
                $time_label = formatTimeFromDecimal($raw_time);
                $ng_row = $r + 1;

                foreach ($valid_columns as $col => $day) {
                    $ok_val = isset($rows[$r][$col]) ? trim((string)$rows[$r][$col]) : '';
                    $ng_val = isset($rows[$ng_row][$col]) ? trim((string)$rows[$ng_row][$col]) : '';

                    $final_val = '';
                    if (strcasecmp($ng_val, 'NG') === 0) {
                        $final_val = 'NG';
                    } else if ($ok_val !== '' && $ok_val !== '-') {
                        $final_val = 'OK';
                    }

                    if ($final_val !== '') {
                        $sess_id = $session_map[$col];
                        $stmt_ins_m = $conn->prepare("
                            INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by) 
                            VALUES (:sid, :cpid, 1, :lbl, :val, :uid)
                        ");
                        $stmt_ins_m->execute([':sid' => $sess_id, ':cpid' => $current_checkpoint_id, ':lbl' => $time_label, ':val' => $final_val, ':uid' => $operator_id]);
                        $measurements_created++;
                    }
                }
            }
        }
    } else {
        // STANDARD SHEETS (MC 1, MC 2, Product 1.1, Product 1.2)
        for ($r = $dateRow + 1; $r <= $maxRow; $r++) {
            if (!isset($rows[$r])) continue;

            $colB = isset($rows[$r]['B']) ? trim((string)$rows[$r]['B']) : '';
            $colC = isset($rows[$r]['C']) ? trim((string)$rows[$r]['C']) : '';
            $colD = isset($rows[$r]['D']) ? trim((string)$rows[$r]['D']) : '';
            $colE = isset($rows[$r]['E']) ? trim((string)$rows[$r]['E']) : '';

            // Check if drawing anchor provides checkpoint name near this row (r-2 to r+2)
            $drawing_cp_name = '';
            for ($dr = $r - 2; $dr <= $r + 2; $dr++) {
                if (isset($drawingTexts[$dr])) {
                    $drawing_cp_name = $drawingTexts[$dr];
                    break;
                }
            }

            // Determine if a new Checkpoint block starts
            $detected_name = '';
            if ($colC !== '' && strpos($colC, ':') === false && !preg_match('/^\d{1,2}:\d{2}$/', str_replace("'", ":", $colC))) {
                $detected_name = $colC;
            } else if ($drawing_cp_name !== '') {
                $detected_name = $drawing_cp_name;
            }

            if ($detected_name !== '' && $detected_name !== $current_checkpoint_name) {
                $current_checkpoint_name = $detected_name;

                // Parse Spec
                $spec_str = $colD;
                $usl = null; $lsl = null;
                $checkpoint_type = 'Qualitative';

                if (preg_match('/USL\s*:\s*([-˗]?\s*[\d\.]+)/i', $spec_str, $m)) $usl = (float)str_replace([' ','˗'], ['','-'], $m[1]);
                if (preg_match('/LSL\s*:\s*([-˗]?\s*[\d\.]+)/i', $spec_str, $m)) $lsl = (float)str_replace([' ','˗'], ['','-'], $m[1]);
                if (preg_match('/^([-˗]?\s*[\d\.]+)\s*~\s*([-˗]?\s*[\d\.]+)/u', trim($spec_str), $m)) {
                    $lsl = (float)str_replace([' ','˗'], ['','-'], $m[1]);
                    $usl = (float)str_replace([' ','˗'], ['','-'], $m[2]);
                }
                if ($usl === null && $lsl === null && preg_match('/([-˗]?\s*[\d\.]+)\s+[c-zA-Z\/]+\s+([-˗]?\s*[\d\.]+)/u', trim($spec_str), $m)) {
                    $lsl = (float)str_replace([' ','˗'], ['','-'], $m[1]);
                    $usl = (float)str_replace([' ','˗'], ['','-'], $m[2]);
                }
                if ($usl !== null || $lsl !== null) {
                    $checkpoint_type = 'Quantitative';
                }

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
                        ':spec' => empty($spec_str) ? null : $spec_str
                    ]);
                    $current_checkpoint_id = $conn->lastInsertId();
                    $checkpoints_created++;
                }
            }

            // Determine Time column: Col D or Col E
            $time_val = '';
            if (preg_match('/^\d{1,2}:\d{2}$/', str_replace("'", ":", $colD))) {
                $time_val = str_replace("'", ":", $colD);
            } else if (preg_match('/^\d{1,2}:\d{2}$/', str_replace("'", ":", $colE))) {
                $time_val = str_replace("'", ":", $colE);
            }

            if ($current_checkpoint_id && $time_val !== '') {
                foreach ($valid_columns as $col => $day) {
                    $val = isset($rows[$r][$col]) ? trim((string)$rows[$r][$col]) : '';
                    if ($val !== '') {
                        $sess_id = $session_map[$col];
                        $stmt_ins_m = $conn->prepare("
                            INSERT INTO dtc_measurements (session_id, checkpoint_id, sample_sequence, sample_label, sample_value, created_by) 
                            VALUES (:sid, :cpid, 1, :lbl, :val, :uid)
                        ");
                        $stmt_ins_m->execute([':sid' => $sess_id, ':cpid' => $current_checkpoint_id, ':lbl' => $time_val, ':val' => $val, ':uid' => $operator_id]);
                        $measurements_created++;
                    }
                }
            }
        }
    }

    echo "Finished Sheet $sheetNum ('$sheetName'): $checkpoints_created checkpoints, $measurements_created measurements imported.\n";
    $summary_results[$sheetName] = [
        'status' => 'IMPORTED',
        'checkpoints_created' => $checkpoints_created,
        'measurements' => $measurements_created
    ];
}

$zip->close();
echo "\n====================================================\n";
echo "COMPLETE TIME CHECK IMPORT SUMMARY:\n";
echo json_encode($summary_results, JSON_PRETTY_PRINT) . "\n";
?>

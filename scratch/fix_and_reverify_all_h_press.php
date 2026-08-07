<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

// Update model_name in dtc_master_parameters from spec table
$conn->exec("
    UPDATE dtc_master_parameters p
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    SET p.model_name = spec.model_name
    WHERE p.model_name IS NULL OR p.model_name = ''
");

// Also update import_h_press_outdoor.php to include model_name in INSERT
$importerFile = __DIR__ . '/import_h_press_outdoor.php';
$code = file_get_contents($importerFile);
$code = str_replace(
    "INSERT INTO dtc_master_parameters (spec_id, target_month, item_check_name, data_type, line_name, section_name, process_name, measuring_item)",
    "INSERT INTO dtc_master_parameters (spec_id, target_month, model_name, item_check_name, data_type, line_name, section_name, process_name, measuring_item)",
    $code
);
$code = str_replace(
    "VALUES (:sid, :month, :item, :dtype, :line, :sec, :proc, 'Qualitative')",
    "VALUES (:sid, :month, :model, :item, :dtype, :line, :sec, :proc, 'Qualitative')",
    $code
);
$code = str_replace(
    "':sid' => \$spec_id,\n        ':month' => \$target_month,\n        ':item' => \$item_check_name,",
    "':sid' => \$spec_id,\n        ':month' => \$target_month,\n        ':model' => \$modelName,\n        ':item' => \$item_check_name,",
    $code
);
file_put_contents($importerFile, $code);

// Re-run batch import for Feb 2026 to ensure 100% clean
$phpPath = 'C:\\xampp\\php\\php.exe';
$cmd = sprintf('"%s" "%s" "202602 Time_Check_H press out_door REF01.xlsx" "2026-02" "REF 01" "H Press Out Door"', $phpPath, $importerFile);
passthru($cmd);

// Close all 28 days for Feb 2026
$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;
$stmt_p = $conn->prepare("SELECT parameter_id FROM dtc_master_parameters WHERE target_month = '2026-02' AND section_name = 'H Press Out Door'");
$stmt_p->execute();
$pList = $stmt_p->fetchAll(PDO::FETCH_COLUMN);

$stmt_check = $conn->prepare("SELECT session_id, is_closed FROM dtc_inspection_sessions WHERE parameter_id = :pid AND inspection_date = :idate");
$stmt_ins = $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, is_closed) VALUES (:pid, :idate, :uid, 1)");
$stmt_upd = $conn->prepare("UPDATE dtc_inspection_sessions SET is_closed = 1 WHERE session_id = :sid AND is_closed = 0");

foreach ($pList as $pid) {
    for ($d = 1; $d <= 28; $d++) {
        $dStr = sprintf('2026-02-%02d', $d);
        $stmt_check->execute([':pid' => $pid, ':idate' => $dStr]);
        $sess = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if (!$sess) {
            $stmt_ins->execute([':pid' => $pid, ':idate' => $dStr, ':uid' => $operator_id]);
        } else if ((int)$sess['is_closed'] === 0) {
            $stmt_upd->execute([':sid' => $sess['session_id']]);
        }
    }
}

echo "\n=== VERIFICATION OF ALL MONTHS (H PRESS OUTDOOR 2026-01 TO 2026-06) ===\n";
$stmt_ver = $conn->query("
    SELECT p.target_month, COUNT(DISTINCT p.parameter_id) as total_models, 
           COUNT(s.session_id) as total_sessions,
           SUM(CASE WHEN s.is_closed = 1 THEN 1 ELSE 0 END) as closed_sessions,
           (SELECT COUNT(*) FROM dtc_measurements m WHERE m.session_id IN (SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id IN (SELECT parameter_id FROM dtc_master_parameters WHERE target_month = p.target_month AND section_name = 'H Press Out Door'))) as total_measurements
    FROM dtc_master_parameters p
    JOIN dtc_inspection_sessions s ON p.parameter_id = s.parameter_id
    WHERE p.section_name = 'H Press Out Door'
    GROUP BY p.target_month
    ORDER BY p.target_month ASC
");
echo json_encode($stmt_ver->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n\n";

// Details of Feb 2026 models
$stmt_feb_models = $conn->query("
    SELECT p.parameter_id, p.model_name, COUNT(DISTINCT s.session_id) as session_count,
           COUNT(m.measurement_id) as measurement_count
    FROM dtc_master_parameters p
    LEFT JOIN dtc_inspection_sessions s ON p.parameter_id = s.parameter_id
    LEFT JOIN dtc_measurements m ON s.session_id = m.session_id
    WHERE p.target_month = '2026-02' AND p.section_name = 'H Press Out Door'
    GROUP BY p.parameter_id
    ORDER BY p.model_name ASC
");
echo "=== FEBRUARY 2026 MODELS DETAILED BREAKDOWN ===\n";
echo json_encode($stmt_feb_models->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

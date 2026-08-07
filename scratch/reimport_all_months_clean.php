<?php
require_once __DIR__ . '/../config/config.php';

$allMonths = [
    ['file' => '202601 Time_Check H Press out_door REF01.xlsx', 'month' => '2026-01'],
    ['file' => '202602 Time_Check_H press out_door REF01.xlsx', 'month' => '2026-02'],
    ['file' => '202603 TIME CHECK H PRESS OUT DOOR REF01.xlsx', 'month' => '2026-03'],
    ['file' => '202604 TIME CHECK H PRESS OUT DOOR REF01.xlsx', 'month' => '2026-04'],
    ['file' => '202605 TIME CHECK H PREESS OUT DOOR REF01.xlsx', 'month' => '2026-05'],
    ['file' => '202606 TIME CHECK H PRESS REF01.xlsx', 'month' => '2026-06']
];

echo "=================================================================\n";
echo "=== RE-IMPORTING ALL 6 MONTHS CLEANLY WITH MODEL NAMES (2026) ===\n";
echo "=================================================================\n\n";

$phpPath = 'C:\\xampp\\php\\php.exe';
$scriptPath = __DIR__ . '/import_h_press_outdoor.php';

foreach ($allMonths as $item) {
    $fName = $item['file'];
    $mStr = $item['month'];

    echo ">>> RE-IMPORTING MONTH $mStr (File: $fName) <<<\n";
    $cmd = sprintf('"%s" "%s" "%s" "%s" "REF 01" "H Press Out Door"', $phpPath, $scriptPath, $fName, $mStr);
    passthru($cmd);
    echo "\n-------------------------------------------------------\n\n";
}

echo "=== CLOSING ALL DAYS FOR ALL MONTHS (2026-01 TO 2026-06) ===\n";
$conn = getDBConnection();
$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;

$stmt_params = $conn->prepare("SELECT parameter_id FROM dtc_master_parameters WHERE target_month = :tm AND section_name = 'H Press Out Door'");
$stmt_check_session = $conn->prepare("SELECT session_id, is_closed FROM dtc_inspection_sessions WHERE parameter_id = :pid AND inspection_date = :idate");
$stmt_ins_session = $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, is_closed) VALUES (:pid, :idate, :uid, 1)");
$stmt_upd_session = $conn->prepare("UPDATE dtc_inspection_sessions SET is_closed = 1 WHERE session_id = :sid AND is_closed = 0");

foreach ($allMonths as $item) {
    $mStr = $item['month'];
    $daysInMonth = (int)date('t', strtotime($mStr . '-01'));

    $stmt_params->execute([':tm' => $mStr]);
    $pList = $stmt_params->fetchAll(PDO::FETCH_COLUMN);

    foreach ($pList as $pid) {
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%s-%02d', $mStr, $d);

            $stmt_check_session->execute([':pid' => $pid, ':idate' => $dateStr]);
            $sess = $stmt_check_session->fetch(PDO::FETCH_ASSOC);

            if (!$sess) {
                $stmt_ins_session->execute([':pid' => $pid, ':idate' => $dateStr, ':uid' => $operator_id]);
            } else if ((int)$sess['is_closed'] === 0) {
                $stmt_upd_session->execute([':sid' => $sess['session_id']]);
            }
        }
    }
    echo "Closed all $daysInMonth days for month $mStr (" . count($pList) . " models).\n";
}

echo "\n=======================================================\n";
echo "=== FINAL VERIFICATION (NO NULL MODELS!) ===\n";
echo "=======================================================\n";

$stmt_verify = $conn->query("
    SELECT p.target_month,
           COUNT(DISTINCT p.parameter_id) as total_models, 
           SUM(CASE WHEN p.model_name IS NULL OR p.model_name = '' THEN 1 ELSE 0 END) as null_model_cnt,
           COUNT(s.session_id) as total_sessions,
           SUM(CASE WHEN s.is_closed = 1 THEN 1 ELSE 0 END) as closed_sessions,
           (SELECT COUNT(*) FROM dtc_measurements m WHERE m.session_id IN (SELECT session_id FROM dtc_inspection_sessions WHERE parameter_id IN (SELECT parameter_id FROM dtc_master_parameters WHERE target_month = p.target_month AND section_name = 'H Press Out Door'))) as total_measurements
    FROM dtc_master_parameters p
    JOIN dtc_inspection_sessions s ON p.parameter_id = s.parameter_id
    WHERE p.section_name = 'H Press Out Door'
    GROUP BY p.target_month
    ORDER BY p.target_month ASC
");
echo json_encode($stmt_verify->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

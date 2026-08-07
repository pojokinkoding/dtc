<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();
$target_month = '2026-01';
$days_in_month = (int)date('t', strtotime($target_month . '-01'));

echo "=== ENSURING ALL 31 DAYS ARE CLOSED FOR ALL PARAMETERS IN $target_month ===\n";
echo "Days in month: $days_in_month\n\n";

// Get all parameters for 2026-01
$stmt_params = $conn->prepare("SELECT parameter_id, line_name, section_name, process_name, item_check_name FROM dtc_master_parameters WHERE target_month = :tm");
$stmt_params->execute([':tm' => $target_month]);
$params = $stmt_params->fetchAll(PDO::FETCH_ASSOC);

echo "Total parameters found for $target_month: " . count($params) . "\n";

$operator_id = $conn->query("SELECT user_id FROM dtc_users WHERE role = 'Admin' ORDER BY user_id ASC LIMIT 1")->fetchColumn() ?: 1;

$stmt_check_session = $conn->prepare("SELECT session_id, is_closed FROM dtc_inspection_sessions WHERE parameter_id = :pid AND inspection_date = :idate");
$stmt_ins_session = $conn->prepare("INSERT INTO dtc_inspection_sessions (parameter_id, inspection_date, operator_id, is_closed) VALUES (:pid, :idate, :uid, 1)");
$stmt_upd_session = $conn->prepare("UPDATE dtc_inspection_sessions SET is_closed = 1 WHERE session_id = :sid AND is_closed = 0");

$created_sessions = 0;
$closed_existing = 0;

foreach ($params as $p) {
    $pid = (int)$p['parameter_id'];

    for ($d = 1; $d <= $days_in_month; $d++) {
        $dateStr = sprintf('%s-%02d', $target_month, $d);

        $stmt_check_session->execute([':pid' => $pid, ':idate' => $dateStr]);
        $sess = $stmt_check_session->fetch(PDO::FETCH_ASSOC);

        if (!$sess) {
            // Create a closed session for this day so the matrix shows locked/closed icon
            $stmt_ins_session->execute([':pid' => $pid, ':idate' => $dateStr, ':uid' => $operator_id]);
            $created_sessions++;
        } else if ((int)$sess['is_closed'] === 0) {
            $stmt_upd_session->execute([':sid' => $sess['session_id']]);
            $closed_existing++;
        }
    }
}

echo "Created new closed sessions for missing days: $created_sessions\n";
echo "Updated existing open sessions to closed: $closed_existing\n\n";

// Verify final totals
$stmt_final = $conn->prepare("
    SELECT is_closed, COUNT(*) as cnt
    FROM dtc_inspection_sessions s
    JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
    WHERE p.target_month = :tm
    GROUP BY is_closed
");
$stmt_final->execute([':tm' => $target_month]);
$final_summary = $stmt_final->fetchAll(PDO::FETCH_ASSOC);

echo "Final Closed Status Summary for $target_month parameters:\n";
echo json_encode($final_summary, JSON_PRETTY_PRINT) . "\n";
?>

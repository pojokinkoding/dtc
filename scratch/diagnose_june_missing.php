<?php
// diagnose_june_missing.php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$month = '2026-06';

// 1. Check master parameters for V Forming in 2026-06
$sqlParams = "
    SELECT p.parameter_id, spec.model_name, spec.item_check_name, spec.section_name, spec.line_name
    FROM dtc_master_parameters p
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    WHERE p.target_month = '$month' AND spec.section_name LIKE 'V Forming%'
";
$params = $conn->query($sqlParams)->fetchAll(PDO::FETCH_ASSOC);

echo "V Forming parameters in 2026-06: " . count($params) . "\n";
foreach ($params as $p) {
    echo "  PID {$p['parameter_id']} | {$p['section_name']} | {$p['model_name']} | {$p['item_check_name']}\n";
}

// 2. Check inspection sessions for these parameters
$pids = array_column($params, 'parameter_id');
if (!empty($pids)) {
    $inClause = implode(',', $pids);
    $sqlSess = "
        SELECT s.parameter_id, s.session_id, s.inspection_date, s.is_closed, s.is_active,
               (SELECT COUNT(*) FROM dtc_measurements m WHERE m.session_id = s.session_id AND m.sample_value != '') as filled_count
        FROM dtc_inspection_sessions s
        WHERE s.parameter_id IN ($inClause) AND DATE_FORMAT(s.inspection_date, '%Y-%m') = '$month'
        ORDER BY s.parameter_id, s.inspection_date ASC
    ";
    $sessions = $conn->query($sqlSess)->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTotal inspection sessions created for V Forming in $month: " . count($sessions) . "\n";

    $isClosedStats = ['closed' => 0, 'open' => 0];
    foreach ($sessions as $s) {
        if ($s['is_closed'] == 1) $isClosedStats['closed']++;
        else $isClosedStats['open']++;
    }
    echo "Sessions status breakdown: " . json_encode($isClosedStats) . "\n";
}
?>

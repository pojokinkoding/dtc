<?php
// test_checkpoint_expansion.php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

$month = '2026-06';

// 1. Fetch all active parameters for the month (WITHOUT IP filter)
$sqlParams = "
    SELECT p.parameter_id, p.target_month, spec.model_name, spec.item_check_name, spec.sub_item_check_name, spec.data_type, spec.section_name, spec.line_name, spec.process_name,
    (SELECT MAX(CAST(m.sample_sequence AS UNSIGNED)) 
     FROM dtc_measurements m 
     JOIN dtc_inspection_sessions s2 ON m.session_id = s2.session_id 
     WHERE s2.parameter_id = p.parameter_id AND m.sample_value != '') as max_seq
    FROM dtc_master_parameters p
    JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
    WHERE p.target_month = :month
    ORDER BY spec.line_name, spec.section_name, spec.process_name
";
$stmtParams = $conn->prepare($sqlParams);
$stmtParams->execute([':month' => $month]);
$parameters = $stmtParams->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch all checkpoints for parameters that use Checkpoints
$sqlCheckpoints = "
    SELECT checkpoint_id, parameter_id, checkpoint_name, checkpoint_type, sort_order 
    FROM dtc_checkpoints 
    ORDER BY parameter_id, sort_order ASC
";
$allCheckpoints = $conn->query($sqlCheckpoints)->fetchAll(PDO::FETCH_ASSOC);
$paramCheckpoints = [];
foreach ($allCheckpoints as $cp) {
    $pid = $cp['parameter_id'];
    if (!isset($paramCheckpoints[$pid])) $paramCheckpoints[$pid] = [];
    $paramCheckpoints[$pid][] = $cp;
}

// 3. Fetch measurements per session, checkpoint, and day
$sqlSessions = "
    SELECT s.parameter_id, DATE_FORMAT(s.inspection_date, '%d') as day_of_month, s.is_closed, s.session_id
    FROM dtc_inspection_sessions s
    WHERE DATE_FORMAT(s.inspection_date, '%Y-%m') = :month AND s.is_active = 1
";
$stmtSessions = $conn->prepare($sqlSessions);
$stmtSessions->execute([':month' => $month]);
$sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

$sessionInfo = [];
$sessionIds = [];
foreach ($sessions as $s) {
    $sessionInfo[$s['session_id']] = $s;
    $sessionIds[] = $s['session_id'];
}

$filledByCheckpoint = []; // $filledByCheckpoint[checkpoint_id][day] = ['filled_labels' => [...], 'is_closed' => 1|2]
if (!empty($sessionIds)) {
    $inClause = implode(',', array_map('intval', $sessionIds));
    $sqlMeas = "
        SELECT m.session_id, m.checkpoint_id, GROUP_CONCAT(DISTINCT m.sample_label) as filled_labels,
               GROUP_CONCAT(DISTINCT m.sample_sequence) as filled_seqs
        FROM dtc_measurements m
        WHERE m.session_id IN ($inClause) AND m.sample_value != ''
        GROUP BY m.session_id, m.checkpoint_id
    ";
    $measRows = $conn->query($sqlMeas)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($measRows as $mr) {
        $sessId = $mr['session_id'];
        $cpId = intval($mr['checkpoint_id']);
        if (!isset($sessionInfo[$sessId])) continue;
        $day = intval($sessionInfo[$sessId]['day_of_month']);
        $isClosed = intval($sessionInfo[$sessId]['is_closed']) === 1 ? 2 : 1;
        $fl = !empty($mr['filled_labels']) ? explode(',', $mr['filled_labels']) : [];
        $fs = !empty($mr['filled_seqs']) ? explode(',', $mr['filled_seqs']) : [];

        if ($cpId > 0) {
            $filledByCheckpoint[$cpId][$day] = [
                'status' => $isClosed,
                'filled_labels' => $fl,
                'filled_seqs' => $fs
            ];
        }
    }
}

echo "Total parameters: " . count($parameters) . "\n";
echo "Parameters with checkpoints: " . count($paramCheckpoints) . "\n";

$data = [];
foreach ($parameters as $param) {
    $pid = $param['parameter_id'];
    $dataType = strtoupper(trim($param['data_type']));
    $isTimeBased = ($dataType === 'TIME CHECK' || $dataType === 'F/PROOF');

    if ($isTimeBased && !empty($paramCheckpoints[$pid])) {
        // Expand each Checkpoint as a row
        foreach ($paramCheckpoints[$pid] as $cp) {
            $cpid = $cp['checkpoint_id'];
            $data[] = [
                'parameter_id' => $pid,
                'checkpoint_id' => $cpid,
                'line_name' => $param['line_name'],
                'section_name' => $param['section_name'],
                'process_name' => $param['process_name'],
                'model_name' => $param['model_name'],
                'item_check_name' => $cp['checkpoint_name'],
                'sub_item_check_name' => $param['item_check_name'],
                'data_type' => $param['data_type']
            ];
        }
    } else {
        $data[] = [
            'parameter_id' => $pid,
            'checkpoint_id' => 0,
            'line_name' => $param['line_name'],
            'section_name' => $param['section_name'],
            'process_name' => $param['process_name'],
            'model_name' => $param['model_name'],
            'item_check_name' => $param['item_check_name'],
            'sub_item_check_name' => $param['sub_item_check_name'],
            'data_type' => $param['data_type']
        ];
    }
}

echo "Total rows created after checkpoint expansion: " . count($data) . "\n";

$sectionCounts = [];
foreach ($data as $d) {
    $secKey = $d['line_name'] . ' ___ ' . $d['section_name'];
    if (!isset($sectionCounts[$secKey])) $sectionCounts[$secKey] = 0;
    $sectionCounts[$secKey]++;
}
echo "Section breakdown after expansion:\n" . json_encode($sectionCounts, JSON_PRETTY_PRINT) . "\n";
?>

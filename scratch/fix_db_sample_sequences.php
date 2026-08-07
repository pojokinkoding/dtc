<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "=== RE-INDEXING SAMPLE SEQUENCES ACROSS ALL MEASUREMENTS IN DB ===\n";

// Get all unique combinations of session_id and checkpoint_id
$stmt = $conn->query("
    SELECT session_id, checkpoint_id
    FROM dtc_measurements
    WHERE checkpoint_id IS NOT NULL
    GROUP BY session_id, checkpoint_id
");
$pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($pairs) . " (session_id, checkpoint_id) pairs to re-sequence.\n";

$stmt_get_meas = $conn->prepare("
    SELECT measurement_id, sample_sequence, sample_label
    FROM dtc_measurements
    WHERE session_id = :sid AND checkpoint_id = :cpid
    ORDER BY measurement_id ASC
");

$stmt_upd = $conn->prepare("
    UPDATE dtc_measurements
    SET sample_sequence = :seq
    WHERE measurement_id = :mid
");

$updated_cnt = 0;
$conn->beginTransaction();

foreach ($pairs as $p) {
    $sid = (int)$p['session_id'];
    $cpid = (int)$p['checkpoint_id'];

    $stmt_get_meas->execute([':sid' => $sid, ':cpid' => $cpid]);
    $measList = $stmt_get_meas->fetchAll(PDO::FETCH_ASSOC);

    $newSeq = 1;
    foreach ($measList as $m) {
        $mid = (int)$m['measurement_id'];
        $oldSeq = (int)$m['sample_sequence'];

        if ($oldSeq !== $newSeq) {
            $stmt_upd->execute([':seq' => $newSeq, ':mid' => $mid]);
            $updated_cnt++;
        }
        $newSeq++;
    }
}

$conn->commit();
echo "Successfully updated $updated_cnt measurement rows with sequential sample_sequence (1, 2, 3, 4...).\n\n";

// Verify parameter 3931 (GN-702G)
$stmt_check = $conn->query("
    SELECT m.checkpoint_id, cp.checkpoint_name, m.sample_sequence, m.sample_label, m.sample_value, s.inspection_date
    FROM dtc_measurements m
    JOIN dtc_checkpoints cp ON m.checkpoint_id = cp.checkpoint_id
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
    WHERE s.parameter_id = 3931
      AND s.inspection_date = '2026-01-02'
      AND cp.checkpoint_name = 'Bending'
    ORDER BY m.measurement_id ASC
");
echo "=== VERIFICATION FOR PARAMETER 3931 (GN-702G) AFTER FIX ===\n";
echo json_encode($stmt_check->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
?>

<?php
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

function sortShiftTimeLabels($labels) {
    $labels = array_values(array_unique(array_filter($labels)));
    usort($labels, function($a, $b) {
        $getMins = function($str) {
            $str = trim($str);
            if (preg_match('/^(\d{1,2})[:\.](\d{2})/', $str, $m)) {
                $h = (int)$m[1];
                $min = (int)$m[2];
                if ($h < 7) {
                    $h += 24;
                }
                return $h * 60 + $min;
            }
            return 9999;
        };
        return $getMins($a) <=> $getMins($b);
    });
    return $labels;
}

// Check Parameter 3931 (GN-702G, 2026-01)
$stmt1 = $conn->query("
    SELECT DISTINCT m.sample_label 
    FROM dtc_measurements m 
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id 
    WHERE s.parameter_id = 3931
");
$distinct1 = $stmt1->fetchAll(PDO::FETCH_COLUMN);
echo "=== PARAMETER 3931 (GN-702G, H PRESS REF02) ===\n";
echo "Raw DB labels: " . json_encode($distinct1) . "\n";
echo "Sorted Shift Labels: " . json_encode(sortShiftTimeLabels($distinct1), JSON_PRETTY_PRINT) . "\n\n";

// Check Parameter 4007 (July 2026, empty)
$stmt2 = $conn->query("
    SELECT DISTINCT m.sample_label 
    FROM dtc_measurements m 
    JOIN dtc_inspection_sessions s ON m.session_id = s.session_id 
    WHERE s.parameter_id = 4007
");
$distinct2 = $stmt2->fetchAll(PDO::FETCH_COLUMN);
$base_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30', '04:30'];
$merged2 = empty($distinct2) ? $base_labels : (count($distinct2) >= 5 ? $distinct2 : array_merge($distinct2, $base_labels));

echo "=== PARAMETER 4007 (JULY 2026, EMPTY) ===\n";
echo "Sorted Shift Labels: " . json_encode(sortShiftTimeLabels($merged2), JSON_PRETTY_PRINT) . "\n";
?>

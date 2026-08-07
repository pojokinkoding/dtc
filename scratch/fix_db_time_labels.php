<?php
require_once __DIR__ . '/../config/config.php';

function sortDtcTimeLabels(array $labels): array {
    $labels = array_values(array_unique(array_filter($labels)));
    usort($labels, function($a, $b) {
        $parseMins = function($t) {
            $parts = explode(':', trim($t));
            $h = intval($parts[0] ?? 0);
            $m = intval($parts[1] ?? 0);
            if ($h == 24) $h = 0;
            $mins = $h * 60 + $m;
            if ($mins < 7 * 60) {
                $mins += 24 * 60;
            }
            return $mins;
        };
        return $parseMins($a) <=> $parseMins($b);
    });
    return array_values($labels);
}

$conn = getDBConnection();
$stmt = $conn->query("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE 'time_matrix_labels%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $key = $r['setting_key'];
    $val = is_resource($r['setting_value']) ? stream_get_contents($r['setting_value']) : $r['setting_value'];
    $decoded = json_decode($val, true) ?: [];
    
    // If 16:40 or 16:30 is missing, add it
    if ($key === 'time_matrix_labels_REF 01') {
        if (!in_array('16:30', $decoded) && !in_array('16:40', $decoded)) {
            $decoded[] = '16:30';
        }
    } else {
        if (!in_array('16:40', $decoded)) {
            $decoded[] = '16:40';
        }
    }
    
    $sorted = sortDtcTimeLabels($decoded);
    $jsonNew = json_encode($sorted);
    
    $upStmt = $conn->prepare("UPDATE dtc_app_settings SET setting_value = :val WHERE setting_key = :key");
    $upStmt->execute([':val' => $jsonNew, ':key' => $key]);
    echo "Updated {$key} => {$jsonNew}\n";
}

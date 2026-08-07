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

$stmt = $conn->query("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE '%time%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$upd = $conn->prepare("UPDATE dtc_app_settings SET setting_value = :val WHERE setting_key = :key");

foreach ($rows as $r) {
    $key = $r['setting_key'];
    $rawVal = is_resource($r['setting_value']) ? stream_get_contents($r['setting_value']) : $r['setting_value'];
    $decoded = json_decode($rawVal, true);

    if (is_array($decoded)) {
        $sorted = sortShiftTimeLabels($decoded);
        $newJson = json_encode($sorted);
        $upd->execute([':val' => $newJson, ':key' => $key]);
        echo "Updated $key => $newJson\n";
    }
}
?>

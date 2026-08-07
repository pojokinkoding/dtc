<?php
function sortDtcTimeLabels(array $labels): array {
    $labels = array_values(array_unique(array_filter($labels)));
    usort($labels, function($a, $b) {
        $parseMins = function($t) {
            $parts = explode(':', trim($t));
            $h = intval($parts[0] ?? 0);
            $m = intval($parts[1] ?? 0);
            if ($h == 24) $h = 0; // 24:30 is 00:30 AM
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

$input = ["07:30", "09:40", "12:40", "14:40", "18:40", "20:05", "22:30", "24:30", "02:30", "04:30", "16:40"];
$output = sortDtcTimeLabels($input);
print_r($output);

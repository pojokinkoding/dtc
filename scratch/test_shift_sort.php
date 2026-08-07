<?php
function sortShiftTimeLabels($labels) {
    $labels = array_values(array_unique(array_filter($labels)));
    usort($labels, function($a, $b) {
        $getMins = function($str) {
            $str = trim($str);
            if (preg_match('/^(\d{1,2}):(\d{2})/', $str, $m)) {
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

// Test 1: Random H Press labels
$test1 = ["15:40", "07:40", "01:40", "09:40", "21:40", "17:40", "11:40", "23:40", "03:40", "05:40", "13:40", "19:40"];
echo "Test 1 Sorted:\n" . json_encode(sortShiftTimeLabels($test1), JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Standard settings labels
$test2 = ["07:30", "09:40", "12:40", "14:40", "16:40", "18:40", "20:05", "22:30", "24:30", "02:30", "04:30"];
echo "Test 2 Sorted:\n" . json_encode(sortShiftTimeLabels($test2), JSON_PRETTY_PRINT) . "\n";
?>

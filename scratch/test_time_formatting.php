<?php
function formatPudoorTimeLabelRef2($bVal, $seq) {
    $bVal_raw = trim((string)$bVal);
    if (is_numeric($bVal_raw) && floatval($bVal_raw) > 0 && floatval($bVal_raw) < 1) {
        $totalMinutes = round(floatval($bVal_raw) * 24 * 60);
        $hours = floor($totalMinutes / 60);
        $mins = $totalMinutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }
    $bVal_str = str_replace('.', ':', $bVal_raw);
    return $bVal_str !== '' ? $bVal_str : "Sample $seq";
}

$test_cases = [
    '0.3125',
    '0.5',
    '0.76388888888888884',
    '0.85416666666666663',
    '2.0833333333333332E-2',
    '0.1875',
    '07.30',
    '12.00',
    '18.30'
];

foreach ($test_cases as $tc) {
    echo "Raw: '$tc' => Formatted: '" . formatPudoorTimeLabelRef2($tc, 1) . "'\n";
}

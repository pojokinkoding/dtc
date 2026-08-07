<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/../202601 Foolproof Hinge Lower REF01.xlsx';
if (!file_exists($file)) {
    die("File not found: $file\n");
}

$spreadsheet = IOFactory::load($file);
$sheets = $spreadsheet->getSheetNames();
echo "Sheet names:\n" . json_encode($sheets, JSON_PRETTY_PRINT) . "\n\n";

foreach ($sheets as $sheetName) {
    $sheet = $spreadsheet->getSheetByName($sheetName);
    echo "--- SHEET: $sheetName (Max Col: {$sheet->getHighestColumn()}, Max Row: {$sheet->getHighestRow()}) ---\n";
    
    // Print first 15 rows, cols A to J
    for ($r = 1; $r <= 15; $r++) {
        $rowVal = [];
        for ($c = 1; $c <= 10; $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $val = trim($sheet->getCell("{$colLetter}{$r}")->getFormattedValue() ?? '');
            if ($val !== '') {
                $rowVal[] = "$colLetter$r: $val";
            }
        }
        if (!empty($rowVal)) {
            echo "Row $r -> " . implode(' | ', $rowVal) . "\n";
        }
    }
    echo "\n";
}
?>

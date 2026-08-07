<?php
// parse_drawing_anchors.php
$file = __DIR__ . '/../timecheck_v_forming_male_A_ref01_jan.xlsx';
$zip = new ZipArchive();
$zip->open($file);

foreach (['drawing1', 'drawing2', 'drawing3', 'drawing4', 'drawing5'] as $dname) {
    $xmlStr = $zip->getFromName("xl/drawings/$dname.xml");
    if (!$xmlStr) continue;
    
    echo "======================================\n";
    echo "DRAWING: $dname\n";
    echo "======================================\n";
    
    $xml = simplexml_load_string($xmlStr);
    $xml->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
    $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
    
    foreach ($xml->children('xdr', true) as $anchor) {
        $fromRow = (int)($anchor->from->row ?? 0) + 1; // 1-based row index
        $fromCol = (int)($anchor->from->col ?? 0); // 0-based col index (0=A, 1=B, 2=C)
        
        $texts = [];
        foreach ($anchor->xpath('.//a:t') as $t) {
            $str = trim((string)$t);
            if ($str !== '') $texts[] = $str;
        }
        if (!empty($texts)) {
            echo "Row $fromRow, Col $fromCol: " . implode(" | ", $texts) . "\n";
        }
    }
}
?>

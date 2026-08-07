<?php
$files = glob(__DIR__ . '/../ctp_acc_*.xlsx');

function getCellValueAccAll($cell, $sharedStrings) {
    $type = (string)$cell['t'];
    $v = (string)$cell->v;
    if ($type === 's') return isset($sharedStrings[intval($v)]) ? $sharedStrings[intval($v)] : $v;
    return $v;
}

foreach ($files as $file) {
    echo "========================================================\n";
    echo "File: " . basename($file) . "\n";
    $zip = new ZipArchive();
    if ($zip->open($file) !== TRUE) continue;

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ss = simplexml_load_string($ssXml);
        foreach ($ss->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
            } else {
                $t = '';
                foreach ($si->r as $r) { $t .= (string)$r->t; }
                $sharedStrings[] = $t;
            }
        }
    }

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $wb = simplexml_load_string($workbookXml);
    $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    foreach ($wb->sheets->sheet as $sheet) {
        $sheetName = (string)$sheet['name'];
        $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $rels = simplexml_load_string($relsXml);
        $targetMap = [];
        foreach ($rels->Relationship as $rel) {
            $targetMap[(string)$rel['Id']] = (string)$rel['Target'];
        }

        $sheetXml = $zip->getFromName('xl/' . $targetMap[$rId]);
        if (!$sheetXml) continue;
        $xml = simplexml_load_string($sheetXml);

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rNum = (int)$row['r'];
            if ($rNum > 30) continue;
            $rowCells = [];
            foreach ($row->c as $c) {
                $colRef = (string)$c['r'];
                preg_match('/([A-Z]+)(\d+)/', $colRef, $matches);
                $colLetter = $matches[1] ?? $colRef;
                $val = getCellValueAccAll($c, $sharedStrings);
                if ($val !== '' && $val !== null) {
                    $rowCells[$colLetter] = $val;
                }
            }
            if (!empty($rowCells)) $rows[$rNum] = $rowCells;
        }

        $model_e5 = $rows[5]['E'] ?? ($rows[5]['F'] ?? 'N/A');
        $ctq_e4 = $rows[4]['E'] ?? ($rows[4]['F'] ?? 'N/A');
        $proc_e6 = $rows[6]['E'] ?? ($rows[6]['F'] ?? 'N/A');
        $lsl_l5 = $rows[5]['L'] ?? 'N/A';
        $usl_l6 = $rows[6]['L'] ?? 'N/A';
        $uom_m5 = $rows[5]['M'] ?? 'N/A';

        // find date row
        $dateRow = null;
        for ($r = 10; $r <= 25; $r++) {
            if (isset($rows[$r])) {
                foreach ($rows[$r] as $col => $val) {
                    if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                        $dateRow = $r;
                        break 2;
                    }
                }
            }
        }

        $sample_times = [];
        if ($dateRow) {
            for ($sr = $dateRow + 1; $sr <= $dateRow + 10; $sr++) {
                $lbl = isset($rows[$sr]['B']) ? trim((string)$rows[$sr]['B']) : '';
                if (strcasecmp($lbl, 'Xbar') === 0 || strcasecmp($lbl, 'R') === 0 || stristr($lbl, 'Std') !== false || stristr($lbl, 'Max') !== false || stristr($lbl, 'Min') !== false || stristr($lbl, 'Average') !== false) break;
                if ($lbl !== '') $sample_times[] = $lbl;
            }
        }

        echo "Sheet '$sheetName': Model Row5='$model_e5' | CTQ Row4='$ctq_e4' | Proc Row6='$proc_e6' | Spec: LSL=$lsl_l5, USL=$usl_l6 $uom_m5 | DateRow: " . ($dateRow ?: 'NONE') . " | Time Slots (" . count($sample_times) . "): [" . implode(', ', $sample_times) . "]\n";
    }

    $zip->close();
}

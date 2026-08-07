<?php
require_once __DIR__ . '/../config/config.php';

echo "=======================================================================\n";
echo "=== FIXING SAMPLE SEQUENCES & RE-IMPORTING ALL CHECKPOINT DATA ===\n";
echo "=======================================================================\n\n";

$phpPath = 'C:\\xampp\\php\\php.exe';

// 1. Re-import H Press Out Door REF 02 (Jan - Jun 2026)
echo ">>> STEP 1: Re-importing H Press Out Door REF 02 (2026-01) <<<\n";
$code = file_get_contents(__DIR__ . '/import_h_press_ref02.php');

// Replace measurement insertion logic in import_h_press_ref02.php
$oldLoop = '        if (isset($datesData[$dateStr])) {
            $seq = 1;
            foreach ($datesData[$dateStr] as $m) {
                $cpName = $m[\'cpName\'];
                if (!isset($cp_id_map[$cpName])) continue;

                $stmt_ins_m->execute([
                    \':sid\' => $session_id,
                    \':cpid\' => $cp_id_map[$cpName],
                    \':seq\' => $seq++,
                    \':label\' => $m[\'label\'],
                    \':val\' => $m[\'val\'],
                    \':uid\' => $operator_id
                ]);
                $total_measurements++;
            }
        }';

$newLoop = '        if (isset($datesData[$dateStr])) {
            $timeSlotMap = [];
            foreach ($datesData[$dateStr] as $m) {
                $lbl = $m[\'label\'];
                if (!isset($timeSlotMap[$lbl])) $timeSlotMap[$lbl] = [];
                $timeSlotMap[$lbl][] = $m;
            }

            $seq = 1;
            foreach ($timeSlotMap as $lbl => $mList) {
                foreach ($mList as $m) {
                    $cpName = $m[\'cpName\'];
                    if (!isset($cp_id_map[$cpName])) continue;

                    $stmt_ins_m->execute([
                        \':sid\' => $session_id,
                        \':cpid\' => $cp_id_map[$cpName],
                        \':seq\' => $seq,
                        \':label\' => $lbl,
                        \':val\' => $m[\'val\'],
                        \':uid\' => $operator_id
                    ]);
                    $total_measurements++;
                }
                $seq++;
            }
        }';

$code = str_replace($oldLoop, $newLoop, $code);
file_put_contents(__DIR__ . '/import_h_press_ref02.php', $code);

// Run single import 2026-01
passthru("\"$phpPath\" \"" . __DIR__ . "/import_h_press_ref02.php\"");

// Update batch_import_hpress_ref02.php as well
$bCode = file_get_contents(__DIR__ . '/batch_import_hpress_ref02.php');
$bCode = str_replace($oldLoop, $newLoop, $bCode);
file_put_contents(__DIR__ . '/batch_import_hpress_ref02.php', $bCode);

echo "\n>>> STEP 2: Re-importing H Press Out Door REF 02 (2026-02 to 2026-06) <<<\n";
passthru("\"$phpPath\" \"" . __DIR__ . "/batch_import_hpress_ref02.php\"");

// 2. Update import_h_press_outdoor.php and batch_import_h_press.php (REF 01)
$code1 = file_get_contents(__DIR__ . '/import_h_press_outdoor.php');
$code1 = str_replace($oldLoop, $newLoop, $code1);
file_put_contents(__DIR__ . '/import_h_press_outdoor.php', $code1);

$bCode1 = file_get_contents(__DIR__ . '/batch_import_h_press.php');
$bCode1 = str_replace($oldLoop, $newLoop, $bCode1);
file_put_contents(__DIR__ . '/batch_import_h_press.php', $bCode1);

echo "\n>>> STEP 3: Re-importing H Press Out Door REF 01 (2026-01 to 2026-06) <<<\n";
passthru("\"$phpPath\" \"" . __DIR__ . "/batch_import_h_press.php\"");

// 3. Update import_autovinyl_cutting.php and batch_import_autovinyl.php
$codeAv = file_get_contents(__DIR__ . '/import_autovinyl_cutting.php');
$codeAv = str_replace($oldLoop, $newLoop, $codeAv);
file_put_contents(__DIR__ . '/import_autovinyl_cutting.php', $codeAv);

$bCodeAv = file_get_contents(__DIR__ . '/batch_import_autovinyl.php');
$bCodeAv = str_replace($oldLoop, $newLoop, $bCodeAv);
file_put_contents(__DIR__ . '/batch_import_autovinyl.php', $bCodeAv);

echo "\n>>> STEP 4: Re-importing AutoVinyl Cutting REF 02 (2026-01 to 2026-06) <<<\n";
passthru("\"$phpPath\" \"" . __DIR__ . "/import_autovinyl_cutting.php\"");
passthru("\"$phpPath\" \"" . __DIR__ . "/batch_import_autovinyl.php\"");

echo "\n=======================================================================\n";
echo "=== RE-IMPORT WITH CORRECT SAMPLE SEQUENCES COMPLETED ===\n";
echo "=======================================================================\n";
?>

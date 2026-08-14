<?php
// c_dtc_summary_ticker.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

try {
    $conn = getDBConnection();

    // Shift 3 cutoff: before 07:00 belongs to yesterday
    $prod_hour = (int)date('H');
    $today = ($prod_hour < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
    $currentMonth = date('Y-m', strtotime($today));

    // 1. Load active running models for this month filtered by IP & user section
    $sqlRM = "
        SELECT running_id, line_name, section_name, model_name, created_at
        FROM dtc_running_models
        WHERE target_month = :month AND is_active = 1
        " . getIPAccessFilterSQL('line_name', 'section_name') . getUserAccessFilterSQL('line_name', 'section_name') . "
        ORDER BY model_name ASC
    ";
    $stmtRM = $conn->prepare($sqlRM);
    $stmtRM->execute([':month' => $currentMonth]);
    $runningModels = $stmtRM->fetchAll(PDO::FETCH_ASSOC);

    if (empty($runningModels)) {
        echo json_encode(['status' => 'success', 'today_formatted' => date('d M Y', strtotime($today)), 'models' => [], 'no_models' => true]);
        exit;
    }

    // 2. Load time matrix labels per line
    $stmtLabel = $conn->prepare("SELECT setting_key, setting_value FROM dtc_app_settings WHERE setting_key LIKE 'time_matrix_labels_%'");
    $stmtLabel->execute();
    $line_labels = [];
    while ($rowSetting = $stmtLabel->fetch(PDO::FETCH_ASSOC)) {
        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
        $decoded = json_decode($val, true);
        if ($decoded) {
            $ln = str_replace('time_matrix_labels_', '', $rowSetting['setting_key']);
            $line_labels[$ln] = $decoded;
        }
    }
    $default_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30'];
    $nowH = (int)date('H');
    $nowM = (int)date('i');

    // 3. Load all parameters for current month indexed by (model_name, line_name, section_name) filtered by IP & user section
    $sqlP = "
        SELECT p.parameter_id,
               COALESCE(p.line_name, s.line_name) AS line_name,
               COALESCE(p.section_name, s.section_name) AS section_name,
               COALESCE(p.model_name, s.model_name) AS model_name
        FROM dtc_master_parameters p
        LEFT JOIN dtc_master_dtc_specs s ON p.spec_id = s.spec_id
        WHERE p.target_month = :month
        " . getIPAccessFilterSQL('COALESCE(p.line_name, s.line_name)', 'COALESCE(p.section_name, s.section_name)') . getUserAccessFilterSQL('COALESCE(p.line_name, s.line_name)', 'COALESCE(p.section_name, s.section_name)') . "
    ";
    $stmtP = $conn->prepare($sqlP);
    $stmtP->execute([':month' => $currentMonth]);
    $allParams = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // Index by model+line+section
    $paramsByModel = [];
    $paramIdList = [];
    foreach ($allParams as $p) {
        $key = strtolower(trim($p['model_name'])) . '|' . strtolower(trim($p['line_name'])) . '|' . strtolower(trim($p['section_name']));
        $paramsByModel[$key][] = $p['parameter_id'];
        $paramIdList[] = $p['parameter_id'];
    }

    if (empty($paramIdList)) {
        echo json_encode(['status' => 'success', 'today_formatted' => date('d M Y', strtotime($today)), 'models' => [], 'no_models' => true]);
        exit;
    }

    // 4. Load today's closed sessions
    $stmtClosed = $conn->prepare("
        SELECT parameter_id FROM dtc_inspection_sessions
        WHERE inspection_date = :today AND is_closed = 1
    ");
    $stmtClosed->execute([':today' => $today]);
    $closedSet = [];
    while ($rc = $stmtClosed->fetch(PDO::FETCH_ASSOC)) {
        $closedSet[$rc['parameter_id']] = true;
    }

    // 5. Load today's filled sample labels
    $stmtM = $conn->prepare("
        SELECT s.parameter_id, m.sample_label
        FROM dtc_inspection_sessions s
        JOIN dtc_measurements m ON s.session_id = m.session_id
        WHERE s.inspection_date = :today AND m.sample_value IS NOT NULL AND m.sample_value != ''
    ");
    $stmtM->execute([':today' => $today]);
    $filledMap = [];
    while ($rowM = $stmtM->fetch(PDO::FETCH_ASSOC)) {
        $filledMap[$rowM['parameter_id']][$rowM['sample_label']] = true;
    }

    // 6. Helper to check if a time slot has passed
    function isSlotPast($timeStr, $nowH, $nowM) {
        if (!$timeStr) return false;
        $tp = explode(':', trim($timeStr));
        if (count($tp) < 2) return false;
        $h = (int)$tp[0]; $m = (int)$tp[1];
        if ($h >= 24) $h = $h - 24;

        // Production day runs from 07:00 AM to 07:00 AM next morning.
        // Hours < 7 (e.g. 00:30, 02:30, 04:30) belong to night shift (next calendar day morning, +24h).
        $slotMinutesFrom7 = ($h < 7 ? $h + 24 : $h) * 60 + $m;
        $nowMinutesFrom7 = ($nowH < 7 ? $nowH + 24 : $nowH) * 60 + $nowM;

        return $slotMinutesFrom7 <= $nowMinutesFrom7;
    }

    // 7. Per-running-model summary
    $modelSummaries = [];
    foreach ($runningModels as $rm) {
        $rmModel = trim($rm['model_name']);
        $rmLine  = trim($rm['line_name']);
        $rmSec   = trim($rm['section_name']);
        $rmCreatedAt = $rm['created_at'] ?? null;
        $key = strtolower($rmModel) . '|' . strtolower($rmLine) . '|' . strtolower($rmSec);

        $pidList = $paramsByModel[$key] ?? [];
        $totalPid = count($pidList);
        $closedCount = 0;
        $overdueSlots = 0;

        $slots = isset($line_labels[$rmLine]) ? $line_labels[$rmLine] : $default_labels;

        $createdMinsFrom7 = null;
        if ($rmCreatedAt) {
            $createdParts = explode(' ', trim($rmCreatedAt));
            $cDate = $createdParts[0] ?? '';
            $cTime = $createdParts[1] ?? '';
            if ($cDate === $today && !empty($cTime)) {
                $tp = explode(':', $cTime);
                $cH = (int)($tp[0] ?? 0);
                $cM = (int)($tp[1] ?? 0);
                if ($cH < 7) $cH += 24;
                $createdMinsFrom7 = $cH * 60 + $cM;
            }
        }

        foreach ($pidList as $pid) {
            if (isset($closedSet[$pid])) $closedCount++;

            foreach ($slots as $idx => $timeStr) {
                if (isset($filledMap[$pid][$timeStr])) continue;

                $stp = explode(':', trim($timeStr));
                $sh = (int)($stp[0] ?? 0);
                $sm = (int)($stp[1] ?? 0);
                if ($sh >= 24) $sh -= 24;
                $curSlotMinsFrom7 = ($sh < 7 ? $sh + 24 : $sh) * 60 + $sm;

                $nextTimeStr = $slots[$idx + 1] ?? null;
                if ($nextTimeStr) {
                    $ntp = explode(':', trim($nextTimeStr));
                    $nsh = (int)($ntp[0] ?? 0);
                    $nsm = (int)($ntp[1] ?? 0);
                    if ($nsh >= 24) $nsh -= 24;
                    $nextSlotMinsFrom7 = ($nsh < 7 ? $nsh + 24 : $nsh) * 60 + $nsm;
                } else {
                    $nextSlotMinsFrom7 = $curSlotMinsFrom7 + 120;
                }

                // If running model was created AFTER this slot's session window ended -> NOT OVERDUE!
                if ($createdMinsFrom7 !== null && $createdMinsFrom7 >= $nextSlotMinsFrom7) {
                    continue;
                }

                if (isSlotPast($timeStr, $nowH, $nowM)) $overdueSlots++;
            }
        }

        $unclosed = max(0, $totalPid - $closedCount);
        $compliance = ($totalPid > 0) ? round(($closedCount / $totalPid) * 100, 1) : 100.0;

        $modelSummaries[] = [
            'model_name'     => $rmModel,
            'line_name'      => $rmLine,
            'section_name'   => $rmSec,
            'total_params'   => $totalPid,
            'closed_count'   => $closedCount,
            'unclosed_count' => $unclosed,
            'overdue_slots'  => $overdueSlots,
            'compliance_rate'=> $compliance,
        ];
    }

    echo json_encode([
        'status'          => 'success',
        'today_formatted' => date('d M Y', strtotime($today)),
        'models'          => $modelSummaries,
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

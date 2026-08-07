<?php
// Script/php/dtc/c_dtc_insight_measurement.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

try {
    $param_id = isset($_GET['param_id']) ? intval($_GET['param_id']) : 0;
    $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m'); // e.g. "2026-06"
    
    if (!$param_id) throw new Exception("Invalid param_id");

    $conn = getDBConnection();
    
    // 1. Get the line_name to determine expected slots per day
    $stmtLine = $conn->prepare("
        SELECT spec.line_name 
        FROM dtc_master_dtc_specs spec
        JOIN dtc_master_parameters p ON spec.spec_id = p.spec_id 
        WHERE p.parameter_id = :pid
    ");
    $stmtLine->execute([':pid' => $param_id]);
    $line_name = $stmtLine->fetchColumn() ?: 'REF 01';
    
    $setting_key = 'time_matrix_labels_' . $line_name;
    $stmtSetting = $conn->prepare("SELECT setting_value FROM dtc_app_settings WHERE setting_key = :key");
    $stmtSetting->execute([':key' => $setting_key]);
    $rowSetting = $stmtSetting->fetch(PDO::FETCH_ASSOC);
    
    $time_labels = [];
    if ($rowSetting && $rowSetting['setting_value']) {
        $val = is_resource($rowSetting['setting_value']) ? stream_get_contents($rowSetting['setting_value']) : $rowSetting['setting_value'];
        $time_labels = json_decode($val, true);
    }
    if (empty($time_labels)) {
        $time_labels = ['07:30', '09:40', '12:40', '14:40', '16:40', '18:40', '20:05', '22:30', '24:30', '02:30'];
    }
    $slots_per_day = count($time_labels);
    
    // 2. Fetch all sessions for this param in this month
    $sql = "
        SELECT 
            s.session_id,
            s.inspection_date,
            s.is_closed,
            m.sample_sequence,
            m.sample_label,
            m.sample_value,
            m.created_date
        FROM dtc_inspection_sessions s
        LEFT JOIN dtc_measurements m ON s.session_id = m.session_id
        WHERE s.parameter_id = :param_id 
          AND DATE_FORMAT(s.inspection_date, '%Y-%m') = :month
          AND s.is_active = 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':param_id' => $param_id, ':month' => $month]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by day to calculate missing
    $daysData = [];
    $late_count = 0;
    
    foreach ($rows as $row) {
        $day = $row['inspection_date'];
        if (!isset($daysData[$day])) {
            $daysData[$day] = [
                'is_closed' => (int)$row['is_closed'] === 1,
                'filled' => 0
            ];
        }
        
        if ($row['sample_value'] !== null && trim($row['sample_value']) !== '') {
            $daysData[$day]['filled']++;
            
            // Check for late (strict comparison: created_date > inspection_date + 1 day)
            // Assuming time slots, if created_date is the next calendar day, it's late.
            $insp_date_str = $row['inspection_date']; // Y-m-d
            $created_date_str = date('Y-m-d', strtotime($row['created_date']));
            
            // Logic for late check considering shifts that span midnight
            // Typically if input is more than 24 hours after inspection_date, it's definitely late
            // For simplicity, if created_date is at least 1 day after inspection date, it's late.
            if ($created_date_str > $insp_date_str) {
                // If it's just the next day but early morning (e.g. shift 3), it might not be late.
                // Let's refine: if it's > 1 day after, or if time is > 08:00 AM the next day.
                $hours_diff = (strtotime($row['created_date']) - strtotime($insp_date_str)) / 3600;
                if ($hours_diff > 32) { // 32 hours from start of inspection date (00:00) is 08:00 AM next day
                    $late_count++;
                }
            }
        }
    }
    
    // 3. Calculate passed days in the month
    $currentMonth = date('Y-m');
    if ($month === $currentMonth) {
        $daysPassed = (int)date('d');
    } else if ($month > $currentMonth) {
        $daysPassed = 0;
    } else {
        $daysPassed = (int)date('t', strtotime($month . '-01'));
    }
    
    $total_expected_slots = $daysPassed * $slots_per_day;
    $total_filled = 0;
    $excused_slots = 0;
    
    // Calculate missing logic
    $start_date = $month . '-01';
    for ($i = 0; $i < $daysPassed; $i++) {
        $d = date('Y-m-d', strtotime("$start_date + $i days"));
        
        if (isset($daysData[$d])) {
            $f = $daysData[$d]['filled'];
            if ($f > $slots_per_day) $f = $slots_per_day;
            
            $total_filled += $f;
            
            if ($daysData[$d]['is_closed']) {
                $missing_this_day = $slots_per_day - $f;
                $excused_slots += $missing_this_day;
            }
        }
    }
    
    $adjusted_expected_slots = max(1, $total_expected_slots - $excused_slots);
    
    // Performance
    $performance = ($total_filled / $adjusted_expected_slots) * 100;
    if ($performance > 100) $performance = 100;
    
    $missing_count = $adjusted_expected_slots - $total_filled;
    if ($missing_count < 0) $missing_count = 0;
    
    // Determine status color/icon
    $status_color = '#10b981'; // Green default
    $status_icon = 'fa-check-circle';
    if ($performance < 80) {
        $status_color = '#ef4444'; // Red
        $status_icon = 'fa-circle-exclamation';
    } else if ($performance < 95) {
        $status_color = '#f59e0b'; // Yellow
        $status_icon = 'fa-triangle-exclamation';
    }

    $insightText = "<i class=\"fa-solid $status_icon\" style=\"color: $status_color; margin-right: 8px; font-size: 14px;\"></i> <span><strong>Measurement Data Insight:</strong> Terdapat <strong>{$late_count}</strong> input terlambat. <strong>{$missing_count}</strong> slot waktu tidak diisi (sesi <em>Closed</em> diabaikan). Performa keseluruhan bulan ini: <strong style=\"color: $status_color\">" . number_format($performance, 1) . "%</strong>.</span>";

    echo json_encode([
        'success' => true,
        'late_count' => $late_count,
        'missing_count' => $missing_count,
        'performance_pct' => round($performance, 1),
        'insight_html' => $insightText
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php
/**
 * Cron Script: Auto Close Shift & Reset Running Models
 * Target Execution: Daily at 06:40 AM WIB (Asia/Jakarta)
 * 
 * Logic:
 * 1. Automatically locks/closes all inspection sessions & checkpoints for the previous working date (and any open past dates).
 * 2. Deletes/resets saved running models in `dtc_running_models` so operators can re-set active models for the new day.
 * 3. Logs execution summary to `dtc_app_settings` and outputs JSON/CLI result.
 */

// Set Timezone to Western Indonesia Time (WIB / UTC+7)
date_default_timezone_set('Asia/Jakarta');

// Handle relative path inclusion whether run from CLI or Web Server
$configPath = __DIR__ . '/../../../config/config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/config/config.php';
}
if (!file_exists($configPath)) {
    $configPath = 'config/config.php';
}
require_once $configPath;

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: application/json');
}

$nowStr = date('Y-m-d H:i:s');
$todayDate = date('Y-m-d');
$yesterdayDate = date('Y-m-d', strtotime('-1 day'));
$currentMonth = date('Y-m');

try {
    $conn = getDBConnection();
    $conn->beginTransaction();

    // 1. Auto-close all open inspection sessions on or before yesterdayDate
    $stmtClose = $conn->prepare("
        UPDATE dtc_inspection_sessions 
        SET is_closed = 1 
        WHERE inspection_date <= :yesterdayDate 
          AND is_closed = 0
    ");
    $stmtClose->execute([':yesterdayDate' => $yesterdayDate]);
    $closedSessionsCount = $stmtClose->rowCount();

    // 2. Count total sessions closed for yesterdayDate
    $stmtTotalYesterday = $conn->prepare("
        SELECT COUNT(*) FROM dtc_inspection_sessions 
        WHERE inspection_date = :yesterdayDate
    ");
    $stmtTotalYesterday->execute([':yesterdayDate' => $yesterdayDate]);
    $totalYesterdaySessions = $stmtTotalYesterday->fetchColumn();

    // 3. Reset/Delete saved running models so they can be set fresh for the new working day
    $stmtCountRunning = $conn->query("SELECT COUNT(*) FROM dtc_running_models");
    $runningModelsCount = $stmtCountRunning->fetchColumn();

    $stmtResetRunning = $conn->query("DELETE FROM dtc_running_models");
    $deletedRunningCount = $stmtResetRunning->rowCount();

    // 4. Record execution log in dtc_app_settings
    $logData = json_encode([
        'execution_time' => $nowStr,
        'closed_date' => $yesterdayDate,
        'closed_sessions_count' => $closedSessionsCount,
        'total_yesterday_sessions' => $totalYesterdaySessions,
        'reset_running_models_count' => $deletedRunningCount,
        'status' => 'success'
    ]);

    $stmtLog = $conn->prepare("
        INSERT INTO dtc_app_settings (setting_key, setting_value) 
        VALUES ('last_cron_close_shift', :val) 
        ON DUPLICATE KEY UPDATE setting_value = :val, updated_at = CURRENT_TIMESTAMP
    ");
    $stmtLog->execute([':val' => $logData]);

    $conn->commit();

    $result = [
        'status' => 'success',
        'execution_time' => $nowStr,
        'target_closed_date' => $yesterdayDate,
        'closed_past_sessions' => $closedSessionsCount,
        'reset_running_models' => $deletedRunningCount,
        'message' => "Berhasil menutup semua checkpoint/pengukuran untuk tanggal $yesterdayDate ($closedSessionsCount sesi di-close) dan menghapus $deletedRunningCount running model tersimpan."
    ];

    if ($isCli) {
        echo "====================================================\n";
        echo " SYSTEM DIGITAL TIME CHECK - AUTO CLOSE SHIFT CRON  \n";
        echo "====================================================\n";
        echo "Execution Time      : $nowStr WIB\n";
        echo "Closed Working Date : $yesterdayDate\n";
        echo "Closed Sessions     : $closedSessionsCount sesi\n";
        echo "Reset Running Models: $deletedRunningCount record\n";
        echo "Status              : SUCCESS\n";
        echo "====================================================\n";
    } else {
        echo json_encode($result, JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorResult = [
        'status' => 'error',
        'execution_time' => $nowStr,
        'message' => $e->getMessage()
    ];

    if ($isCli) {
        echo "ERROR: " . $e->getMessage() . "\n";
    } else {
        echo json_encode($errorResult, JSON_PRETTY_PRINT);
    }
}

<?php
// Script/php/dtc/c_oos_summary.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if ($userRole !== 'admin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak. Fitur Quick Update Out of Spec ini hanya dapat diakses oleh Admin.'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get';
    $month = $_GET['month'] ?? $_POST['month'] ?? date('Y-m');

    if ($action === 'get_param_oos') {
        $paramId = (int)($_GET['parameter_id'] ?? 0);
        if (!$paramId) {
            echo json_encode(['status' => 'error', 'message' => 'Parameter ID is required.']);
            exit;
        }

        // 1. Fetch Parameter Info
        $sqlParam = "
            SELECT p.parameter_id, p.target_month,
                   COALESCE(p.model_name, spec.model_name) as model_name,
                   COALESCE(p.line_name, spec.line_name) as line_name,
                   COALESCE(p.section_name, spec.section_name) as section_name,
                   COALESCE(p.process_name, spec.process_name) as process_name,
                   COALESCE(p.item_check_name, spec.item_check_name) as item_check_name,
                   COALESCE(p.sub_item_check_name, spec.sub_item_check_name) as sub_item_check_name,
                   COALESCE(p.data_type, spec.data_type) as data_type,
                   COALESCE(p.measuring_item, spec.measuring_item) as measuring_item,
                   COALESCE(p.lsl, spec.lsl) as lsl,
                   COALESCE(p.usl, spec.usl) as usl,
                   COALESCE(p.target_value, spec.target_value) as target_value
            FROM dtc_master_parameters p
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE p.parameter_id = :pid
        ";
        $stmtP = $conn->prepare($sqlParam);
        $stmtP->execute([':pid' => $paramId]);
        $parameter = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$parameter) {
            echo json_encode(['status' => 'error', 'message' => 'Parameter not found.']);
            exit;
        }

        $date = $_GET['date'] ?? $_POST['date'] ?? '';

        // 2. Fetch OOS Sessions for this parameter
        $paramsS = [':pid' => $paramId];
        $dateCondition = "";
        if (!empty($month)) {
            $dateCondition = " AND DATE_FORMAT(s.inspection_date, '%Y-%m') = :imonth ";
            $paramsS[':imonth'] = $month;
        } elseif (!empty($date)) {
            $dateCondition = " AND s.inspection_date = :idate ";
            $paramsS[':idate'] = $date;
        } else {
            $targetMonth = $parameter['target_month'] ?? date('Y-m');
            $dateCondition = " AND DATE_FORMAT(s.inspection_date, '%Y-%m') = :imonth ";
            $paramsS[':imonth'] = $targetMonth;
        }

        $sqlSessions = "
            SELECT DISTINCT s.session_id, DATE_FORMAT(s.inspection_date, '%Y-%m-%d %H:%i') as inspection_date_str,
                            s.inspection_date, s.min_value, s.max_value, s.remarks
            FROM dtc_inspection_sessions s
            JOIN dtc_measurements m ON s.session_id = m.session_id
            JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            LEFT JOIN dtc_checkpoints c ON m.checkpoint_id = c.checkpoint_id
            WHERE s.parameter_id = :pid
              AND s.is_active = 1
              {$dateCondition}
              AND (
                  CASE 
                      WHEN c.lsl IS NOT NULL OR c.usl IS NOT NULL THEN (
                          m.sample_value IS NOT NULL AND TRIM(m.sample_value) != '' AND m.sample_value REGEXP '^[0-9.-]+$'
                          AND (
                              (c.lsl IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) < c.lsl)
                              OR
                              (c.usl IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) > c.usl)
                          )
                      )
                      WHEN (UPPER(TRIM(COALESCE(p.data_type, spec.data_type))) IN ('TIME CHECK', 'F/PROOF')
                            OR LOWER(TRIM(COALESCE(p.measuring_item, spec.measuring_item))) = 'qualitative') THEN (
                          UPPER(TRIM(m.sample_value)) = 'NG'
                      )
                      ELSE (
                          (UPPER(TRIM(m.sample_value)) = 'NG')
                          OR (
                              m.sample_value IS NOT NULL AND TRIM(m.sample_value) != '' AND m.sample_value REGEXP '^[0-9.-]+$'
                              AND (
                                  (COALESCE(p.lsl, spec.lsl) IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) < COALESCE(p.lsl, spec.lsl))
                                  OR
                                  (COALESCE(p.usl, spec.usl) IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) > COALESCE(p.usl, spec.usl))
                              )
                          )
                      )
                  END
              )
            ORDER BY s.inspection_date DESC
        ";

        $stmtS = $conn->prepare($sqlSessions);
        $stmtS->execute($paramsS);
        $sessions = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        // Fetch OOS measurements for each session including checkpoint info and spec thresholds
        foreach ($sessions as &$sess) {
            $stmtM = $conn->prepare("
                SELECT m.measurement_id, m.session_id, m.checkpoint_id, m.sample_sequence, m.sample_label, m.sample_value,
                       c.checkpoint_name, c.reference_image as cp_reference_image,
                       CASE 
                           WHEN c.lsl IS NOT NULL THEN c.lsl
                           WHEN UPPER(TRIM(COALESCE(p.data_type, spec.data_type))) IN ('TIME CHECK', 'F/PROOF')
                                OR LOWER(TRIM(COALESCE(p.measuring_item, spec.measuring_item))) = 'qualitative' THEN NULL
                           ELSE COALESCE(p.lsl, spec.lsl)
                       END as lsl,
                       CASE 
                           WHEN c.usl IS NOT NULL THEN c.usl
                           WHEN UPPER(TRIM(COALESCE(p.data_type, spec.data_type))) IN ('TIME CHECK', 'F/PROOF')
                                OR LOWER(TRIM(COALESCE(p.measuring_item, spec.measuring_item))) = 'qualitative' THEN NULL
                           ELSE COALESCE(p.usl, spec.usl)
                       END as usl,
                       COALESCE(c.target_value, p.target_value, spec.target_value) as target_value
                FROM dtc_measurements m
                JOIN dtc_inspection_sessions s ON m.session_id = s.session_id
                JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
                LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
                LEFT JOIN dtc_checkpoints c ON m.checkpoint_id = c.checkpoint_id
                WHERE m.session_id = :sid 
                  AND (
                      CASE 
                          WHEN c.lsl IS NOT NULL OR c.usl IS NOT NULL THEN (
                              m.sample_value IS NOT NULL AND TRIM(m.sample_value) != '' AND m.sample_value REGEXP '^[0-9.-]+$'
                              AND (
                                  (c.lsl IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) < c.lsl)
                                  OR
                                  (c.usl IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) > c.usl)
                              )
                          )
                          WHEN (UPPER(TRIM(COALESCE(p.data_type, spec.data_type))) IN ('TIME CHECK', 'F/PROOF')
                                OR LOWER(TRIM(COALESCE(p.measuring_item, spec.measuring_item))) = 'qualitative') THEN (
                              UPPER(TRIM(m.sample_value)) = 'NG'
                          )
                          ELSE (
                              (UPPER(TRIM(m.sample_value)) = 'NG')
                              OR (
                                  m.sample_value IS NOT NULL AND TRIM(m.sample_value) != '' AND m.sample_value REGEXP '^[0-9.-]+$'
                                  AND (
                                      (COALESCE(p.lsl, spec.lsl) IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) < COALESCE(p.lsl, spec.lsl))
                                      OR
                                      (COALESCE(p.usl, spec.usl) IS NOT NULL AND CAST(m.sample_value AS DECIMAL(10,4)) > COALESCE(p.usl, spec.usl))
                                  )
                              )
                          )
                      END
                  )
                ORDER BY m.sample_sequence ASC, m.measurement_id ASC
            ");
            $stmtM->execute([':sid' => $sess['session_id']]);
            $sess['measurements'] = $stmtM->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'status' => 'success',
            'parameter' => $parameter,
            'sessions' => $sessions
        ]);
        exit;
    }

    if ($action === 'save_param_oos_update') {
        $sessionsJson = $_POST['sessions'] ?? '[]';
        $sessions = json_decode($sessionsJson, true);

        if (!is_array($sessions) || empty($sessions)) {
            echo json_encode(['status' => 'error', 'message' => 'Tidak ada data sesi pengukuran yang dikirim.']);
            exit;
        }

        $conn->beginTransaction();

        $stmtUpdateMeas = $conn->prepare("
            UPDATE dtc_measurements 
            SET sample_value = :val, modified_date = NOW() 
            WHERE measurement_id = :mid AND session_id = :sid
        ");
        $stmtUpdSess = $conn->prepare("
            UPDATE dtc_inspection_sessions 
            SET min_value = :min_v, max_value = :max_v, remarks = :rem 
            WHERE session_id = :sid
        ");

        foreach ($sessions as $sess) {
            $sid = (int)($sess['session_id'] ?? 0);
            $remarks = trim($sess['remarks'] ?? '');
            $measList = $sess['measurements'] ?? [];

            if ($sid <= 0) continue;

            $numericVals = [];
            if (is_array($measList)) {
                foreach ($measList as $m) {
                    $mid = (int)($m['measurement_id'] ?? 0);
                    $val = trim($m['sample_value'] ?? '');
                    if ($mid > 0) {
                        $stmtUpdateMeas->execute([
                            ':val' => ($val === '' ? null : $val),
                            ':mid' => $mid,
                            ':sid' => $sid
                        ]);
                        if ($val !== '' && is_numeric($val)) {
                            $numericVals[] = (float)$val;
                        }
                    }
                }
            }

            $minVal = !empty($numericVals) ? min($numericVals) : null;
            $maxVal = !empty($numericVals) ? max($numericVals) : null;

            $stmtUpdSess->execute([
                ':min_v' => $minVal,
                ':max_v' => $maxVal,
                ':rem' => $remarks,
                ':sid' => $sid
            ]);
        }

        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => 'Data pengukuran Out of Spec berhasil di-update.']);
        exit;
    }

    if ($action === 'get_session_details') {
        $sessionId = (int)($_GET['session_id'] ?? 0);
        if (!$sessionId) {
            echo json_encode(['status' => 'error', 'message' => 'Session ID is required.']);
            exit;
        }

        $sql = "
            SELECT s.session_id, s.parameter_id, DATE_FORMAT(s.inspection_date, '%Y-%m-%d') as inspection_date, 
                   s.min_value, s.max_value, s.remarks, s.is_active,
                   COALESCE(p.model_name, spec.model_name) as model_name,
                   COALESCE(p.line_name, spec.line_name) as line_name,
                   COALESCE(p.section_name, spec.section_name) as section_name,
                   COALESCE(p.process_name, spec.process_name) as process_name,
                   COALESCE(p.item_check_name, spec.item_check_name) as item_check_name,
                   COALESCE(p.sub_item_check_name, spec.sub_item_check_name) as sub_item_check_name,
                   COALESCE(p.data_type, spec.data_type) as data_type,
                   COALESCE(p.measuring_item, spec.measuring_item) as measuring_item,
                   COALESCE(p.lsl, spec.lsl) as lsl,
                   COALESCE(p.usl, spec.usl) as usl,
                   COALESCE(p.target_value, spec.target_value) as target_value
            FROM dtc_inspection_sessions s
            JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE s.session_id = :sid
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':sid' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            echo json_encode(['status' => 'error', 'message' => 'Session not found.']);
            exit;
        }

        $stmtMeas = $conn->prepare("
            SELECT measurement_id, session_id, sample_sequence, sample_label, sample_value 
            FROM dtc_measurements 
            WHERE session_id = :sid 
            ORDER BY sample_sequence ASC, measurement_id ASC
        ");
        $stmtMeas->execute([':sid' => $sessionId]);
        $measurements = $stmtMeas->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'session' => $session,
            'measurements' => $measurements
        ]);
        exit;
    }

    if ($action === 'save_session_update') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        $measurementsJson = $_POST['measurements'] ?? '[]';
        $measurements = json_decode($measurementsJson, true);

        if (!$sessionId) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid session ID.']);
            exit;
        }

        $stmtSess = $conn->prepare("
            SELECT s.*, COALESCE(p.lsl, spec.lsl) as lsl, COALESCE(p.usl, spec.usl) as usl
            FROM dtc_inspection_sessions s
            JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
            LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
            WHERE s.session_id = :sid
        ");
        $stmtSess->execute([':sid' => $sessionId]);
        $sessRow = $stmtSess->fetch(PDO::FETCH_ASSOC);

        if (!$sessRow) {
            echo json_encode(['status' => 'error', 'message' => 'Session not found.']);
            exit;
        }

        $conn->beginTransaction();

        $numericVals = [];
        if (is_array($measurements) && !empty($measurements)) {
            $stmtUpdateMeas = $conn->prepare("
                UPDATE dtc_measurements 
                SET sample_value = :val, modified_date = NOW() 
                WHERE measurement_id = :mid AND session_id = :sid
            ");
            foreach ($measurements as $m) {
                $mid = (int)($m['measurement_id'] ?? 0);
                $val = trim($m['sample_value'] ?? '');
                if ($mid > 0) {
                    $stmtUpdateMeas->execute([
                        ':val' => ($val === '' ? null : $val),
                        ':mid' => $mid,
                        ':sid' => $sessionId
                    ]);
                    if ($val !== '' && is_numeric($val)) {
                        $numericVals[] = (float)$val;
                    }
                }
            }
        }

        $minVal = !empty($numericVals) ? min($numericVals) : null;
        $maxVal = !empty($numericVals) ? max($numericVals) : null;

        $stmtUpdSess = $conn->prepare("
            UPDATE dtc_inspection_sessions 
            SET min_value = :min_v, max_value = :max_v, remarks = :rem 
            WHERE session_id = :sid
        ");
        $stmtUpdSess->execute([
            ':min_v' => $minVal,
            ':max_v' => $maxVal,
            ':rem' => $remarks,
            ':sid' => $sessionId
        ]);

        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => 'Data pengukuran berhasil di-update.']);
        exit;
    }

    // Default action: get OOS list
    $sql = "
        SELECT 
            s.session_id,
            DATE_FORMAT(s.inspection_date, '%Y-%m-%d') as inspection_date, 
            p.parameter_id,
            COALESCE(p.model_name, spec.model_name) as model_name,
            COALESCE(p.line_name, spec.line_name) as line_name,
            COALESCE(p.section_name, spec.section_name) as section_name,
            COALESCE(p.process_name, spec.process_name) as process_name,
            COALESCE(p.item_check_name, spec.item_check_name) as item_check_name,
            COALESCE(p.sub_item_check_name, spec.sub_item_check_name) as sub_item_check_name,
            COALESCE(p.data_type, spec.data_type) as data_type,
            COALESCE(p.measuring_item, spec.measuring_item) as measuring_item,
            COALESCE(p.lsl, spec.lsl) as lsl,
            COALESCE(p.usl, spec.usl) as usl,
            COALESCE(p.target_value, spec.target_value) as target_value,
            s.min_value, s.max_value, s.remarks,
            CASE 
                WHEN LOWER(TRIM(COALESCE(p.measuring_item, spec.measuring_item))) = 'qualitative' THEN 'NG'
                WHEN COALESCE(p.lsl, spec.lsl) IS NOT NULL AND s.min_value < COALESCE(p.lsl, spec.lsl) THEN 'Below LSL'
                WHEN COALESCE(p.usl, spec.usl) IS NOT NULL AND s.max_value > COALESCE(p.usl, spec.usl) THEN 'Above USL'
                ELSE 'Out of Spec'
            END as oos_type
        FROM dtc_inspection_sessions s
        JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
        LEFT JOIN dtc_master_dtc_specs spec ON p.spec_id = spec.spec_id
        WHERE s.is_active = 1
          AND DATE_FORMAT(s.inspection_date, '%Y-%m') = :month
          AND (
              (LOWER(TRIM(COALESCE(p.measuring_item, spec.measuring_item))) != 'qualitative' 
               AND ((COALESCE(p.lsl, spec.lsl) IS NOT NULL AND s.min_value < COALESCE(p.lsl, spec.lsl))
                    OR (COALESCE(p.usl, spec.usl) IS NOT NULL AND s.max_value > COALESCE(p.usl, spec.usl))))
              OR
              (EXISTS (
                  SELECT 1 FROM dtc_measurements m2 
                  WHERE m2.session_id = s.session_id 
                    AND UPPER(TRIM(m2.sample_value)) = 'NG'
              ))
          )
          " . getIPAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
          " . getUserAccessFilterSQL('COALESCE(p.line_name, spec.line_name)', 'COALESCE(p.section_name, spec.section_name)') . "
        ORDER BY s.inspection_date DESC, s.session_id DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':month' => $month]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'total_oos' => count($results),
        'below_lsl' => 0,
        'above_usl' => 0,
        'qualitative_ng' => 0
    ];

    foreach ($results as $r) {
        if ($r['oos_type'] === 'Below LSL') $stats['below_lsl']++;
        else if ($r['oos_type'] === 'Above USL') $stats['above_usl']++;
        else if ($r['oos_type'] === 'NG') $stats['qualitative_ng']++;
    }
    
    echo json_encode([
        'status' => 'success',
        'month' => $month,
        'stats' => $stats,
        'data' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

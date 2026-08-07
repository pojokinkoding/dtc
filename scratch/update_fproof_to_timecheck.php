<?php
require_once __DIR__ . '/../config/config.php';

try {
    $conn = getDBConnection();
    $conn->beginTransaction();

    echo "=== BEFORE UPDATE ===\n";
    $stmtSpecBeforeF = $conn->query("SELECT COUNT(*) FROM dtc_master_dtc_specs WHERE data_type = 'F/Proof'");
    $specFBefore = $stmtSpecBeforeF->fetchColumn();
    $stmtParamBeforeF = $conn->query("SELECT COUNT(*) FROM dtc_master_parameters WHERE data_type = 'F/Proof'");
    $paramFBefore = $stmtParamBeforeF->fetchColumn();

    echo "dtc_master_dtc_specs F/Proof count: $specFBefore\n";
    echo "dtc_master_parameters F/Proof count: $paramFBefore\n";

    // 1. UPDATE dtc_master_dtc_specs
    $sqlSpecUpdate = "
        UPDATE dtc_master_dtc_specs 
        SET data_type = 'Time Check'
        WHERE data_type = 'F/Proof' 
          AND NOT (TRIM(UPPER(section_name)) = 'PRE CASE' AND TRIM(UPPER(item_check_name)) = 'SCREW HINGE LOWER')
    ";
    $stmtSpecUp = $conn->prepare($sqlSpecUpdate);
    $stmtSpecUp->execute();
    $specUpdatedCount = $stmtSpecUp->rowCount();

    // 2. UPDATE dtc_master_parameters
    $sqlParamUpdate = "
        UPDATE dtc_master_parameters 
        SET data_type = 'Time Check'
        WHERE data_type = 'F/Proof' 
          AND NOT (TRIM(UPPER(section_name)) = 'PRE CASE' AND TRIM(UPPER(item_check_name)) = 'SCREW HINGE LOWER')
    ";
    $stmtParamUp = $conn->prepare($sqlParamUpdate);
    $stmtParamUp->execute();
    $paramUpdatedCount = $stmtParamUp->rowCount();

    $conn->commit();

    echo "\n=== AFTER UPDATE ===\n";
    $stmtSpecAfterF = $conn->query("SELECT COUNT(*) FROM dtc_master_dtc_specs WHERE data_type = 'F/Proof'");
    $specFAfter = $stmtSpecAfterF->fetchColumn();
    $stmtParamAfterF = $conn->query("SELECT COUNT(*) FROM dtc_master_parameters WHERE data_type = 'F/Proof'");
    $paramFAfter = $stmtParamAfterF->fetchColumn();

    $stmtSpecAfterTC = $conn->query("SELECT COUNT(*) FROM dtc_master_dtc_specs WHERE data_type = 'Time Check'");
    $specTCAfter = $stmtSpecAfterTC->fetchColumn();
    $stmtParamAfterTC = $conn->query("SELECT COUNT(*) FROM dtc_master_parameters WHERE data_type = 'Time Check'");
    $paramTCAfter = $stmtParamAfterTC->fetchColumn();

    echo "dtc_master_dtc_specs updated to 'Time Check': $specUpdatedCount (Remaining F/Proof: $specFAfter, Total Time Check: $specTCAfter)\n";
    echo "dtc_master_parameters updated to 'Time Check': $paramUpdatedCount (Remaining F/Proof: $paramFAfter, Total Time Check: $paramTCAfter)\n";

    echo "\n--- Remaining F/Proof Specs ---\n";
    $remSpecs = $conn->query("SELECT spec_id, section_name, line_name, process_name, item_check_name, data_type FROM dtc_master_dtc_specs WHERE data_type = 'F/Proof'")->fetchAll(PDO::FETCH_ASSOC);
    print_r($remSpecs);

    echo "\n--- Sample Remaining F/Proof Parameters ---\n";
    $remParams = $conn->query("SELECT DISTINCT section_name, line_name, process_name, item_check_name, data_type, model_name FROM dtc_master_parameters WHERE data_type = 'F/Proof'")->fetchAll(PDO::FETCH_ASSOC);
    print_r($remParams);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
}

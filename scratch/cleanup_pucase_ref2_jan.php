<?php
// Delete and reimport CTP PU Case January 2026 REF 02 - fixing item check names
require_once __DIR__ . '/../config/config.php';
$conn = getDBConnection();

echo "Deleting CTP PU Case REF 02 January 2026 data for reimport...\n";

// Get parameters for Jan 2026, REF 02, PU Case
$stmt = $conn->prepare("SELECT parameter_id FROM dtc_master_parameters WHERE target_month = '2026-01' AND line_name = 'REF 02' AND section_name = 'PU Case' AND data_type = 'CTP'");
$stmt->execute();
$paramIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Found " . count($paramIds) . " parameters to delete.\n";

if (!empty($paramIds)) {
    $placeholders = implode(',', array_fill(0, count($paramIds), '?'));
    
    // Delete measurements
    $stmt2 = $conn->prepare("DELETE m FROM dtc_measurements m JOIN dtc_inspection_sessions s ON m.session_id = s.session_id WHERE s.parameter_id IN ($placeholders)");
    $stmt2->execute($paramIds);
    echo "Deleted measurements: " . $stmt2->rowCount() . "\n";
    
    // Delete sessions
    $stmt3 = $conn->prepare("DELETE FROM dtc_inspection_sessions WHERE parameter_id IN ($placeholders)");
    $stmt3->execute($paramIds);
    echo "Deleted sessions: " . $stmt3->rowCount() . "\n";
    
    // Delete parameters
    $stmt4 = $conn->prepare("DELETE FROM dtc_master_parameters WHERE parameter_id IN ($placeholders)");
    $stmt4->execute($paramIds);
    echo "Deleted parameters: " . $stmt4->rowCount() . "\n";
}

// Delete master specs for REF 02 PU Case (spec IDs 824-842)
$specStmt = $conn->prepare("SELECT spec_id FROM dtc_master_dtc_specs WHERE line_name = 'REF 02' AND section_name = 'PU Case' AND data_type = 'CTP'");
$specStmt->execute();
$specIds = $specStmt->fetchAll(PDO::FETCH_COLUMN);
echo "Found " . count($specIds) . " master specs to delete.\n";

if (!empty($specIds)) {
    $placeholders = implode(',', array_fill(0, count($specIds), '?'));
    $stmt5 = $conn->prepare("DELETE FROM dtc_master_dtc_specs WHERE spec_id IN ($placeholders)");
    $stmt5->execute($specIds);
    echo "Deleted master specs: " . $stmt5->rowCount() . "\n";
}

// Delete running models
$rmStmt = $conn->prepare("DELETE FROM dtc_running_models WHERE target_month = '2026-01' AND line_name = 'REF 02' AND section_name = 'PU Case'");
$rmStmt->execute();
echo "Deleted running models: " . $rmStmt->rowCount() . "\n";

echo "\nCleanup done. Ready to reimport.\n";

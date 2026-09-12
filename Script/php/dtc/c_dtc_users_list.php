<?php
require_once __DIR__ . '/../../../config/config.php';
header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Check if allowed_sections column exists
    $hasAllowedSec = false;
    try {
        $chk = $conn->query("SHOW COLUMNS FROM dtc_users LIKE 'allowed_sections'")->fetch();
        if ($chk) $hasAllowedSec = true;
    } catch (Throwable $t) {}

    $colAllowedSec = $hasAllowedSec ? ", allowed_sections" : ", '' AS allowed_sections";
    $sql = "SELECT user_id, username, full_name, role, profile_picture, line_name, section_name $colAllowedSec FROM dtc_users ORDER BY user_id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["data" => $results], JSON_INVALID_UTF8_SUBSTITUTE);
    
} catch (Throwable $e) {
    echo json_encode(["data" => [], "error" => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
}
?>

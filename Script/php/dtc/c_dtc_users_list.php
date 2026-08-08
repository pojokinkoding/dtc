<?php
// c_dtc_users_list.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    $sql = "SELECT user_id, username, full_name, role, profile_picture, line_name, section_name, allowed_sections FROM dtc_users ORDER BY user_id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["data" => $results]);
    
} catch (Exception $e) {
    echo json_encode(["data" => [], "error" => $e->getMessage()]);
}
?>

<?php
// c_master_spec_list.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    $sql = "SELECT spec_id, model_name, item_check_name, sub_item_check_name, data_type, lsl, usl, uom, target_value, 
                   section_name, line_name, process_name, measuring_item, target_zst, target_zlt 
            FROM dtc_master_dtc_specs 
            WHERE 1=1 " . getIPAccessFilterSQL('line_name', 'section_name') . getUserAccessFilterSQL('line_name', 'section_name') . "
            ORDER BY spec_id DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(["data" => $results]);
    
} catch (Exception $e) {
    echo json_encode(["data" => [], "error" => $e->getMessage()]);
}
?>

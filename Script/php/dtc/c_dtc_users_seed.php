<?php
// c_dtc_users_seed.php
require_once '../../../config/config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Check if any users exist
    $stmt = $conn->query("SELECT COUNT(*) FROM dtc_users");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Users already exist.']);
        exit;
    }
    
    // Insert initial admin user
    $username = 'admin';
    $password = 'admin123';
    $full_name = 'System Administrator';
    $role = 'Admin';
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO dtc_users (username, password_hash, full_name, role) VALUES (:username, :hash, :full_name, :role)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':hash' => $hash,
        ':full_name' => $full_name,
        ':role' => $role
    ]);
    
    echo json_encode(['status' => 'success', 'message' => 'Initial admin user created successfully.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

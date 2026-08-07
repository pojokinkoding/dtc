<?php
// c_dtc_users_delete.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDBConnection();
    
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        throw new Exception("Invalid user ID.");
    }
    
    // Check if this is the only admin left?
    // We will skip complex logic for now, but prevent deleting the active user is good if they use session
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
        throw new Exception("You cannot delete your own account while logged in.");
    }
    
    // Get old profile picture to delete it
    $stmtOldPic = $conn->prepare("SELECT profile_picture FROM dtc_users WHERE user_id = :user_id");
    $stmtOldPic->execute([':user_id' => $user_id]);
    $oldPic = $stmtOldPic->fetchColumn();
    if ($oldPic && file_exists('../../../uploads/profiles/' . $oldPic)) {
        unlink('../../../uploads/profiles/' . $oldPic);
    }
    
    $sql = "DELETE FROM dtc_users WHERE user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    
    echo json_encode(["status" => "success", "message" => "User successfully deleted."]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

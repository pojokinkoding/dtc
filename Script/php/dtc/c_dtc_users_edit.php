<?php
// c_dtc_users_edit.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDBConnection();
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($user_id <= 0 || empty($username) || empty($full_name) || empty($role)) {
        throw new Exception("Missing required fields.");
    }
    
    // Check if username exists for OTHER users
    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM dtc_users WHERE username = :username AND user_id != :user_id");
    $stmtCheck->execute([':username' => $username, ':user_id' => $user_id]);
    if ($stmtCheck->fetchColumn() > 0) {
        throw new Exception("Username already exists.");
    }
    
    // Handle Profile Picture Upload
    $profile_picture = null;
    $hasNewProfilePic = false;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
        $fileName = $_FILES['profile_picture']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = '../../../uploads/profiles/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            $newFileName = md5(time() . $username) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $profile_picture = $newFileName;
                $hasNewProfilePic = true;
                
                // Get old profile picture to delete it
                $stmtOldPic = $conn->prepare("SELECT profile_picture FROM dtc_users WHERE user_id = :user_id");
                $stmtOldPic->execute([':user_id' => $user_id]);
                $oldPic = $stmtOldPic->fetchColumn();
                if ($oldPic && file_exists($uploadFileDir . $oldPic)) {
                    unlink($uploadFileDir . $oldPic);
                }
            } else {
                throw new Exception("Error moving the uploaded file.");
            }
        } else {
            throw new Exception("Upload failed. Allowed file types: " . implode(',', $allowedfileExtensions));
        }
    }
    
    $params = [
        ':username' => $username,
        ':full_name' => $full_name,
        ':role' => $role,
        ':user_id' => $user_id,
        ':line_name' => !empty($_POST['line_name']) ? $_POST['line_name'] : null,
        ':section_name' => !empty($_POST['section_name']) ? $_POST['section_name'] : null
    ];
    
    $sqlSets = ["username = :username", "full_name = :full_name", "role = :role", "line_name = :line_name", "section_name = :section_name", "updated_at = CURRENT_TIMESTAMP"];
    
    if (!empty($password)) {
        $params[':hash'] = password_hash($password, PASSWORD_DEFAULT);
        $sqlSets[] = "password_hash = :hash";
    }
    
    if ($hasNewProfilePic) {
        $params[':profile_picture'] = $profile_picture;
        $sqlSets[] = "profile_picture = :profile_picture";
    }
    
    $sql = "UPDATE dtc_users SET " . implode(", ", $sqlSets) . " WHERE user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    // Update session if the logged-in user edited their own profile
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
        $_SESSION['full_name'] = $full_name;
        $_SESSION['role'] = $role;
        $_SESSION['username'] = $username;
        $_SESSION['line_name'] = $params[':line_name'];
        $_SESSION['section_name'] = $params[':section_name'];
        if ($hasNewProfilePic) {
            $_SESSION['profile_picture'] = $profile_picture;
        }
    }
    
    echo json_encode(["status" => "success", "message" => "User successfully updated."]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

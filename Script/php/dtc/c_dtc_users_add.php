<?php
// c_dtc_users_add.php
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDBConnection();
    
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($full_name) || empty($role) || empty($password)) {
        throw new Exception("All fields are required.");
    }
    
    // Check if username exists
    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM dtc_users WHERE username = :username");
    $stmtCheck->execute([':username' => $username]);
    if ($stmtCheck->fetchColumn() > 0) {
        throw new Exception("Username already exists.");
    }
    
    // Handle Profile Picture Upload
    $profile_picture = null;
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
            } else {
                throw new Exception("Error moving the uploaded file.");
            }
        } else {
            throw new Exception("Upload failed. Allowed file types: " . implode(',', $allowedfileExtensions));
        }
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $line_name = !empty($_POST['line_name']) ? $_POST['line_name'] : null;
    $section_name = !empty($_POST['section_name']) ? $_POST['section_name'] : null;
    
    $allowed_sections = null;
    if (!empty($_POST['allowed_sections'])) {
        if (is_array($_POST['allowed_sections'])) {
            $allowed_sections = implode(',', array_map('trim', $_POST['allowed_sections']));
        } else {
            $allowed_sections = trim($_POST['allowed_sections']);
        }
    }
    
    $sql = "INSERT INTO dtc_users (username, password_hash, full_name, role, profile_picture, line_name, section_name, allowed_sections) VALUES (:username, :hash, :full_name, :role, :profile_picture, :line_name, :section_name, :allowed_sections)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':hash' => $hash,
        ':full_name' => $full_name,
        ':role' => $role,
        ':profile_picture' => $profile_picture,
        ':line_name' => $line_name,
        ':section_name' => $section_name,
        ':allowed_sections' => $allowed_sections
    ]);
    
    echo json_encode(["status" => "success", "message" => "User successfully added."]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

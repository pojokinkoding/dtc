<?php
// c_dtc_add.php - Auto Generate DTC Parameters per Line & Section for Current Month
require_once '../../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Always target current month
    $target_month = date('Y-m');
    $line_name = trim($_POST['line_name'] ?? '');
    $section_name = trim($_POST['section_name'] ?? '');

    if (empty($line_name) || empty($section_name)) {
        throw new Exception("Harap pilih Line dan Section.");
    }

    // Query specs matching selected Line and Section
    $sql_specs = "SELECT * FROM dtc_master_dtc_specs 
                  WHERE (UPPER(line_name) = UPPER(:line_name) OR :line_all = 'ALL')
                    AND (UPPER(section_name) = UPPER(:section_name) OR :sec_all = 'ALL')
                  ORDER BY spec_id ASC";
    $stmt_specs = $conn->prepare($sql_specs);
    $stmt_specs->execute([
        ':line_name' => $line_name,
        ':line_all' => strtoupper($line_name),
        ':section_name' => $section_name,
        ':sec_all' => strtoupper($section_name)
    ]);
    $specs = $stmt_specs->fetchAll(PDO::FETCH_ASSOC);

    if (empty($specs)) {
        throw new Exception("Tidak ditemukan Master Spec untuk Line '$line_name' dan Section '$section_name'.");
    }

    // Handle reference image upload
    $reference_image = null;
    if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/dtc/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext = strtolower(pathinfo($_FILES['reference_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            throw new Exception("Format gambar tidak valid. Format yang diizinkan: jpg, jpeg, png, gif, webp");
        }
        $filename = 'ref_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $target_path = $upload_dir . $filename;
        if (!move_uploaded_file($_FILES['reference_image']['tmp_name'], $target_path)) {
            throw new Exception("Gagal mengunggah gambar acuan.");
        }
        $reference_image = 'uploads/dtc/' . $filename;
    }

    $sql_check = "SELECT parameter_id FROM dtc_master_parameters 
                  WHERE target_month = :target_month 
                    AND (
                      spec_id = :spec_id 
                      OR (
                        UPPER(line_name) = UPPER(:line_name) 
                        AND UPPER(section_name) = UPPER(:section_name) 
                        AND UPPER(process_name) = UPPER(:process_name) 
                        AND UPPER(item_check_name) = UPPER(:item_check_name) 
                        AND UPPER(COALESCE(sub_item_check_name, '')) = UPPER(:sub_item_check_name)
                      )
                    )
                  LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);

    $sql_insert = "INSERT INTO dtc_master_parameters 
            (spec_id, target_month, item_check_name, sub_item_check_name, data_type, line_name, section_name, process_name, measuring_item, target_zst, target_zlt, reference_image) 
            VALUES (:spec_id, :target_month, :item_check_name, :sub_item_check_name, :data_type, :line_name, :section_name, :process_name, :measuring_item, :target_zst, :target_zlt, :reference_image)";
    $stmt_insert = $conn->prepare($sql_insert);

    $addedCount = 0;
    $skippedCount = 0;

    foreach ($specs as $spec) {
        $subItem = $spec['sub_item_check_name'] ?? '';
        $stmt_check->execute([
            ':spec_id' => $spec['spec_id'],
            ':target_month' => $target_month,
            ':line_name' => $spec['line_name'],
            ':section_name' => $spec['section_name'],
            ':process_name' => $spec['process_name'],
            ':item_check_name' => $spec['item_check_name'],
            ':sub_item_check_name' => $subItem
        ]);

        if ($stmt_check->fetchColumn()) {
            $skippedCount++;
            continue;
        }

        $refImgToUse = $reference_image ? $reference_image : ($spec['reference_image'] ?? null);

        $stmt_insert->execute([
            ':spec_id' => $spec['spec_id'],
            ':target_month' => $target_month,
            ':item_check_name' => $spec['item_check_name'],
            ':sub_item_check_name' => $spec['sub_item_check_name'],
            ':data_type' => $spec['data_type'],
            ':line_name' => $spec['line_name'],
            ':section_name' => $spec['section_name'],
            ':process_name' => $spec['process_name'],
            ':measuring_item' => $spec['measuring_item'],
            ':target_zst' => $spec['target_zst'],
            ':target_zlt' => $spec['target_zlt'],
            ':reference_image' => $refImgToUse
        ]);
        $addedCount++;
    }

    if ($addedCount === 0 && $skippedCount > 0) {
        echo json_encode([
            "status" => "warning", 
            "message" => "Tidak ada parameter baru yang dibuat. Seluruh $skippedCount DTC Parameter untuk $line_name - $section_name pada bulan $target_month sudah duplikat/pernah dibuat sebelumnya."
        ]);
        exit;
    }

    $msg = "Berhasil membuat $addedCount DTC Parameter baru untuk $line_name - $section_name (Bulan Ini: $target_month).";
    if ($skippedCount > 0) {
        $msg .= " ($skippedCount item yang sudah duplikat/ada diabaikan).";
    }

    echo json_encode(["status" => "success", "message" => $msg]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>

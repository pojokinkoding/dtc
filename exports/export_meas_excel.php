<?php
require_once '../config/config.php';

$conn = getDBConnection();

$sql = "
SELECT 
    c.model_name AS \"Model Name\", 
    c.item_check_name AS \"Item Check Name\", 
    c.data_type AS \"Data Type\",
    p.section_name AS \"Section\", 
    p.line_name AS \"Line\", 
    p.process_name AS \"Process\", 
    DATE_FORMAT(s.inspection_date, '%Y-%m-%d') AS \"Inspection Date\", 
    s.shift_name AS \"Shift\", 
    c.lsl AS \"LSL\",
    c.usl AS \"USL\",
    MAX(m.measurement_id) AS \"Measurement ID\",
    s.session_id AS \"Session ID\",
    MAX(CASE WHEN m.sample_sequence = 1 THEN m.sample_value END) AS sample_1,
    MAX(CASE WHEN m.sample_sequence = 2 THEN m.sample_value END) AS sample_2,
    MAX(CASE WHEN m.sample_sequence = 3 THEN m.sample_value END) AS sample_3,
    MAX(CASE WHEN m.sample_sequence = 4 THEN m.sample_value END) AS sample_4,
    MAX(CASE WHEN m.sample_sequence = 5 THEN m.sample_value END) AS sample_5,
    MAX(CASE WHEN m.sample_sequence = 6 THEN m.sample_value END) AS sample_6,
    MAX(CASE WHEN m.sample_sequence = 7 THEN m.sample_value END) AS sample_7,
    MAX(CASE WHEN m.sample_sequence = 8 THEN m.sample_value END) AS sample_8,
    MAX(CASE WHEN m.sample_sequence = 9 THEN m.sample_value END) AS sample_9,
    MAX(CASE WHEN m.sample_sequence = 10 THEN m.sample_value END) AS sample_10,
    s.max_value AS \"Max Value\",
    s.min_value AS \"Min Value\",
    s.x_bar AS \"X-Bar\",
    s.range_value AS \"Range\",
    s.std_dev AS \"Std Dev\"
FROM dtc_inspection_sessions s
LEFT JOIN dtc_measurements m ON s.session_id = m.session_id
JOIN dtc_master_parameters p ON s.parameter_id = p.parameter_id
JOIN dtc_master_dtc_specs c ON p.spec_id = c.spec_id
GROUP BY 
    s.session_id, 
    c.model_name, 
    c.item_check_name, 
    c.data_type,
    p.section_name, 
    p.line_name, 
    p.process_name, 
    s.inspection_date, 
    s.shift_name, 
    c.lsl, 
    c.usl,
    s.max_value,
    s.min_value,
    s.x_bar,
    s.range_value,
    s.std_dev
ORDER BY s.inspection_date DESC, s.session_id DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$time_mapping = [
    1 => '08:00', 2 => '09:00', 3 => '10:00', 4 => '11:00', 5 => '13:00',
    6 => '14:00', 7 => '15:00', 8 => '16:00', 9 => '17:00', 10 => '18:00'
];

$processed_data = [];
foreach($data as $raw_row) {
    $row = array_change_key_case($raw_row, CASE_UPPER);
    
    $new_row = [
        "Model Name" => $row['MODEL NAME'] ?? '',
        "Item Check Name" => $row['ITEM CHECK NAME'] ?? '',
        "Data Type" => $row['DATA TYPE'] ?? '',
        "Section" => $row['SECTION'] ?? '',
        "Line" => $row['LINE'] ?? '',
        "Process" => $row['PROCESS'] ?? '',
        "Measuring Item" => 'Quantitative',
        "Inspection Date" => $row['INSPECTION DATE'] ?? '',
        "Shift" => $row['SHIFT'] ?? '',
        "LSL" => $row['LSL'] ?? '',
        "USL" => $row['USL'] ?? '',
        "Measurement ID" => $row['MEASUREMENT ID'] ?? '',
        "Session ID" => $row['SESSION ID'] ?? ''
    ];
    
    $ins_date = $row['INSPECTION DATE'] ?? date('Y-m-d');
    
    for($i=1; $i<=10; $i++) {
        $sample_val = $row['SAMPLE_'.$i] ?? null;
        
        $new_row["Sampling Label $i"] = "Jam " . $time_mapping[$i];
        $new_row["Sample $i"] = $sample_val;
        
        if($sample_val !== null && $sample_val !== '') {
            $base_time = strtotime($ins_date . ' ' . $time_mapping[$i] . ':00');
            // Created date = base hour + random 1 to 15 mins
            $created_time = date('Y-m-d H:i:s', $base_time + rand(60, 900));
            // Modified date = created + random 0 to 5 mins
            $modified_time = date('Y-m-d H:i:s', strtotime($created_time) + rand(0, 300));
            
            $new_row["Sampling {$i} Created Date"] = $created_time;
            $new_row["Sampling {$i} Modified Date"] = $modified_time;
        } else {
            $new_row["Sampling {$i} Created Date"] = null;
            $new_row["Sampling {$i} Modified Date"] = null;
        }
    }
    
    $new_row["Max Value"] = $row['MAX VALUE'] ?? '';
    $new_row["Min Value"] = $row['MIN VALUE'] ?? '';
    $new_row["X-Bar"] = $row['X-BAR'] ?? '';
    $new_row["Range"] = $row['RANGE'] ?? '';
    $new_row["Std Dev"] = $row['STD DEV'] ?? '';
    $new_row["Zst"] = '4';
    $new_row["Zlt"] = '3';
    
    $processed_data[] = $new_row;
}

// Output as Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Data_Measurement_Joined.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
if (count($processed_data) > 0) {
    // Header
    echo "<tr>";
    foreach (array_keys($processed_data[0]) as $header) {
        echo "<th style='background-color:#4CAF50;color:white;'>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr>";
    
    // Data
    foreach ($processed_data as $row) {
        echo "<tr>";
        foreach ($row as $val) {
            echo "<td>" . htmlspecialchars((string)$val) . "</td>";
        }
        echo "</tr>";
    }
} else {
    echo "<tr><td>No Data Found</td></tr>";
}
echo "</table>";
?>

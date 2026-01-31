<?php
// ============================================
// get_latest_sensor.php
// ดึงข้อมูลเซ็นเซอร์ล่าสุด
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

date_default_timezone_set('Asia/Bangkok');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "robotic_handwear";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode([
        "status" => "error", 
        "message" => "Database connection failed"
    ]));
}

// ดึงข้อมูลล่าสุด (ภายใน 30 วินาที)
$sql = "SELECT * FROM sensor_data 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ORDER BY created_at DESC 
        LIMIT 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode([
        "status" => "success",
        "data" => $data,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "status" => "no_data",
        "message" => "No recent sensor data",
        "timestamp" => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
?>
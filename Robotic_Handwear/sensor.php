<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ตั้งค่าเขตเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// การตั้งค่าฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "robotic_handwear";

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die(json_encode([
        "status" => "error", 
        "message" => "Database connection failed: " . $conn->connect_error
    ]));
}

// รับค่าจาก GET parameters
$speed_type = isset($_GET['speed_type']) ? $_GET['speed_type'] : '';
$acceleration = isset($_GET['acceleration']) ? floatval($_GET['acceleration']) : 0;
$gyroscope = isset($_GET['gyroscope']) ? floatval($_GET['gyroscope']) : 0;
$angle_z = isset($_GET['angle_z']) ? floatval($_GET['angle_z']) : 0;
$slow_count = isset($_GET['slow_count']) ? intval($_GET['slow_count']) : 0;
$medium_count = isset($_GET['medium_count']) ? intval($_GET['medium_count']) : 0;
$fast_count = isset($_GET['fast_count']) ? intval($_GET['fast_count']) : 0;

// Validate speed_type
$valid_types = ['SLOW', 'MEDIUM', 'FAST'];
if (!in_array($speed_type, $valid_types)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid speed_type. Must be SLOW, MEDIUM, or FAST"
    ]);
    $conn->close();
    exit;
}

// เตรียม SQL statement
$sql = "INSERT INTO sensor_data 
        (speed_type, acceleration, gyroscope, angle_z, slow_count, medium_count, fast_count, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to prepare statement: " . $conn->error
    ]);
    $conn->close();
    exit;
}

// ผูกพารามิเตอร์ - แก้ไขจาก "sdddiiii" เป็น "sdddiii" (7 ตัว)
$stmt->bind_param("sdddiii", 
    $speed_type,      // s = string
    $acceleration,    // d = double
    $gyroscope,       // d = double
    $angle_z,         // d = double
    $slow_count,      // i = integer
    $medium_count,    // i = integer
    $fast_count       // i = integer
);

// Execute และตรวจสอบผลลัพธ์
if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Data inserted successfully",
        "insert_id" => $stmt->insert_id,
        "data" => [
            "speed_type" => $speed_type,
            "acceleration" => $acceleration,
            "gyroscope" => $gyroscope,
            "angle_z" => $angle_z,
            "counts" => [
                "slow" => $slow_count,
                "medium" => $medium_count,
                "fast" => $fast_count
            ]
        ],
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to insert data: " . $stmt->error
    ]);
}

// ปิดการเชื่อมต่อ
$stmt->close();
$conn->close();
?>
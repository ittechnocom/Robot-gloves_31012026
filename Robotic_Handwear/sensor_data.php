<?php
/**
 * Robotic Handwear Health Monitoring System - Data Logger
 * Version: 3.3
 * Purpose: รับข้อมูลจาก ESP32 และบันทึกลงฐานข้อมูล MySQL
 */

// ตั้งค่า Headers สำหรับรับข้อมูลจาก ESP32
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";           // เปลี่ยนตามการตั้งค่าของคุณ
$password = "";               // เปลี่ยนตามการตั้งค่าของคุณ
$dbname = "robotic_handwear";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตั้งค่า charset เป็น utf8mb4
$conn->set_charset("utf8mb4");

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    $response = array(
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    );
    echo json_encode($response);
    exit;
}

// รับข้อมูลจาก GET parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1; // Default user_id = 1
$speed_type = isset($_GET['speed_type']) ? $conn->real_escape_string($_GET['speed_type']) : 'UNKNOWN';
$acceleration = isset($_GET['acceleration']) ? floatval($_GET['acceleration']) : 0.0;
$gyroscope = isset($_GET['gyroscope']) ? floatval($_GET['gyroscope']) : 0.0;
$angle_z = isset($_GET['angle_z']) ? floatval($_GET['angle_z']) : 0.0;
$heart_rate = isset($_GET['heart_rate']) ? intval($_GET['heart_rate']) : 0;
$spo2 = isset($_GET['spo2']) ? intval($_GET['spo2']) : 0;
$ir_value = isset($_GET['ir_value']) ? intval($_GET['ir_value']) : 0;
$slow_count = isset($_GET['slow_count']) ? intval($_GET['slow_count']) : 0;
$medium_count = isset($_GET['medium_count']) ? intval($_GET['medium_count']) : 0;
$fast_count = isset($_GET['fast_count']) ? intval($_GET['fast_count']) : 0;

// ตรวจสอบความถูกต้องของข้อมูล
$errors = array();

// ตรวจสอบ speed_type
$valid_speed_types = array('SLOW', 'MEDIUM', 'FAST', 'IDLE', 'UNKNOWN');
if (!in_array($speed_type, $valid_speed_types)) {
    $errors[] = "Invalid speed_type: $speed_type";
}

// ตรวจสอบช่วงค่า heart_rate (20-255 BPM)
if ($heart_rate < 0 || $heart_rate > 255) {
    $errors[] = "Invalid heart_rate: $heart_rate (must be 0-255)";
}

// ตรวจสอบช่วงค่า SpO2 (0-100%)
if ($spo2 < 0 || $spo2 > 100) {
    $errors[] = "Invalid spo2: $spo2 (must be 0-100)";
}

// ตรวจสอบช่วงค่ามุม Z (0-360 degrees)
if ($angle_z < 0 || $angle_z > 360) {
    $errors[] = "Invalid angle_z: $angle_z (must be 0-360)";
}

// ถ้ามี error ให้ return ทันที
if (!empty($errors)) {
    $response = array(
        'success' => false,
        'message' => 'Validation errors',
        'errors' => $errors,
        'received_data' => array(
            'user_id' => $user_id,
            'speed_type' => $speed_type,
            'acceleration' => $acceleration,
            'gyroscope' => $gyroscope,
            'angle_z' => $angle_z,
            'heart_rate' => $heart_rate,
            'spo2' => $spo2,
            'ir_value' => $ir_value,
            'slow_count' => $slow_count,
            'medium_count' => $medium_count,
            'fast_count' => $fast_count
        )
    );
    echo json_encode($response);
    $conn->close();
    exit;
}

// SQL สำหรับบันทึกข้อมูล
$sql = "INSERT INTO sensor_data 
        (user_id, speed_type, acceleration, gyroscope, angle_z, 
         slow_count, medium_count, fast_count, 
         heart_rate, spo2, ir_value, sensor_datetime, created_at) 
        VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

// เตรียม statement
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $response = array(
        'success' => false,
        'message' => 'Prepare statement failed: ' . $conn->error
    );
    echo json_encode($response);
    $conn->close();
    exit;
}

// Bind parameters
$stmt->bind_param(
    "isdddiiiiii",
    $user_id,           // i = integer
    $speed_type,        // s = string
    $acceleration,      // d = double (float)
    $gyroscope,         // d = double
    $angle_z,           // d = double
    $slow_count,        // i = integer
    $medium_count,      // i = integer
    $fast_count,        // i = integer
    $heart_rate,        // i = integer
    $spo2,              // i = integer
    $ir_value           // i = integer
);

// Execute statement
if ($stmt->execute()) {
    $insert_id = $stmt->insert_id;
    
    $response = array(
        'success' => true,
        'message' => 'Data saved successfully',
        'insert_id' => $insert_id,
        'data' => array(
            'user_id' => $user_id,
            'speed_type' => $speed_type,
            'acceleration' => $acceleration,
            'gyroscope' => $gyroscope,
            'angle_z' => $angle_z,
            'heart_rate' => $heart_rate,
            'spo2' => $spo2,
            'ir_value' => $ir_value,
            'slow_count' => $slow_count,
            'medium_count' => $medium_count,
            'fast_count' => $fast_count,
            'timestamp' => date('Y-m-d H:i:s')
        )
    );
    
    // Log การบันทึกข้อมูล (optional)
    error_log(sprintf(
        "[%s] Sensor Data Saved: ID=%d, User=%d, Type=%s, HR=%d, SpO2=%d, IR=%d",
        date('Y-m-d H:i:s'),
        $insert_id,
        $user_id,
        $speed_type,
        $heart_rate,
        $spo2,
        $ir_value
    ));
    
} else {
    $response = array(
        'success' => false,
        'message' => 'Execute failed: ' . $stmt->error,
        'sql_state' => $stmt->sqlstate
    );
}

// ปิด statement และ connection
$stmt->close();
$conn->close();

// ส่งผลลัพธ์กลับ
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Log การ response (optional)
if ($response['success']) {
    error_log("✅ Data saved: " . json_encode($response['data']));
} else {
    error_log("❌ Error: " . $response['message']);
}
?>
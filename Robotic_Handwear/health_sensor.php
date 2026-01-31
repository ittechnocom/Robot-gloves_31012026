<?php
/**
 * Health Sensor Data Receiver - FIXED VERSION
 * รับข้อมูลจาก ESP32 พร้อมเซนเซอร์ MPU6050 + MAX30102
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "robotic_handwear";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

$conn->set_charset("utf8mb4");

// รับข้อมูลจาก GET parameters
$speed_type = isset($_GET['speed_type']) ? $conn->real_escape_string($_GET['speed_type']) : 'UNKNOWN';
$acceleration = isset($_GET['acceleration']) ? floatval($_GET['acceleration']) : 0;
$gyroscope = isset($_GET['gyroscope']) ? floatval($_GET['gyroscope']) : 0;
$angle_z = isset($_GET['angle_z']) ? floatval($_GET['angle_z']) : 0;
$heart_rate = isset($_GET['heart_rate']) ? intval($_GET['heart_rate']) : 0;
$spo2 = isset($_GET['spo2']) ? intval($_GET['spo2']) : 0;
$ir_value = isset($_GET['ir_value']) ? intval($_GET['ir_value']) : 0;
$slow_count = isset($_GET['slow_count']) ? intval($_GET['slow_count']) : 0;
$medium_count = isset($_GET['medium_count']) ? intval($_GET['medium_count']) : 0;
$fast_count = isset($_GET['fast_count']) ? intval($_GET['fast_count']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

// สร้าง SQL query - ใช้ชื่อตารางที่ถูกต้อง
$sql = "INSERT INTO sensor_data (
    user_id, 
    speed_type, 
    acceleration, 
    gyroscope, 
    angle_z, 
    slow_count, 
    medium_count, 
    fast_count,
    heart_rate, 
    spo2, 
    ir_value,
    sensor_datetime
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
)";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die(json_encode([
        'status' => 'error',
        'message' => 'SQL prepare failed: ' . $conn->error,
        'debug' => [
            'sql' => $sql,
            'error' => $conn->error
        ]
    ]));
}

$stmt->bind_param(
    "isdddiiiiii",
    $user_id,
    $speed_type,
    $acceleration,
    $gyroscope,
    $angle_z,
    $slow_count,
    $medium_count,
    $fast_count,
    $heart_rate,
    $spo2,
    $ir_value
);

// Execute the statement
if ($stmt->execute()) {
    $insert_id = $stmt->insert_id;
    
    // ประเมินสถานะสุขภาพ
    $health_status = evaluateHealthStatus($heart_rate, $spo2, $speed_type);
    
    $response = [
        'status' => 'success',
        'message' => 'Data saved successfully',
        'data' => [
            'sensor_id' => $insert_id,
            'user_id' => $user_id,
            'speed_type' => $speed_type,
            'acceleration' => $acceleration,
            'gyroscope' => $gyroscope,
            'angle_z' => $angle_z,
            'heart_rate' => $heart_rate,
            'spo2' => $spo2,
            'ir_value' => $ir_value,
            'counts' => [
                'slow' => $slow_count,
                'medium' => $medium_count,
                'fast' => $fast_count
            ],
            'health_status' => $health_status,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    // บันทึก activity log
    logActivity($conn, $user_id, 'sensor_data', 'Health data recorded: ' . $speed_type);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save data: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();

/**
 * ฟังก์ชันประเมินสถานะสุขภาพ
 */
function evaluateHealthStatus($heart_rate, $spo2, $speed_type) {
    $status = [];
    
    // ประเมินอัตราการเต้นหัวใจ
    if ($heart_rate > 0) {
        if ($heart_rate < 60) {
            $status['heart_rate_status'] = 'LOW - อัตราการเต้นหัวใจต่ำ';
            $status['heart_rate_level'] = 'warning';
            $status['heart_rate_emoji'] = '⚠️';
        } elseif ($heart_rate >= 60 && $heart_rate <= 100) {
            $status['heart_rate_status'] = 'NORMAL - อัตราการเต้นหัวใจปกติ';
            $status['heart_rate_level'] = 'good';
            $status['heart_rate_emoji'] = '✅';
        } elseif ($heart_rate > 100 && $heart_rate <= 120) {
            $status['heart_rate_status'] = 'ELEVATED - อัตราการเต้นหัวใจสูงเล็กน้อย';
            $status['heart_rate_level'] = 'warning';
            $status['heart_rate_emoji'] = '⚠️';
        } else {
            $status['heart_rate_status'] = 'HIGH - อัตราการเต้นหัวใจสูง';
            $status['heart_rate_level'] = 'danger';
            $status['heart_rate_emoji'] = '🚨';
        }
    } else {
        $status['heart_rate_status'] = 'NO DATA - รอการตรวจวัด';
        $status['heart_rate_level'] = 'info';
        $status['heart_rate_emoji'] = 'ℹ️';
    }
    
    // ประเมิน SpO2
    if ($spo2 > 0) {
        if ($spo2 >= 95) {
            $status['spo2_status'] = 'NORMAL - ออกซิเจนในเลือดปกติ';
            $status['spo2_level'] = 'good';
            $status['spo2_emoji'] = '✅';
        } elseif ($spo2 >= 90 && $spo2 < 95) {
            $status['spo2_status'] = 'LOW - ออกซิเจนในเลือดต่ำเล็กน้อย';
            $status['spo2_level'] = 'warning';
            $status['spo2_emoji'] = '⚠️';
        } else {
            $status['spo2_status'] = 'CRITICAL - ออกซิเจนในเลือดต่ำมาก';
            $status['spo2_level'] = 'danger';
            $status['spo2_emoji'] = '🚨';
        }
    } else {
        $status['spo2_status'] = 'NO DATA - รอการตรวจวัด';
        $status['spo2_level'] = 'info';
        $status['spo2_emoji'] = 'ℹ️';
    }
    
    // ประเมินความเร็วการบีบมือ
    switch ($speed_type) {
        case 'SLOW':
            $status['performance'] = 'การบีบมือช้า - เหมาะสำหรับผู้เริ่มต้นหรือผู้ป่วย';
            $status['performance_emoji'] = '🐢';
            break;
        case 'MEDIUM':
            $status['performance'] = 'การบีบมือปานกลาง - ระดับดี';
            $status['performance_emoji'] = '🚶';
            break;
        case 'FAST':
            $status['performance'] = 'การบีบมือเร็ว - ระดับดีมาก';
            $status['performance_emoji'] = '🏃';
            break;
        default:
            $status['performance'] = 'กำลังตรวจสอบสุขภาพ';
            $status['performance_emoji'] = '📊';
    }
    
    // สรุปสถานะโดยรวม
    if ($heart_rate > 0 && $spo2 > 0) {
        if ($status['heart_rate_level'] == 'good' && $status['spo2_level'] == 'good') {
            $status['overall'] = 'สุขภาพดีมาก';
            $status['overall_emoji'] = '🎉';
        } elseif ($status['heart_rate_level'] == 'danger' || $status['spo2_level'] == 'danger') {
            $status['overall'] = 'ต้องระวัง กรุณาพักผ่อน';
            $status['overall_emoji'] = '🚨';
        } else {
            $status['overall'] = 'สุขภาพปกติ';
            $status['overall_emoji'] = '✅';
        }
    } else {
        $status['overall'] = 'กำลังรอข้อมูล';
        $status['overall_emoji'] = '⏳';
    }
    
    return $status;
}

/**
 * ฟังก์ชันบันทึก Activity Log
 */
function logActivity($conn, $user_id, $log_type, $log_details) {
    $sql = "INSERT INTO tb_activity_log (user_id, log_type, log_details, log_datetime) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $log_type, $log_details);
        $stmt->execute();
        $stmt->close();
    }
}
?>
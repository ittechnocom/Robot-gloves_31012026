<?php
/**
 * API สำหรับดึงข้อมูลสุขภาพล่าสุด Real-time
 * ใช้สำหรับ Dashboard และการแสดงผลแบบ Live
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "robotic_handwear";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed']));
}

$conn->set_charset("utf8mb4");

// รับพารามิเตอร์
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;
$action = isset($_GET['action']) ? $_GET['action'] : 'latest';

$response = ['status' => 'success'];

switch ($action) {
    case 'latest':
        // ดึงข้อมูลล่าสุด
        $sql = "SELECT * FROM sensor_data WHERE user_id = ? ORDER BY sensor_datetime DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            // ประเมินสถานะสุขภาพ
            $health_status = evaluateHealth($data);
            
            $response['data'] = $data;
            $response['health_status'] = $health_status;
            $response['timestamp'] = date('Y-m-d H:i:s');
        } else {
            $response['message'] = 'No data found';
        }
        break;
        
    case 'history':
        // ดึงข้อมูลย้อนหลัง (24 ชั่วโมง)
        $hours = isset($_GET['hours']) ? intval($_GET['hours']) : 24;
        
        $sql = "SELECT 
                    sensor_datetime,
                    heart_rate,
                    spo2,
                    angle_z,
                    speed_type,
                    acceleration,
                    gyroscope
                FROM sensor_data 
                WHERE user_id = ? 
                AND sensor_datetime >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY sensor_datetime ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $hours);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        $response['data'] = $history;
        $response['count'] = count($history);
        break;
        
    case 'summary':
        // สรุปข้อมูลรายวัน
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    AVG(heart_rate) as avg_heart_rate,
                    MAX(heart_rate) as max_heart_rate,
                    MIN(heart_rate) as min_heart_rate,
                    AVG(spo2) as avg_spo2,
                    MIN(spo2) as min_spo2,
                    SUM(slow_count) as total_slow,
                    SUM(medium_count) as total_medium,
                    SUM(fast_count) as total_fast,
                    AVG(angle_z) as avg_angle
                FROM tb_sensor 
                WHERE user_id = ? 
                AND DATE(sensor_datetime) = CURDATE()";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $summary = $result->fetch_assoc();
            
            // คำนวณค่าเพิ่มเติม
            $summary['total_compressions'] = 
                ($summary['total_slow'] ?? 0) + 
                ($summary['total_medium'] ?? 0) + 
                ($summary['total_fast'] ?? 0);
            
            // ประเมินผลการออกกำลังกาย
            $summary['performance'] = evaluatePerformance($summary);
            
            $response['data'] = $summary;
        }
        break;
        
    case 'alerts':
        // ตรวจสอบค่าผิดปกติ
        $sql = "SELECT * FROM sensor_data
                WHERE user_id = ? 
                AND sensor_datetime >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND (heart_rate > 120 OR heart_rate < 50 OR spo2 < 90)
                ORDER BY sensor_datetime DESC
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $alerts = [];
        while ($row = $result->fetch_assoc()) {
            $alert = [
                'datetime' => $row['sensor_datetime'],
                'type' => '',
                'message' => '',
                'level' => ''
            ];
            
            if ($row['heart_rate'] > 120) {
                $alert['type'] = 'HIGH_HEART_RATE';
                $alert['message'] = 'อัตราการเต้นหัวใจสูงเกินปกติ: ' . $row['heart_rate'] . ' BPM';
                $alert['level'] = 'warning';
            } elseif ($row['heart_rate'] < 50) {
                $alert['type'] = 'LOW_HEART_RATE';
                $alert['message'] = 'อัตราการเต้นหัวใจต่ำเกินไป: ' . $row['heart_rate'] . ' BPM';
                $alert['level'] = 'danger';
            }
            
            if ($row['spo2'] < 90) {
                $alert['type'] = 'LOW_SPO2';
                $alert['message'] = 'ออกซิเจนในเลือดต่ำ: ' . $row['spo2'] . '%';
                $alert['level'] = 'danger';
            }
            
            $alerts[] = $alert;
        }
        
        $response['data'] = $alerts;
        $response['count'] = count($alerts);
        break;
        
    case 'stats':
        // สถิติรายสัปดาห์
        $sql = "SELECT 
                    DATE(sensor_datetime) as date,
                    COUNT(*) as records,
                    AVG(heart_rate) as avg_hr,
                    AVG(spo2) as avg_spo2,
                    SUM(slow_count + medium_count + fast_count) as total_compressions
                FROM sensor_data
                WHERE user_id = ? 
                AND sensor_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(sensor_datetime)
                ORDER BY date ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        $response['data'] = $stats;
        break;
        
    default:
        $response['status'] = 'error';
        $response['message'] = 'Invalid action';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$conn->close();

/**
 * ฟังก์ชันประเมินสถานะสุขภาพ
 */
function evaluateHealth($data) {
    $status = [
        'overall' => 'good',
        'heart_rate' => [],
        'spo2' => [],
        'recommendations' => []
    ];
    
    // ประเมิน Heart Rate
    $hr = $data['heart_rate'] ?? 0;
    if ($hr > 0) {
        if ($hr < 60) {
            $status['heart_rate'] = [
                'status' => 'low',
                'message' => 'อัตราการเต้นหัวใจต่ำกว่าปกติ',
                'level' => 'warning'
            ];
            $status['overall'] = 'warning';
            $status['recommendations'][] = 'พิจารณาตรวจสอบกับแพทย์หากมีอาการผิดปกติ';
        } elseif ($hr >= 60 && $hr <= 100) {
            $status['heart_rate'] = [
                'status' => 'normal',
                'message' => 'อัตราการเต้นหัวใจปกติ',
                'level' => 'good'
            ];
        } elseif ($hr > 100 && $hr <= 120) {
            $status['heart_rate'] = [
                'status' => 'elevated',
                'message' => 'อัตราการเต้นหัวใจสูงเล็กน้อย',
                'level' => 'warning'
            ];
            $status['overall'] = 'warning';
            $status['recommendations'][] = 'พักผ่อนให้เพียงพอและลดความเครียด';
        } else {
            $status['heart_rate'] = [
                'status' => 'high',
                'message' => 'อัตราการเต้นหัวใจสูงเกินปกติ',
                'level' => 'danger'
            ];
            $status['overall'] = 'danger';
            $status['recommendations'][] = 'หยุดออกกำลังกายและปรึกษาแพทย์';
        }
    }
    
    // ประเมิน SpO2
    $spo2 = $data['spo2'] ?? 0;
    if ($spo2 > 0) {
        if ($spo2 >= 95) {
            $status['spo2'] = [
                'status' => 'normal',
                'message' => 'ระดับออกซิเจนในเลือดปกติ',
                'level' => 'good'
            ];
        } elseif ($spo2 >= 90 && $spo2 < 95) {
            $status['spo2'] = [
                'status' => 'low',
                'message' => 'ระดับออกซิเจนในเลือดต่ำเล็กน้อย',
                'level' => 'warning'
            ];
            $status['overall'] = 'warning';
            $status['recommendations'][] = 'หายใจลึกๆ และพักผ่อน';
        } else {
            $status['spo2'] = [
                'status' => 'critical',
                'message' => 'ระดับออกซิเจนในเลือดต่ำมาก',
                'level' => 'danger'
            ];
            $status['overall'] = 'danger';
            $status['recommendations'][] = 'ควรรีบพบแพทย์ทันที';
        }
    }
    
    return $status;
}

/**
 * ฟังก์ชันประเมินผลการออกกำลังกาย
 */
function evaluatePerformance($summary) {
    $total = $summary['total_compressions'] ?? 0;
    $avg_hr = $summary['avg_heart_rate'] ?? 0;
    
    if ($total == 0) {
        return [
            'rating' => 'none',
            'message' => 'ยังไม่มีการออกกำลังกายวันนี้',
            'score' => 0
        ];
    }
    
    $score = 0;
    
    // คะแนนจากจำนวนการบีบ
    if ($total >= 200) $score += 40;
    elseif ($total >= 150) $score += 30;
    elseif ($total >= 100) $score += 20;
    else $score += 10;
    
    // คะแนนจากอัตราการเต้นหัวใจ
    if ($avg_hr >= 70 && $avg_hr <= 100) $score += 30;
    elseif ($avg_hr > 60 && $avg_hr < 110) $score += 20;
    else $score += 10;
    
    // คะแนนจากความหลากหลาย
    if ($summary['total_slow'] > 0 && $summary['total_medium'] > 0 && $summary['total_fast'] > 0) {
        $score += 30;
    } else {
        $score += 15;
    }
    
    // ประเมินผล
    if ($score >= 80) {
        return [
            'rating' => 'excellent',
            'message' => 'ดีเยี่ยม! การออกกำลังกายเหมาะสมมาก',
            'score' => $score
        ];
    } elseif ($score >= 60) {
        return [
            'rating' => 'good',
            'message' => 'ดี! ควรออกกำลังกายในระดับนี้ต่อไป',
            'score' => $score
        ];
    } elseif ($score >= 40) {
        return [
            'rating' => 'fair',
            'message' => 'พอใช้ ควรเพิ่มความถี่ในการออกกำลังกาย',
            'score' => $score
        ];
    } else {
        return [
            'rating' => 'poor',
            'message' => 'ควรออกกำลังกายให้มากขึ้น',
            'score' => $score
        ];
    }
}
?>

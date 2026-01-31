<?php
// ============================================
// get_sensor_history.php
// ดึงประวัติเซ็นเซอร์ย้อนหลัง
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

// รับพารามิเตอร์
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$hours = isset($_GET['hours']) ? intval($_GET['hours']) : 24;

$sql = "SELECT * FROM sensor_data 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ORDER BY created_at DESC 
        LIMIT ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $hours, $limit);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// คำนวณสถิติ
$stats = [
    "total_records" => count($data),
    "slow_count" => 0,
    "medium_count" => 0,
    "fast_count" => 0,
    "avg_acceleration" => 0,
    "avg_gyroscope" => 0
];

if (count($data) > 0) {
    $stats['slow_count'] = $data[0]['slow_count'];
    $stats['medium_count'] = $data[0]['medium_count'];
    $stats['fast_count'] = $data[0]['fast_count'];
    
    $total_acc = 0;
    $total_gyro = 0;
    foreach ($data as $record) {
        $total_acc += $record['acceleration'];
        $total_gyro += $record['gyroscope'];
    }
    $stats['avg_acceleration'] = round($total_acc / count($data), 2);
    $stats['avg_gyroscope'] = round($total_gyro / count($data), 2);
}

echo json_encode([
    "status" => "success",
    "data" => $data,
    "stats" => $stats,
    "query_params" => [
        "limit" => $limit,
        "hours" => $hours
    ]
]);

$stmt->close();
$conn->close();
?>
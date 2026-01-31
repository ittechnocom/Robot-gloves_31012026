<?php
// ============================================
// get_relay_status.php
// ดึงสถานะรีเลย์ปัจจุบัน
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

$sql = "SELECT relay_1, relay_2, relay_3, updated_at 
        FROM relay_control 
        WHERE id = 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode([
        "status" => "success",
        "relay_status" => [
            "relay_1" => (int)$data['relay_1'],
            "relay_2" => (int)$data['relay_2'],
            "relay_3" => (int)$data['relay_3']
        ],
        "updated_at" => $data['updated_at']
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "relay_status" => [
            "relay_1" => 0,
            "relay_2" => 0,
            "relay_3" => 0
        ],
        "updated_at" => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
?>
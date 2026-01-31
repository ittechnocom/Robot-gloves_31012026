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
$relay_1 = isset($_GET['relay_1']) ? intval($_GET['relay_1']) : 0;
$relay_2 = isset($_GET['relay_2']) ? intval($_GET['relay_2']) : 0;
$relay_3 = isset($_GET['relay_3']) ? intval($_GET['relay_3']) : 0;

// ตรวจสอบค่าว่าเป็น 0 หรือ 1 เท่านั้น
$relay_1 = ($relay_1 == 1) ? 1 : 0;
$relay_2 = ($relay_2 == 1) ? 1 : 0;
$relay_3 = ($relay_3 == 1) ? 1 : 0;

// ลอง UPDATE ก่อน
$sql = "UPDATE relay_control 
        SET relay_1 = ?, relay_2 = ?, relay_3 = ?, updated_at = NOW() 
        WHERE id = 1";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to prepare statement: " . $conn->error
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param("iii", $relay_1, $relay_2, $relay_3);

if ($stmt->execute()) {
    // ตรวจสอบว่ามีการ UPDATE หรือไม่
    if ($stmt->affected_rows == 0) {
        // ถ้าไม่มีแถวที่ถูก UPDATE (ไม่มีข้อมูล id=1) ให้ INSERT
        $stmt->close();
        
        $sql = "INSERT INTO relay_control (id, relay_1, relay_2, relay_3, updated_at) 
                VALUES (1, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $relay_1, $relay_2, $relay_3);
        
        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "message" => "Relay control record created",
                "action" => "inserted",
                "relay_status" => [
                    "relay_1" => $relay_1,
                    "relay_2" => $relay_2,
                    "relay_3" => $relay_3
                ]
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to insert relay control: " . $stmt->error
            ]);
        }
    } else {
        // UPDATE สำเร็จ
        echo json_encode([
            "status" => "success",
            "message" => "Relay control updated",
            "action" => "updated",
            "relay_status" => [
                "relay_1" => $relay_1,
                "relay_2" => $relay_2,
                "relay_3" => $relay_3
            ]
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update relay control: " . $stmt->error
    ]);
}

// ปิดการเชื่อมต่อ
$stmt->close();
$conn->close();
?>
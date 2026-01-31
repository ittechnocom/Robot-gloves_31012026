<?php
session_start();
header('Content-Type: application/json');

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// เชื่อมต่อฐานข้อมูล
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$duration = intval($_POST['duration'] ?? 0);
$rounds_completed = intval($_POST['rounds_completed'] ?? 0);
$intensity = $_POST['intensity'] ?? '';

// บันทึกลงตาราง tb_exercise_session
if (isset($_SESSION['exercise_session_id'])) {
    $session_id = $_SESSION['exercise_session_id'];
    
    $sql_update = "UPDATE tb_exercise_session 
                   SET end_time = NOW(),
                       duration_seconds = ?,
                       rounds_completed = ?,
                       status = 'completed'
                   WHERE session_id = ?";
    
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("iii", $duration, $rounds_completed, $session_id);
    $stmt->execute();
    
    unset($_SESSION['exercise_session_id']);
}

// บันทึก activity log
$log_sql = "INSERT INTO tb_activity_log (user_id, log_type, log_details) 
            VALUES (?, 'exercise_completed', ?)";
$log_stmt = $conn->prepare($log_sql);
$log_details = "ออกกำลังกาย $intensity - $rounds_completed รอบ ใช้เวลา " . gmdate("i:s", $duration);
$log_stmt->bind_param("is", $user_id, $log_details);
$log_stmt->execute();

echo json_encode([
    'status' => 'success',
    'message' => 'Exercise log saved',
    'data' => [
        'duration' => $duration,
        'rounds' => $rounds_completed,
        'intensity' => $intensity
    ]
]);

$conn->close();
?>
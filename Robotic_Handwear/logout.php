<?php
session_start();

// บันทึก Logout Log
if (isset($_SESSION['user_id'])) {
    $servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $dbname = "robotic_handwear";
    
    $conn = new mysqli($servername, $db_username, $db_password, $dbname);
    
    if (!$conn->connect_error) {
        $conn->set_charset("utf8mb4");
        
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_sql = "INSERT INTO tb_logout_logs (user_id, logout_time, ip_address) VALUES (?, NOW(), ?)";
        $log_stmt = $conn->prepare($log_sql);
        
        // Check if prepare was successful
        if ($log_stmt) {
            $log_stmt->bind_param("is", $user_id, $ip_address);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            // Log the error for debugging (optional)
            error_log("Prepare failed: " . $conn->error);
        }
        
        $conn->close();
    }
}

// ทำลาย Session
session_unset();
session_destroy();

// Redirect ไปหน้า Login
header('Location: login.php?logout=1');
exit();
?>
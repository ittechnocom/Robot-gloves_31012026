<?php
/**
 * สคริปต์สำหรับอัพเดทรหัสผ่านของผู้ใช้ที่มีอยู่แล้ว
 * ใช้สำหรับแปลงรหัสผ่าน Plain Text เป็น Hash
 */

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "db_robotic_handwear";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

echo "<h2>🔄 กำลังอัพเดทรหัสผ่านของผู้ใช้ที่มีอยู่แล้ว...</h2>";

// ดึงข้อมูลผู้ใช้ทั้งหมด
$sql = "SELECT user_id, user_email, user_fname, user_lname FROM tb_user";
$result = $conn->query($sql);

echo "<div style='background: #fef3c7; padding: 20px; border-radius: 12px; margin: 20px 0;'>";
echo "<h3 style='color: #92400e;'>⚠️ คำแนะนำ</h3>";
echo "<p>สคริปต์นี้จะตั้งรหัสผ่านเริ่มต้นสำหรับผู้ใช้ทั้งหมดเป็น: <strong>password123</strong></p>";
echo "<p>ผู้ใช้ควรเปลี่ยนรหัสผ่านหลังจาก Login ครั้งแรก</p>";
echo "</div>";

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background: #667eea; color: white;'>
        <th>User ID</th>
        <th>ชื่อ</th>
        <th>นามสกุล</th>
        <th>อีเมล</th>
        <th>รหัสผ่านใหม่</th>
        <th>สถานะ</th>
      </tr>";

$success_count = 0;
$error_count = 0;
$default_password = "password123";
$hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // อัพเดทรหัสผ่าน
        $update_sql = "UPDATE tb_user SET user_password = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $hashed_password, $row['user_id']);
        
        if ($update_stmt->execute()) {
            echo "<tr style='background: #dcfce7;'>
                    <td>{$row['user_id']}</td>
                    <td>{$row['user_fname']}</td>
                    <td>{$row['user_lname']}</td>
                    <td>{$row['user_email']}</td>
                    <td><code>{$default_password}</code></td>
                    <td><strong style='color: #166534;'>✅ อัพเดทสำเร็จ</strong></td>
                  </tr>";
            $success_count++;
        } else {
            echo "<tr style='background: #fee2e2;'>
                    <td>{$row['user_id']}</td>
                    <td>{$row['user_fname']}</td>
                    <td>{$row['user_lname']}</td>
                    <td>{$row['user_email']}</td>
                    <td><code>{$default_password}</code></td>
                    <td><strong style='color: #991b1b;'>❌ เกิดข้อผิดพลาด</strong></td>
                  </tr>";
            $error_count++;
        }
    }
} else {
    echo "<tr><td colspan='6' style='text-align: center; padding: 20px;'>ไม่มีผู้ใช้ในระบบ</td></tr>";
}

echo "</table>";

echo "<div style='background: #dbeafe; padding: 20px; border-radius: 12px; margin: 20px 0;'>";
echo "<h3 style='color: #1e40af; margin: 0 0 10px 0;'>📊 สรุปผลการอัพเดท</h3>";
echo "<p style='margin: 5px 0;'>✅ อัพเดทสำเร็จ: <strong>{$success_count}</strong> บัญชี</p>";
echo "<p style='margin: 5px 0;'>❌ ล้มเหลว: <strong>{$error_count}</strong> บัญชี</p>";
echo "<p style='margin: 5px 0;'>📝 ทั้งหมด: <strong>" . ($success_count + $error_count) . "</strong> บัญชี</p>";
echo "</div>";

if ($success_count > 0) {
    echo "<div style='background: #dcfce7; padding: 20px; border-radius: 12px; margin: 20px 0;'>";
    echo "<h3 style='color: #166534; margin: 0 0 10px 0;'>🎉 อัพเดทรหัสผ่านสำเร็จ!</h3>";
    echo "<p>ผู้ใช้ทุกคนสามารถ Login ด้วยรหัสผ่าน: <strong>password123</strong></p>";
    echo "<p><a href='login.php' style='display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin-top: 10px;'>
            🔐 ไปยังหน้า Login
          </a></p>";
    echo "</div>";
}

echo "<div style='background: #fee2e2; padding: 20px; border-radius: 12px; margin: 20px 0;'>";
echo "<h3 style='color: #991b1b; margin: 0 0 10px 0;'>🔒 ความปลอดภัย</h3>";
echo "<ul style='margin: 10px 0; padding-left: 20px;'>";
echo "<li>แจ้งให้ผู้ใช้เปลี่ยนรหัสผ่านทันทีหลัง Login</li>";
echo "<li>ลบไฟล์ update_existing_users.php หลังจากใช้งานเสร็จ</li>";
echo "<li>ไม่ควรใช้รหัสผ่านเดียวกันสำหรับทุกบัญชีใน Production</li>";
echo "</ul>";
echo "</div>";

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัพเดทรหัสผ่านผู้ใช้ - ระบบถุงมือหุ่นยนต์บำบัด</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
            margin: 0;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h2 {
            color: #0f172a;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        table {
            font-size: 14px;
        }
        th {
            font-weight: 600;
        }
        code {
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: monospace;
            color: #ef4444;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- PHP Output will be here -->
    </div>
</body>
</html>
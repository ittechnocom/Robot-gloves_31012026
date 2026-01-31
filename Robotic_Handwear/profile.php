<?php
session_start();

// ตรวจสอบว่า Login แล้วหรือยัง
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "robotic_handwear";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$message = "";
$messageType = "";

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$sql = "SELECT * FROM tb_user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// อัพเดทข้อมูลเมื่อส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $phone = trim($_POST['phone']);
    $age = intval($_POST['age']);
    $sex = $_POST['sex'];
    
    // Validate
    if (empty($fname) || empty($lname)) {
        $message = "กรุณากรอกชื่อและนามสกุล";
        $messageType = "error";
    } elseif ($age < 1 || $age > 150) {
        $message = "กรุณากรอกอายุที่ถูกต้อง";
        $messageType = "error";
    } else {
        $update_sql = "UPDATE tb_user SET user_fname = ?, user_lname = ?, user_phon = ?, user_age = ?, user_sex = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssisi", $fname, $lname, $phone, $age, $sex, $user_id);
        
        if ($update_stmt->execute()) {
            $message = "บันทึกข้อมูลเรียบร้อยแล้ว";
            $messageType = "success";
            
            // อัพเดท session
            $_SESSION['user_name'] = $fname . ' ' . $lname;
            
            // ดึงข้อมูลใหม่
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            $messageType = "error";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าข้อมูลส่วนตัว - ระบบถุงมือหุ่นยนต์บำบัด</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            padding: 20px;
            color: #fff;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .profile-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
            color: #0f172a;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .avatar-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }

        .profile-email {
            font-size: 1.1rem;
            color: #64748b;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #0f172a;
            font-size: 1rem;
        }

        .form-group label i {
            margin-right: 8px;
            color: #667eea;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Prompt', sans-serif;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Prompt', sans-serif;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #10b981;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-section {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #3b82f6;
        }

        .info-section h3 {
            color: #0f172a;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-section p {
            color: #64748b;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .profile-card {
                padding: 25px;
            }

            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> กลับสู่หน้าหลัก
        </a>

        <div class="header">
            <h1>
                <i class="fas fa-user-cog"></i>
                ตั้งค่าข้อมูลส่วนตัว
            </h1>
            <p>จัดการข้อมูลส่วนตัวของคุณ</p>
        </div>

        <div class="profile-card">
            <div class="profile-header">
                <div class="avatar-circle">
                    <i class="fas fa-user"></i>
                </div>
                <h2><?php echo htmlspecialchars($user['user_email']); ?></h2>
                <div class="profile-email">
                    <i class="fas fa-clock"></i>
                    สมัครเมื่อ: <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="info-section">
                <h3>
                    <i class="fas fa-info-circle"></i>
                    ทำไมต้องกรอกข้อมูล?
                </h3>
                <p>ข้อมูลส่วนตัวของคุณจะช่วยให้ระบบสามารถวิเคราะห์และให้คำแนะนำด้านสุขภาพที่เหมาะสมกับคุณได้ดีขึ้น รวมถึงการติดตามผลการบำบัดที่แม่นยำยิ่งขึ้น</p>
            </div>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-user"></i>
                            ชื่อ <span style="color: #ef4444;">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="fname" 
                            value="<?php echo htmlspecialchars($user['user_fname'] ?? ''); ?>" 
                            placeholder="กรอกชื่อ"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-user"></i>
                            นามสกุล <span style="color: #ef4444;">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="lname" 
                            value="<?php echo htmlspecialchars($user['user_lname'] ?? ''); ?>" 
                            placeholder="กรอกนามสกุล"
                            required
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-phone"></i>
                            เบอร์โทรศัพท์
                        </label>
                        <input 
                            type="tel" 
                            name="phone" 
                            value="<?php echo htmlspecialchars($user['user_phon'] ?? ''); ?>" 
                            placeholder="0812345678"
                            maxlength="20"
                        >
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-calendar"></i>
                            อายุ <span style="color: #ef4444;">*</span>
                        </label>
                        <input 
                            type="number" 
                            name="age" 
                            value="<?php echo htmlspecialchars($user['user_age'] ?? ''); ?>" 
                            placeholder="25"
                            min="1"
                            max="150"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-venus-mars"></i>
                        เพศ <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="sex" required>
                        <option value="">-- เลือกเพศ --</option>
                        <option value="Male" <?php echo ($user['user_sex'] ?? '') == 'Male' ? 'selected' : ''; ?>>ชาย</option>
                        <option value="Female" <?php echo ($user['user_sex'] ?? '') == 'Female' ? 'selected' : ''; ?>>หญิง</option>
                        <option value="Other" <?php echo ($user['user_sex'] ?? '') == 'Other' ? 'selected' : ''; ?>>อื่นๆ</option>
                    </select>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> บันทึกข้อมูล
                </button>
            </form>
        </div>
    </div>
</body>
</html>
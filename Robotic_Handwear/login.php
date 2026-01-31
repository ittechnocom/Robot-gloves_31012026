<?php
session_start();

// ถ้า login แล้วให้ redirect ไปหน้า dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "robotic_handwear";

$error_message = '';
$success_message = '';

// ประมวลผล Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $user_password = $_POST['password'];
    
    if (empty($email) || empty($user_password)) {
        $error_message = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        $conn = new mysqli($servername, $db_username, $db_password, $dbname);
        
        if ($conn->connect_error) {
            $error_message = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . $conn->connect_error;
        } else {
            $conn->set_charset("utf8mb4");
            
            // ค้นหาผู้ใช้จากอีเมล
            $sql = "SELECT user_id, user_fname, user_lname, user_email, user_password FROM tb_user WHERE user_email = ?";
            $stmt = $conn->prepare($sql);
            
            // ตรวจสอบว่า prepare สำเร็จหรือไม่
            if ($stmt === false) {
                $error_message = 'เกิดข้อผิดพลาดในการเตรียม query: ' . $conn->error;
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    
                    // ตรวจสอบรหัสผ่าน
                    if (password_verify($user_password, $user['user_password'])) {
                        // Login สำเร็จ
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['user_name'] = $user['user_fname'] . ' ' . $user['user_lname'];
                        $_SESSION['user_fname'] = $user['user_fname'];
                        $_SESSION['user_lname'] = $user['user_lname'];
                        $_SESSION['user_email'] = $user['user_email'];
                        $_SESSION['login_time'] = time();
                        
                        // บันทึก Login Log
                        $log_sql = "INSERT INTO tb_login_logs (user_id, login_time, ip_address) VALUES (?, NOW(), ?)";
                        $log_stmt = $conn->prepare($log_sql);
                        
                        if ($log_stmt) {
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            $log_stmt->bind_param("is", $user['user_id'], $ip_address);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                        
                        $stmt->close();
                        $conn->close();
                        
                        header('Location: index.php');
                        exit();
                    } else {
                        $error_message = 'รหัสผ่านไม่ถูกต้อง';
                    }
                } else {
                    $error_message = 'ไม่พบอีเมลนี้ในระบบ';
                }
                
                $stmt->close();
            }
            
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ระบบถุงมือหุ่นยนต์บำบัด</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header .icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }

        .login-header h1 {
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .login-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            color: #0f172a;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1.1rem;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Prompt', sans-serif;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-control::placeholder {
            color: #cbd5e1;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 4px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #10b981;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Prompt', sans-serif;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.5);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .register-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.9rem;
        }

        .register-link a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: #764ba2;
        }

        .demo-credentials {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            border-left: 4px solid #f59e0b;
        }

        .demo-credentials h4 {
            color: #92400e;
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-credentials p {
            color: #92400e;
            font-size: 0.85rem;
            margin: 4px 0;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 24px;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="icon">
                <i class="fas fa-hand-sparkles"></i>
            </div>
            <h1>เข้าสู่ระบบ</h1>
            <p>ระบบถุงมือหุ่นยนต์บำบัด</p>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <!-- Demo Credentials -->
        <div class="demo-credentials">
            <h4><i class="fas fa-info-circle"></i> ข้อมูลทดสอบ</h4>
            <p><strong>Email:</strong> user@example.com</p>
            <p><strong>Password:</strong> password123</p>
        </div>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> อีเมล
                </label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email" 
                        placeholder="กรอกอีเมล"
                        required
                        autocomplete="email"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> รหัสผ่าน
                </label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="password" 
                        name="password" 
                        placeholder="กรอกรหัสผ่าน"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
            </button>
        </form>

        <div class="forgot-password">
            <a href="forgot_password.php">
                <i class="fas fa-question-circle"></i> ลืมรหัสผ่าน?
            </a>
        </div>

        <div class="register-link">
            ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Auto-fill demo credentials (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('demo') === '1') {
                document.getElementById('email').value = 'user@example.com';
                document.getElementById('password').value = 'password123';
            }
        });
    </script>
</body>
</html>
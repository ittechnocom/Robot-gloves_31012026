<?php
session_start();

// ===== API ENDPOINTS =====
// ตรวจสอบว่าเป็นการเรียก API หรือไม่
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    // Database Configuration
    $host = '127.0.0.1';
    $dbname = 'robotic_handwear';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // API: ดึงข้อมูล sensor ล่าสุด
        if ($_GET['api'] === 'get_latest_sensor') {
            // Debug: แสดง user_id
            error_log("Fetching data for user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
            
            $stmt = $pdo->prepare("
                SELECT * FROM sensor_data 
                WHERE user_id = :user_id 
                ORDER BY insert_id DESC 
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // ถ้าไม่มีข้อมูลสำหรับ user นี้ ให้ดึงข้อมูลล่าสุดทั้งหมด
            if (!$data) {
                error_log("No data for user " . $_SESSION['user_id'] . ", fetching latest record");
                $stmt = $pdo->query("SELECT * FROM sensor_data ORDER BY insert_id DESC LIMIT 1");
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'debug' => [
                    'user_id' => $_SESSION['user_id'],
                    'found_records' => $data ? 'yes' : 'no'
                ]
            ]);
            exit;
        }
        
        // API: ดึงข้อมูล relay status
        if ($_GET['api'] === 'get_relay_status') {
            $stmt = $pdo->query("SELECT * FROM relay_control WHERE id = 1");
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            exit;
        }
        
        // API: ดึงข้อมูลสำหรับกราฟ (20 รายการล่าสุด)
        if ($_GET['api'] === 'get_chart_data') {
            // ลองดึงข้อมูลของ user ก่อน
            $stmt = $pdo->prepare("
                SELECT 
                    insert_id,
                    acceleration,
                    gyroscope,
                    angle_z,
                    heart_rate,
                    spo2,
                    speed_type,
                    DATE_FORMAT(created_at, '%H:%i:%s') as time_label
                FROM sensor_data 
                WHERE user_id = :user_id 
                ORDER BY insert_id DESC 
                LIMIT 20
            ");
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ถ้าไม่มีข้อมูล ให้ดึงข้อมูลล่าสุดทั้งหมด
            if (empty($data)) {
                $stmt = $pdo->query("
                    SELECT 
                        insert_id,
                        acceleration,
                        gyroscope,
                        angle_z,
                        heart_rate,
                        spo2,
                        speed_type,
                        DATE_FORMAT(created_at, '%H:%i:%s') as time_label
                    FROM sensor_data 
                    ORDER BY insert_id DESC 
                    LIMIT 20
                ");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            $data = array_reverse($data);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            exit;
        }
        
        // API: ดึงสถิติสุขภาพ
        if ($_GET['api'] === 'get_health_stats') {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_compressions,
                    AVG(heart_rate) as avg_hr,
                    AVG(spo2) as avg_spo2,
                    MAX(heart_rate) as max_hr,
                    MIN(heart_rate) as min_hr,
                    SUM(slow_count) as total_slow,
                    SUM(medium_count) as total_medium,
                    SUM(fast_count) as total_fast
                FROM sensor_data 
                WHERE user_id = :user_id 
                AND DATE(created_at) = CURDATE()
                AND heart_rate > 0
            ");
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            exit;
        }
        
        // API: รีเซ็ตตัวนับ
        if ($_GET['api'] === 'reset_counters' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // ในระบบจริง คุณอาจต้องการอัพเดทข้อมูลใน database
            echo json_encode([
                'success' => true,
                'message' => 'รีเซ็ตตัวนับสำเร็จ'
            ]);
            exit;
        }
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// ===== NORMAL PAGE RENDERING =====
// ตรวจสอบว่า Login แล้วหรือยัง
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ดึงข้อมูลผู้ใช้
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'ผู้ใช้';
$user_email = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบถุงมือหุ่นยนต์บำบัด - Health Monitoring</title>
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
            max-width: 1800px;
            margin: 0 auto;
        }

        /* User Info Bar */
        .user-bar {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 15px 30px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .user-details h3 {
            font-size: 18px;
            margin-bottom: 4px;
        }

        .user-details p {
            font-size: 14px;
            opacity: 0.8;
        }

        .logout-btn {
            padding: 10px 25px;
            background: rgba(239, 68, 68, 0.2);
            border: 2px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Prompt', sans-serif;
            font-size: 14px;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.4);
            border-color: rgba(239, 68, 68, 0.5);
            transform: translateY(-2px);
        }

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .tab-btn {
            flex: 1;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Prompt', sans-serif;
            background: rgba(255,255,255,0.1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .tab-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Header */
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
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .connection-status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            margin-top: 15px;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        .status-indicator.connected {
            background: #10b981;
            box-shadow: 0 0 20px #10b981;
        }

        .status-indicator.disconnected {
            background: #ef4444;
            box-shadow: 0 0 20px #ef4444;
            animation: none;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
        }

        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Sensor Display */
        .sensor-panel {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }

        .panel-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sensor-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .sensor-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .sensor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }

        .sensor-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin: 0 auto 16px;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .sensor-label {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .sensor-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        .sensor-unit {
            font-size: 1rem;
            color: #64748b;
            margin-top: 4px;
        }

        /* Health Cards */
        .health-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .health-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .health-card.heart {
            border-left: 4px solid #ef4444;
        }

        .health-card.oxygen {
            border-left: 4px solid #3b82f6;
        }

        .health-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }

        /* Relay Control */
        .relay-panel {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }

        .relay-grid {
            display: grid;
            gap: 15px;
        }

        .relay-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .relay-item.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            color: white;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }

        .relay-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .relay-indicator {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .relay-item.active .relay-indicator {
            background: rgba(255,255,255,0.3);
            box-shadow: 0 0 30px rgba(255,255,255,0.5);
            animation: relayPulse 1.5s ease-in-out infinite;
        }

        @keyframes relayPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .relay-text h3 {
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .relay-text p {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        /* Counter Panel */
        .counter-panel {
            grid-column: 1 / -1;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }

        .counter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .counter-item {
            text-align: center;
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        .counter-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .counter-item.slow { border-left-color: #10b981; }
        .counter-item.medium { border-left-color: #f59e0b; }
        .counter-item.fast { border-left-color: #ef4444; }

        .counter-emoji {
            font-size: 3rem;
            margin-bottom: 12px;
        }

        .counter-value {
            font-size: 3rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .counter-label {
            font-size: 1rem;
            color: #64748b;
            font-weight: 500;
        }

        /* Chart Container */
        .chart-panel {
            grid-column: 1 / -1;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }

        #dataChart, #healthChart {
            width: 100%;
            height: 400px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 400px;
            width: 100%;
        }

        /* Control Buttons */
        .control-panel {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .control-btn {
            padding: 16px 40px;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: 'Prompt', sans-serif;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }

        .control-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.3);
        }

        .control-btn.primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .control-btn.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .control-btn.secondary {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .control-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Last Update */
        .last-update {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.9);
            font-size: 0.95rem;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 12px 24px;
            border-radius: 20px;
            display: inline-block;
        }

        .update-container {
            text-align: center;
        }

        /* Loading */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            color: #0f172a;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
            display: none;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }

        .toast.show {
            display: flex;
        }

        .toast.success { border-left: 4px solid #10b981; }
        .toast.error { border-left: 4px solid #ef4444; }
        .toast.info { border-left: 4px solid #3b82f6; }

        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Health Status Badge */
        .health-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 8px;
        }

        .health-status.good {
            background: #dcfce7;
            color: #166534;
        }

        .health-status.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .health-status.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .health-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sensor-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .tab-btn {
                font-size: 0.9rem;
                padding: 12px 20px;
            }

            .user-bar {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- User Info Bar -->
        <div class="user-bar">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($user_name); ?></h3>
                    <p><?php echo htmlspecialchars($user_email); ?></p>
                    <small style="opacity: 0.7;">User ID: <?php echo $user_id; ?></small>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="logout-btn" onclick="window.location.href='profile.php'" style="background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.3);">
                    <i class="fas fa-user-cog"></i> ตั้งค่า
                </button>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                </button>
            </div>
        </div>

        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-hand-sparkles"></i> ระบบถุงมือหุ่นยนต์บำบัด</h1>
            <p>Robotic Handwear Therapy System with Health Monitoring</p>
            <div class="connection-status">
                <div class="status-indicator disconnected" id="connectionIndicator"></div>
                <span id="connectionText">กำลังเชื่อมต่อ...</span>
                <span class="loading-spinner" id="loadingSpinner"></span>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="switchTab('sensor')" id="tabSensor">
                <i class="fas fa-gauge-high"></i> เซนเซอร์และการควบคุม
            </button>
            <button class="tab-btn" onclick="switchTab('health')" id="tabHealth">
                <i class="fas fa-heart-pulse"></i> ข้อมูลสุขภาพ
            </button>
            <button class="tab-btn" onclick="switchTab('exercise')" id="tabExercise">
                <i class="fas fa-dumbbell"></i> การออกกำลังกาย
            </button>
        </div>

        <!-- Tab Content 1: Sensor & Control -->
        <div class="tab-content active" id="contentSensor">
            <div class="main-grid">
                <!-- Left Column: Sensors -->
                <div class="sensor-panel">
                    <div class="panel-title">
                        <i class="fas fa-gauge-high"></i>
                        ข้อมูลเซ็นเซอร์แบบเรียลไทม์
                    </div>
                    
                    <div class="sensor-grid">
                        <div class="sensor-card">
                            <div class="sensor-icon">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <div class="sensor-label">ความเร่ง</div>
                            <div class="sensor-value" id="accelerationValue">0.0</div>
                            <div class="sensor-unit">m/s²</div>
                        </div>

                        <div class="sensor-card">
                            <div class="sensor-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                            <div class="sensor-label">ไจโรสโคป</div>
                            <div class="sensor-value" id="gyroscopeValue">0.0</div>
                            <div class="sensor-unit">rad/s</div>
                        </div>

                        <div class="sensor-card">
                            <div class="sensor-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                                <i class="fas fa-compass"></i>
                            </div>
                            <div class="sensor-label">มุม Z-Axis</div>
                            <div class="sensor-value" id="angleZValue">0.0</div>
                            <div class="sensor-unit">องศา</div>
                        </div>
                    </div>

                    <div class="panel-title" style="margin-top: 30px;">
                        <i class="fas fa-clipboard-list"></i>
                        ประเภทการตบมือล่าสุด
                    </div>
                    <div id="lastClapType" style="text-align: center; font-size: 3rem; padding: 20px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 16px; margin-top: 15px;">
                        <span style="color: #64748b;">รอการตรวจจับ...</span>
                    </div>
                </div>

                <!-- Right Column: Relay Control -->
                <div class="relay-panel">
                    <div class="panel-title">
                        <i class="fas fa-toggle-on"></i>
                        สถานะรีเลย์
                    </div>
                    
                    <div class="relay-grid">
                        <div class="relay-item" id="relay1">
                            <div class="relay-info">
                                <div class="relay-indicator">
                                    <i class="fas fa-power-off"></i>
                                </div>
                                <div class="relay-text">
                                    <h3>รีเลย์ 1</h3>
                                    <p>SLOW 🐢</p>
                                </div>
                            </div>
                            <div class="relay-status">OFF</div>
                        </div>

                        <div class="relay-item" id="relay2">
                            <div class="relay-info">
                                <div class="relay-indicator">
                                    <i class="fas fa-power-off"></i>
                                </div>
                                <div class="relay-text">
                                    <h3>รีเลย์ 2</h3>
                                    <p>MEDIUM 🚶</p>
                                </div>
                            </div>
                            <div class="relay-status">OFF</div>
                        </div>

                        <div class="relay-item" id="relay3">
                            <div class="relay-info">
                                <div class="relay-indicator">
                                    <i class="fas fa-power-off"></i>
                                </div>
                                <div class="relay-text">
                                    <h3>รีเลย์ 3</h3>
                                    <p>FAST 🏃</p>
                                </div>
                            </div>
                            <div class="relay-status">OFF</div>
                        </div>
                    </div>
                </div>

                <!-- Counter Panel -->
                <div class="counter-panel">
                    <div class="panel-title">
                        <i class="fas fa-hand-paper"></i>
                        สถิติการตบมือ
                    </div>
                    
                    <div class="counter-grid">
                        <div class="counter-item slow">
                            <div class="counter-emoji">🐢</div>
                            <div class="counter-value" id="slowCount">0</div>
                            <div class="counter-label">SLOW</div>
                        </div>

                        <div class="counter-item medium">
                            <div class="counter-emoji">🚶</div>
                            <div class="counter-value" id="mediumCount">0</div>
                            <div class="counter-label">MEDIUM</div>
                        </div>

                        <div class="counter-item fast">
                            <div class="counter-emoji">🏃</div>
                            <div class="counter-value" id="fastCount">0</div>
                            <div class="counter-label">FAST</div>
                        </div>

                        <div class="counter-item" style="border-left-color: #8b5cf6;">
                            <div class="counter-emoji">📊</div>
                            <div class="counter-value" id="totalCount">0</div>
                            <div class="counter-label">ทั้งหมด</div>
                        </div>
                    </div>
                </div>

                <!-- Chart Panel -->
                <div class="chart-panel">
                    <div class="panel-title">
                        <i class="fas fa-chart-line"></i>
                        กราฟข้อมูลแบบเรียลไทม์
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="dataChart"></canvas>
                    </div>
                </div>

                <!-- Control Panel -->
                <div class="control-panel">
                    <button class="control-btn primary" id="startBtn" onclick="startMonitoring()">
                        <i class="fas fa-play"></i> เริ่มการตรวจสอบ
                    </button>
                    <button class="control-btn danger" id="stopBtn" onclick="stopMonitoring()" style="display: none;">
                        <i class="fas fa-stop"></i> หยุดการตรวจสอบ
                    </button>
                    <button class="control-btn secondary" onclick="resetCounters()">
                        <i class="fas fa-redo"></i> รีเซ็ตค่า
                    </button>
                </div>

                <!-- Last Update -->
                <div class="update-container">
                    <div class="last-update">
                        <i class="fas fa-clock"></i> อัพเดทล่าสุด: <span id="lastUpdateTime">-</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content 2: Health -->
        <div class="tab-content" id="contentHealth">
            <div class="main-grid">
                <div class="sensor-panel">
                    <div class="panel-title">
                        <i class="fas fa-heart-pulse"></i>
                        ข้อมูลสุขภาพแบบเรียลไทม์
                    </div>
                    
                    <div class="health-grid">
                        <div class="health-card heart">
                            <div class="sensor-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div class="sensor-label">อัตราการเต้นหัวใจ</div>
                            <div class="sensor-value" id="heartRateValue">0</div>
                            <div class="sensor-unit">BPM</div>
                            <div class="health-status good" id="heartRateStatus">ปกติ</div>
                        </div>

                        <div class="health-card oxygen">
                            <div class="sensor-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                                <i class="fas fa-lungs"></i>
                            </div>
                            <div class="sensor-label">ออกซิเจนในเลือด</div>
                            <div class="sensor-value" id="spo2Value">0</div>
                            <div class="sensor-unit">%</div>
                            <div class="health-status good" id="spo2Status">ปกติ</div>
                        </div>
                    </div>
                </div>

                <div class="relay-panel">
                    <div class="panel-title">
                        <i class="fas fa-clipboard-check"></i>
                        สรุปสุขภาพวันนี้
                    </div>
                    
                    <div class="relay-grid" style="gap: 20px;">
                        <div style="padding: 20px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 16px;">
                            <h4 style="color: #0f172a; margin-bottom: 15px;">
                                <i class="fas fa-chart-bar"></i> สถิติการออกกำลังกาย
                            </h4>
                            <div style="color: #64748b;">
                                <p style="margin-bottom: 8px;">
                                    <strong>จำนวนการบีบมือ:</strong> 
                                    <span id="healthTotalCompressions">0</span> ครั้ง
                                </p>
                                <p style="margin-bottom: 8px;">
                                    <strong>HR เฉลี่ย:</strong> 
                                    <span id="healthAvgHR">0</span> BPM
                                </p>
                                <p style="margin-bottom: 8px;">
                                    <strong>SpO2 เฉลี่ย:</strong> 
                                    <span id="healthAvgSpO2">0</span> %
                                </p>
                            </div>
                        </div>

                        <div style="padding: 20px; background: linear-gradient(135deg, #dcfce7, #bbf7d0); border-radius: 16px;">
                            <h4 style="color: #0f172a; margin-bottom: 15px;">
                                <i class="fas fa-star"></i> ประเมินผล
                            </h4>
                            <div style="color: #166534; font-size: 1.1rem; font-weight: 600;" id="healthPerformance">
                                กำลังประเมิน...
                            </div>
                        </div>

                        <div style="padding: 20px; background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 16px;">
                            <h4 style="color: #0f172a; margin-bottom: 15px;">
                                <i class="fas fa-exclamation-triangle"></i> คำแนะนำ
                            </h4>
                            <div style="color: #92400e; font-size: 0.95rem;" id="healthRecommendations">
                                ยังไม่มีคำแนะนำ
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chart-panel">
                    <div class="panel-title">
                        <i class="fas fa-chart-area"></i>
                        กราฟข้อมูลสุขภาพ (1 ชั่วโมงล่าสุด)
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="healthChart"></canvas>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="control-panel">
                    <button class="control-btn primary" onclick="openHealthSurvey()">
                        <i class="fas fa-clipboard-list"></i> แบบสอบถามสุขภาพ
                    </button>
                    <button class="control-btn secondary" onclick="refreshHealthData()">
                        <i class="fas fa-sync"></i> รีเฟรชข้อมูล
                    </button>
                    <button class="control-btn secondary" onclick="exportHealthReport()">
                        <i class="fas fa-download"></i> ส่งออกรายงาน
                    </button>
                </div>

                <!-- Last Update -->
                <div class="update-container">
                    <div class="last-update">
                        <i class="fas fa-clock"></i> อัพเดทล่าสุด: <span id="healthLastUpdate">-</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content 3: Exercise -->
        <div class="tab-content" id="contentExercise">
            <div style="text-align: center; padding: 60px; background: rgba(255,255,255,0.1); border-radius: 24px;">
                <div style="font-size: 5rem; margin-bottom: 20px;">💪</div>
                <h2 style="font-size: 2rem; margin-bottom: 15px;">การออกกำลังกาย</h2>
                <p style="font-size: 1.2rem; margin-bottom: 30px; opacity: 0.9;">
                    ฟีเจอร์นี้กำลังพัฒนา
                </p>
                <button class="control-btn primary" onclick="window.location.href='exercise_settings.php'">
                    <i class="fas fa-gear"></i> ตั้งค่าการออกกำลังกาย
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const currentUserId = <?php echo $user_id; ?>;
        let isMonitoring = false;
        let monitoringInterval;
        let sensorChart, healthChart;

        // Initialize Charts
        function initCharts() {
            // Sensor Chart
            const sensorCtx = document.getElementById('dataChart').getContext('2d');
            sensorChart = new Chart(sensorCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'ความเร่ง (m/s²)',
                            data: [],
                            borderColor: '#667eea',
                            tension: 0.4
                        },
                        {
                            label: 'ไจโรสโคป (rad/s)',
                            data: [],
                            borderColor: '#f093fb',
                            tension: 0.4
                        },
                        {
                            label: 'มุม Z (องศา)',
                            data: [],
                            borderColor: '#4facfe',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 300 }
                }
            });

            // Health Chart
            const healthCtx = document.getElementById('healthChart').getContext('2d');
            healthChart = new Chart(healthCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Heart Rate (BPM)',
                            data: [],
                            borderColor: '#ef4444',
                            tension: 0.4
                        },
                        {
                            label: 'SpO2 (%)',
                            data: [],
                            borderColor: '#3b82f6',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 300 }
                }
            });
        }

        // Fetch Latest Sensor Data
        async function fetchSensorData() {
            try {
                console.log('Fetching sensor data...');
                const response = await fetch('?api=get_latest_sensor');
                const result = await response.json();
                
                console.log('API Response:', result);
                
                if (result.success && result.data) {
                    console.log('Sensor Data:', result.data);
                    updateSensorDisplay(result.data);
                    updateRelayStatus();
                    updateChartData();
                } else {
                    console.error('No data returned or error:', result);
                    showToast('ไม่พบข้อมูล', 'warning');
                }
            } catch (error) {
                console.error('Error fetching sensor data:', error);
                showToast('เกิดข้อผิดพลาดในการดึงข้อมูล: ' + error.message, 'error');
            }
        }

        // Update Sensor Display
        function updateSensorDisplay(data) {
            console.log('Updating display with data:', data);
            
            // Update sensor values - with null/undefined checks
            const accel = data.acceleration !== null ? parseFloat(data.acceleration).toFixed(2) : '0.0';
            const gyro = data.gyroscope !== null ? parseFloat(data.gyroscope).toFixed(2) : '0.0';
            const angle = data.angle_z !== null ? parseFloat(data.angle_z).toFixed(1) : '0.0';
            
            document.getElementById('accelerationValue').textContent = accel;
            document.getElementById('gyroscopeValue').textContent = gyro;
            document.getElementById('angleZValue').textContent = angle;
            
            console.log('Sensor values updated:', { accel, gyro, angle });
            
            // Update counters
            const slowCount = parseInt(data.slow_count) || 0;
            const mediumCount = parseInt(data.medium_count) || 0;
            const fastCount = parseInt(data.fast_count) || 0;
            
            document.getElementById('slowCount').textContent = slowCount;
            document.getElementById('mediumCount').textContent = mediumCount;
            document.getElementById('fastCount').textContent = fastCount;
            
            console.log('Counters updated:', { slowCount, mediumCount, fastCount });
            
            const total = (parseInt(data.slow_count) || 0) + 
                         (parseInt(data.medium_count) || 0) + 
                         (parseInt(data.fast_count) || 0);
            document.getElementById('totalCount').textContent = total;
            
            // Update speed type display
            const speedEmojis = {
                'SLOW': '🐢 SLOW',
                'MEDIUM': '🚶 MEDIUM',
                'FAST': '🏃 FAST',
                'IDLE': '⏸️ พัก',
                'UNKNOWN': '❓ ไม่ทราบ'
            };
            document.getElementById('lastClapType').innerHTML = `
                <span style="color: #0f172a;">${speedEmojis[data.speed_type] || '⏸️ พัก'}</span>
            `;
            
            // Update health values
            document.getElementById('heartRateValue').textContent = data.heart_rate || 0;
            document.getElementById('spo2Value').textContent = data.spo2 || 0;
            
            // Update health status
            updateHealthStatus(data.heart_rate, data.spo2);
            
            // Update timestamp
            document.getElementById('lastUpdateTime').textContent = new Date().toLocaleTimeString('th-TH');
            
            // Update connection status
            document.getElementById('connectionIndicator').className = 'status-indicator connected';
            document.getElementById('connectionText').textContent = 'เชื่อมต่อแล้ว';
            document.getElementById('loadingSpinner').style.display = 'none';
        }

        // Update Health Status
        function updateHealthStatus(hr, spo2) {
            const hrStatus = document.getElementById('heartRateStatus');
            const spo2Status = document.getElementById('spo2Status');
            
            // Heart Rate Status
            if (hr < 60 || hr > 100) {
                hrStatus.className = 'health-status warning';
                hrStatus.textContent = hr < 60 ? 'ต่ำ' : 'สูง';
            } else {
                hrStatus.className = 'health-status good';
                hrStatus.textContent = 'ปกติ';
            }
            
            // SpO2 Status
            if (spo2 < 95) {
                spo2Status.className = 'health-status danger';
                spo2Status.textContent = 'ต่ำ';
            } else if (spo2 >= 95 && spo2 <= 100) {
                spo2Status.className = 'health-status good';
                spo2Status.textContent = 'ปกติ';
            }
        }

        // Update Relay Status
        async function updateRelayStatus() {
            try {
                const response = await fetch('?api=get_relay_status');
                const result = await response.json();
                
                if (result.success && result.data) {
                    for (let i = 1; i <= 3; i++) {
                        const relay = document.getElementById(`relay${i}`);
                        const isOn = result.data[`relay_${i}`] == 1;
                        
                        if (isOn) {
                            relay.classList.add('active');
                            relay.querySelector('.relay-status').textContent = 'ON';
                        } else {
                            relay.classList.remove('active');
                            relay.querySelector('.relay-status').textContent = 'OFF';
                        }
                    }
                }
            } catch (error) {
                console.error('Error fetching relay status:', error);
            }
        }

        // Update Chart Data
        async function updateChartData() {
            try {
                const response = await fetch('?api=get_chart_data');
                const result = await response.json();
                
                if (result.success && result.data) {
                    const labels = result.data.map(d => d.time_label);
                    const accel = result.data.map(d => parseFloat(d.acceleration));
                    const gyro = result.data.map(d => parseFloat(d.gyroscope));
                    const angle = result.data.map(d => parseFloat(d.angle_z));
                    const hr = result.data.map(d => parseInt(d.heart_rate));
                    const spo2 = result.data.map(d => parseInt(d.spo2));
                    
                    // Update sensor chart
                    sensorChart.data.labels = labels;
                    sensorChart.data.datasets[0].data = accel;
                    sensorChart.data.datasets[1].data = gyro;
                    sensorChart.data.datasets[2].data = angle;
                    sensorChart.update('none');
                    
                    // Update health chart
                    healthChart.data.labels = labels;
                    healthChart.data.datasets[0].data = hr;
                    healthChart.data.datasets[1].data = spo2;
                    healthChart.update('none');
                }
            } catch (error) {
                console.error('Error updating chart:', error);
            }
        }

        // Load Health Stats
        async function loadHealthStats() {
            try {
                const response = await fetch('?api=get_health_stats');
                const result = await response.json();
                
                if (result.success && result.data) {
                    const stats = result.data;
                    
                    // Update summary stats
                    document.getElementById('healthTotalCompressions').textContent = stats.total_compressions || 0;
                    document.getElementById('healthAvgHR').textContent = Math.round(stats.avg_hr) || 0;
                    document.getElementById('healthAvgSpO2').textContent = Math.round(stats.avg_spo2) || 0;
                    
                    // Performance evaluation
                    const performance = document.getElementById('healthPerformance');
                    const totalCompressions = stats.total_compressions || 0;
                    
                    if (totalCompressions === 0) {
                        performance.textContent = 'ยังไม่มีข้อมูล';
                        performance.style.color = '#64748b';
                    } else if (totalCompressions < 50) {
                        performance.textContent = '⭐ เริ่มต้นดี';
                        performance.style.color = '#f59e0b';
                    } else if (totalCompressions < 100) {
                        performance.textContent = '⭐⭐ ดีมาก';
                        performance.style.color = '#10b981';
                    } else {
                        performance.textContent = '⭐⭐⭐ ยอดเยี่ยม!';
                        performance.style.color = '#059669';
                    }
                    
                    // Recommendations
                    const recommendations = document.getElementById('healthRecommendations');
                    const avgHR = Math.round(stats.avg_hr) || 0;
                    const avgSpO2 = Math.round(stats.avg_spo2) || 0;
                    
                    let tips = [];
                    
                    if (avgHR === 0 && avgSpO2 === 0) {
                        tips.push('เริ่มต้นออกกำลังกายเพื่อบันทึกข้อมูลสุขภาพ');
                    } else {
                        if (avgHR > 0 && avgHR < 60) {
                            tips.push('💙 อัตราการเต้นหัวใจต่ำ - ควรปรึกษาแพทย์');
                        } else if (avgHR > 100) {
                            tips.push('❤️ อัตราการเต้นหัวใจสูง - พักผ่อนให้เพียงพอ');
                        } else if (avgHR > 0) {
                            tips.push('💚 อัตราการเต้นหัวใจปกติ');
                        }
                        
                        if (avgSpO2 > 0 && avgSpO2 < 95) {
                            tips.push('🫁 ออกซิเจนในเลือดต่ำ - ควรหายใจลึกๆ');
                        } else if (avgSpO2 >= 95) {
                            tips.push('✅ ออกซิเจนในเลือดอยู่ในเกณฑ์ดี');
                        }
                        
                        if (totalCompressions > 0) {
                            tips.push('💪 ออกกำลังกายอย่างสม่ำเสมอเพื่อสุขภาพที่ดี');
                        }
                    }
                    
                    recommendations.innerHTML = tips.length > 0 ? tips.join('<br>') : 'ยังไม่มีคำแนะนำ';
                    
                    // Update last update time
                    document.getElementById('healthLastUpdate').textContent = new Date().toLocaleTimeString('th-TH');
                }
            } catch (error) {
                console.error('Error loading health stats:', error);
            }
        }

        // Open Health Survey
        function openHealthSurvey() {
            window.location.href = 'health_survey.php';
        }

        // Refresh Health Data
        async function refreshHealthData() {
            showToast('กำลังรีเฟรชข้อมูล...', 'info');
            await loadHealthStats();
            await fetchSensorData();
            showToast('รีเฟรชข้อมูลสำเร็จ!', 'success');
        }

        // Export Health Report
        async function exportHealthReport() {
            try {
                showToast('กำลังสร้างรายงาน...', 'info');
                
                // Get health stats
                const response = await fetch('?api=get_health_stats');
                const result = await response.json();
                
                if (result.success && result.data) {
                    const stats = result.data;
                    const today = new Date().toLocaleDateString('th-TH');
                    
                    // Create report text
                    let report = `รายงานสุขภาพประจำวัน\n`;
                    report += `วันที่: ${today}\n`;
                    report += `ชื่อ: <?php echo htmlspecialchars($user_name); ?>\n`;
                    report += `\n--- สรุปการออกกำลังกาย ---\n`;
                    report += `จำนวนการบีบมือ: ${stats.total_compressions || 0} ครั้ง\n`;
                    report += `- SLOW: ${stats.total_slow || 0} ครั้ง\n`;
                    report += `- MEDIUM: ${stats.total_medium || 0} ครั้ง\n`;
                    report += `- FAST: ${stats.total_fast || 0} ครั้ง\n`;
                    report += `\n--- ข้อมูลสุขภาพ ---\n`;
                    report += `อัตราการเต้นหัวใจเฉลี่ย: ${Math.round(stats.avg_hr) || 0} BPM\n`;
                    report += `อัตราการเต้นหัวใจสูงสุด: ${stats.max_hr || 0} BPM\n`;
                    report += `อัตราการเต้นหัวใจต่ำสุด: ${stats.min_hr || 0} BPM\n`;
                    report += `ออกซิเจนในเลือดเฉลี่ย: ${Math.round(stats.avg_spo2) || 0} %\n`;
                    report += `\n--- คำแนะนำ ---\n`;
                    report += `ออกกำลังกายอย่างสม่ำเสมอเพื่อสุขภาพที่ดี\n`;
                    report += `ดื่มน้ำให้เพียงพอ\n`;
                    report += `พักผ่อนให้เพียงพอ\n`;
                    
                    // Create and download file
                    const blob = new Blob([report], { type: 'text/plain;charset=utf-8' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `health_report_${new Date().getTime()}.txt`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    showToast('ส่งออกรายงานสำเร็จ!', 'success');
                } else {
                    showToast('ไม่พบข้อมูลสำหรับส่งออก', 'warning');
                }
            } catch (error) {
                console.error('Error exporting report:', error);
                showToast('เกิดข้อผิดพลาดในการส่งออกรายงาน', 'error');
            }
        }

        // Start Monitoring
        function startMonitoring() {
            if (!isMonitoring) {
                isMonitoring = true;
                document.getElementById('startBtn').style.display = 'none';
                document.getElementById('stopBtn').style.display = 'inline-flex';
                
                // Fetch immediately
                fetchSensorData();
                
                // Then fetch every 2 seconds
                monitoringInterval = setInterval(fetchSensorData, 2000);
                
                showToast('เริ่มการตรวจสอบแล้ว', 'success');
            }
        }

        // Stop Monitoring
        function stopMonitoring() {
            if (isMonitoring) {
                isMonitoring = false;
                clearInterval(monitoringInterval);
                document.getElementById('startBtn').style.display = 'inline-flex';
                document.getElementById('stopBtn').style.display = 'none';
                
                document.getElementById('connectionIndicator').className = 'status-indicator disconnected';
                document.getElementById('connectionText').textContent = 'หยุดการตรวจสอบ';
                
                showToast('หยุดการตรวจสอบแล้ว', 'info');
            }
        }

        // Reset Counters
        async function resetCounters() {
            if (confirm('คุณต้องการรีเซ็ตค่าทั้งหมดหรือไม่?')) {
                try {
                    const response = await fetch('?api=reset_counters', { method: 'POST' });
                    const result = await response.json();
                    
                    if (result.success) {
                        document.getElementById('slowCount').textContent = '0';
                        document.getElementById('mediumCount').textContent = '0';
                        document.getElementById('fastCount').textContent = '0';
                        document.getElementById('totalCount').textContent = '0';
                        
                        showToast('รีเซ็ตค่าสำเร็จ', 'success');
                    }
                } catch (error) {
                    console.error('Error resetting counters:', error);
                    showToast('เกิดข้อผิดพลาด', 'error');
                }
            }
        }

        // Switch Tab
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            document.getElementById(`tab${tab.charAt(0).toUpperCase() + tab.slice(1)}`).classList.add('active');
            document.getElementById(`content${tab.charAt(0).toUpperCase() + tab.slice(1)}`).classList.add('active');
            
            if (tab === 'health') {
                loadHealthStats();
            }
        }

        // Logout
        function logout() {
            if (confirm('คุณต้องการออกจากระบบหรือไม่?')) {
                window.location.href = 'logout.php';
            }
        }

        // Show Toast
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toast.className = `toast ${type} show`;
            toastMessage.textContent = message;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            fetchSensorData(); // Load initial data
        });
    </script>
</body>
</html>
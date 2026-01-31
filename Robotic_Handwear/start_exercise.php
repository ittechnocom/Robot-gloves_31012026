<?php
session_start();

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// เชื่อมต่อฐานข้อมูล
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
$sql_user = "SELECT user_id, user_email, user_fname, user_lname, result_health_survey, 
             exercise_intensity, rounds_per_minute FROM tb_user WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user_data = $result_user->fetch_assoc();

// ตรวจสอบว่าตั้งค่าแล้วหรือยัง
if (!$user_data['exercise_intensity'] || !$user_data['rounds_per_minute']) {
    header('Location: exercise_settings.php');
    exit();
}

// บันทึก session เริ่มออกกำลังกาย
if (!isset($_SESSION['exercise_session_id'])) {
    $sql_session = "INSERT INTO tb_exercise_session (user_id, exercise_intensity, rounds_per_minute, 
                    start_time, status) VALUES (?, ?, ?, NOW(), 'active')";
    $stmt_session = $conn->prepare($sql_session);
    $stmt_session->bind_param("isi", $user_id, $user_data['exercise_intensity'], $user_data['rounds_per_minute']);
    $stmt_session->execute();
    $_SESSION['exercise_session_id'] = $conn->insert_id;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เริ่มออกกำลังกาย - Robotic Handwear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --light-color: #28a745;
            --medium-color: #ffc107;
            --high-color: #dc3545;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .exercise-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            margin: 30px auto;
            max-width: 1000px;
        }
        
        .status-bar {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .exercise-control {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .timer-display {
            font-size: 4rem;
            font-weight: bold;
            color: var(--primary-color);
            text-align: center;
            margin: 30px 0;
        }
        
        .rounds-counter {
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
        
        .progress-circle {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 10px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px auto;
            position: relative;
        }
        
        .btn-control {
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 50px;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-start {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-pause {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
        }
        
        .btn-stop {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        .btn-control:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        .sensor-display {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid var(--primary-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sensor-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .intensity-badge {
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 1.2rem;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-light {
            background: var(--light-color);
            color: white;
        }
        
        .badge-medium {
            background: var(--medium-color);
            color: white;
        }
        
        .badge-high {
            background: var(--high-color);
            color: white;
        }

        /* Popup Recommendation */
        .recommendation-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .popup-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            text-align: center;
            animation: popupScale 0.3s ease-out;
        }
        
        @keyframes popupScale {
            from {
                transform: scale(0.8);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .popup-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }
        
        .popup-title {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .popup-details {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 25px;
        }
        
        .recommendation-list {
            text-align: left;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .recommendation-list li {
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <!-- Recommendation Popup -->
    <div class="recommendation-popup" id="recommendationPopup">
        <div class="popup-content">
            <?php
            $intensity = $user_data['exercise_intensity'];
            $rounds = $user_data['rounds_per_minute'];
            
            $recommendations = [
                'LIGHT' => [
                    'icon' => '🟢',
                    'emoji' => '😊',
                    'color' => 'light',
                    'title' => 'การออกกำลังกายระดับเบา',
                    'tips' => [
                        'เริ่มต้นอย่างช้าๆ อย่าเร่งรีบ',
                        'หายใจเข้าลึกๆ ผ่อนคลาย',
                        'หยุดพักทันทีหากรู้สึกเหนื่อย',
                        'ดื่มน้ำเล็กน้อยระหว่างพัก',
                        'ฝึกต่อเนื่อง 5-10 นาที'
                    ]
                ],
                'MEDIUM' => [
                    'icon' => '🟡',
                    'emoji' => '💪',
                    'color' => 'medium',
                    'title' => 'การออกกำลังกายระดับกลาง',
                    'tips' => [
                        'รักษาจังหวะการเคลื่อนไหวให้สม่ำเสมอ',
                        'หายใจเข้าออกอย่างสม่ำเสมอ',
                        'พักเล็กน้อยทุกๆ 3-5 นาที',
                        'ดื่มน้ำทุก 5 นาที',
                        'ฝึกต่อเนื่อง 10-15 นาที'
                    ]
                ],
                'HIGH' => [
                    'icon' => '🔴',
                    'emoji' => '🔥',
                    'color' => 'high',
                    'title' => 'การออกกำลังกายระดับแรง',
                    'tips' => [
                        'เริ่มต้นด้วยการอุ่นเครื่อง 2-3 นาที',
                        'ใช้แรงเต็มที่ แต่คงท่าทางที่ถูกต้อง',
                        'หายใจเข้าลึกๆ ช่วยเพิ่มออกซิเจน',
                        'พักสั้นๆ ทุกๆ 2-3 นาที',
                        'ดื่มน้ำเป็นประจำ',
                        'คลายกล้ามเนื้อหลังเสร็จ'
                    ]
                ]
            ];
            
            $current_rec = $recommendations[$intensity];
            ?>
            
            <div class="popup-icon"><?= $current_rec['emoji'] ?></div>
            <div class="popup-title">
                <?= $current_rec['icon'] ?> <?= $current_rec['title'] ?>
            </div>
            <div class="popup-details">
                คุณจะออกกำลังกายที่ <strong><?= $rounds ?> รอบต่อนาที</strong>
            </div>
            
            <div class="recommendation-list">
                <h5 class="mb-3"><i class="bi bi-lightbulb-fill text-warning"></i> คำแนะนำ</h5>
                <ul>
                    <?php foreach ($current_rec['tips'] as $tip): ?>
                    <li><i class="bi bi-check-circle-fill text-success"></i> <?= $tip ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <button class="btn btn-primary btn-lg px-5" onclick="closePopup()">
                <i class="bi bi-check-lg"></i> เข้าใจแล้ว เริ่มเลย!
            </button>
        </div>
    </div>

    <div class="container">
        <div class="exercise-container">
            <!-- Status Bar -->
            <div class="status-bar">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <h5 class="mb-2">
                            <i class="bi bi-person-circle"></i> 
                            <?= $user_data['user_fname'] ?> <?= $user_data['user_lname'] ?>
                        </h5>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="intensity-badge badge-<?= strtolower($user_data['exercise_intensity']) ?>">
                            <?php
                            $intensity_text = [
                                'LIGHT' => '🟢 เบา',
                                'MEDIUM' => '🟡 กลาง',
                                'HIGH' => '🔴 แรง'
                            ];
                            echo $intensity_text[$user_data['exercise_intensity']];
                            ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <h5 class="mb-0">
                            <i class="bi bi-arrow-repeat"></i> 
                            <?= $user_data['rounds_per_minute'] ?> รอบ/นาที
                        </h5>
                    </div>
                </div>
            </div>

            <!-- Exercise Control -->
            <div class="exercise-control">
                <h4 class="text-center mb-4">
                    <i class="bi bi-activity"></i> ควบคุมการออกกำลังกาย
                </h4>
                
                <!-- Timer -->
                <div class="timer-display" id="timerDisplay">00:00</div>
                
                <!-- Rounds Counter -->
                <div class="rounds-counter">
                    <span class="text-muted">รอบที่</span>
                    <span class="text-primary" id="currentRounds">0</span>
                    <span class="text-muted">/</span>
                    <span class="text-secondary" id="targetRounds">
                        <?= $user_data['rounds_per_minute'] ?>
                    </span>
                </div>
                
                <!-- Progress -->
                <div class="progress-circle">
                    <div style="font-size: 1.5rem; font-weight: bold;">
                        <span id="progressPercent">0</span>%
                    </div>
                </div>
                
                <!-- Control Buttons -->
                <div class="text-center mt-4">
                    <button class="btn btn-control btn-start me-3" id="startBtn" onclick="startExercise()">
                        <i class="bi bi-play-fill"></i> เริ่ม
                    </button>
                    <button class="btn btn-control btn-pause me-3" id="pauseBtn" onclick="pauseExercise()" style="display:none;">
                        <i class="bi bi-pause-fill"></i> หยุดชั่วคราว
                    </button>
                    <button class="btn btn-control btn-stop" id="stopBtn" onclick="stopExercise()">
                        <i class="bi bi-stop-fill"></i> หยุด
                    </button>
                </div>
            </div>

            <!-- Sensor Data -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="sensor-display">
                        <div class="text-muted mb-1">
                            <i class="bi bi-heart-pulse"></i> Heart Rate
                        </div>
                        <div class="sensor-value" id="heartRate">--</div>
                        <small class="text-muted">BPM</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sensor-display">
                        <div class="text-muted mb-1">
                            <i class="bi bi-droplet"></i> SpO2
                        </div>
                        <div class="sensor-value" id="spo2">--</div>
                        <small class="text-muted">%</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sensor-display">
                        <div class="text-muted mb-1">
                            <i class="bi bi-thermometer-half"></i> Temperature
                        </div>
                        <div class="sensor-value" id="temperature">--</div>
                        <small class="text-muted">°C</small>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ตัวแปรสำหรับควบคุม
        let isRunning = false;
        let isPaused = false;
        let seconds = 0;
        let currentRounds = 0;
        let targetRounds = <?= $user_data['rounds_per_minute'] ?>;
        let roundsPerMinute = <?= $user_data['rounds_per_minute'] ?>;
        let intervalPerRound = 60 / roundsPerMinute; // วินาทีต่อรอบ
        let timerInterval;
        let roundInterval;
        let sensorInterval;

        // ปิด Popup
        function closePopup() {
            document.getElementById('recommendationPopup').style.display = 'none';
        }

        // เริ่มออกกำลังกาย
        function startExercise() {
            if (isRunning) return;
            
            isRunning = true;
            isPaused = false;
            
            // เปลี่ยนปุ่ม
            document.getElementById('startBtn').style.display = 'none';
            document.getElementById('pauseBtn').style.display = 'inline-block';
            
            // เริ่ม Timer
            timerInterval = setInterval(updateTimer, 1000);
            
            // เริ่มนับรอบ
            roundInterval = setInterval(updateRounds, intervalPerRound * 1000);
            
            // เริ่มอ่านค่า Sensor
            sensorInterval = setInterval(updateSensorData, 2000);
            
            // สั่งถุงมือเริ่มทำงาน
            controlGlove('start');
        }

        // หยุดชั่วคราว
        function pauseExercise() {
            if (!isRunning || isPaused) return;
            
            isPaused = true;
            clearInterval(timerInterval);
            clearInterval(roundInterval);
            
            // เปลี่ยนปุ่ม
            document.getElementById('pauseBtn').innerHTML = '<i class="bi bi-play-fill"></i> ดำเนินการต่อ';
            
            // สั่งถุงมือหยุดชั่วคราว
            controlGlove('pause');
        }

        // หยุดออกกำลังกาย
        function stopExercise() {
            if (!isRunning) return;
            
            if (confirm('คุณต้องการหยุดออกกำลังกายหรือไม่?')) {
                isRunning = false;
                isPaused = false;
                
                clearInterval(timerInterval);
                clearInterval(roundInterval);
                clearInterval(sensorInterval);
                
                // รีเซ็ตค่า
                seconds = 0;
                currentRounds = 0;
                updateDisplay();
                
                // เปลี่ยนปุ่ม
                document.getElementById('startBtn').style.display = 'inline-block';
                document.getElementById('pauseBtn').style.display = 'none';
                
                // สั่งถุงมือหยุด
                controlGlove('stop');
                
                // บันทึก log
                saveExerciseLog();
                
                // กลับหน้าหลัก
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 2000);
            }
        }

        // อัพเดท Timer
        function updateTimer() {
            seconds++;
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            document.getElementById('timerDisplay').textContent = 
                `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        // อัพเดทรอบ
        function updateRounds() {
            if (currentRounds < targetRounds) {
                currentRounds++;
                updateDisplay();
            } else {
                // ครบรอบแล้ว
                stopExercise();
                alert('🎉 ยินดีด้วย! คุณออกกำลังกายครบตามเป้าหมายแล้ว');
            }
        }

        // อัพเดทการแสดงผล
        function updateDisplay() {
            document.getElementById('currentRounds').textContent = currentRounds;
            const percent = Math.round((currentRounds / targetRounds) * 100);
            document.getElementById('progressPercent').textContent = percent;
        }

        // อัพเดทข้อมูล Sensor
        function updateSensorData() {
            fetch('get_latest_sensor.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('heartRate').textContent = data.heart_rate || '--';
                        document.getElementById('spo2').textContent = data.spo2 || '--';
                        document.getElementById('temperature').textContent = 
                            data.temperature ? parseFloat(data.temperature).toFixed(1) : '--';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // ควบคุมถุงมือ
        function controlGlove(action) {
            const intensity = '<?= $user_data['exercise_intensity'] ?>';
            const rounds = <?= $user_data['rounds_per_minute'] ?>;
            
            // กำหนด relay ตามระดับความแรง
            let relay1 = 0, relay2 = 0, relay3 = 0;
            
            if (action === 'start') {
                if (intensity === 'LIGHT') {
                    relay1 = 1; // เปิดแค่ relay 1
                } else if (intensity === 'MEDIUM') {
                    relay1 = 1;
                    relay2 = 1; // เปิด relay 1 และ 2
                } else if (intensity === 'HIGH') {
                    relay1 = 1;
                    relay2 = 1;
                    relay3 = 1; // เปิดทั้ง 3
                }
            }
            
            // ส่งคำสั่งไปที่ Arduino
            fetch(`control.php?relay_1=${relay1}&relay_2=${relay2}&relay_3=${relay3}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Glove control:', data);
                })
                .catch(error => console.error('Error:', error));
        }

        // บันทึก log การออกกำลังกาย
        function saveExerciseLog() {
            const data = new FormData();
            data.append('duration', seconds);
            data.append('rounds_completed', currentRounds);
            data.append('intensity', '<?= $user_data['exercise_intensity'] ?>');
            
            fetch('save_exercise_log.php', {
                method: 'POST',
                body: data
            });
        }

        // โหลดข้อมูล sensor ครั้งแรก
        updateSensorData();
    </script>
</body>
</html>
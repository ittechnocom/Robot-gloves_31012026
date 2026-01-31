<?php
session_start();

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// เชื่อมต่อฐานข้อมูล
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// ดึงข้อมูลผู้ใช้
$sql_user = "SELECT user_id, user_email, user_fname, user_lname, user_phon, user_age, user_sex, user_role FROM tb_user WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);

if ($stmt_user === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user_data = $result_user->fetch_assoc();

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $resting_heart_rate = $_POST['resting_heart_rate'] ?? null;
    $avg_spo2 = $_POST['avg_spo2'] ?? null;
    $hand_strength = $_POST['hand_strength'] ?? null;
    $hand_flexibility = $_POST['hand_flexibility'] ?? null;
    $avg_hand_angle = $_POST['avg_hand_angle'] ?? null;
    
    $hand_pain = isset($_POST['hand_pain']) ? 1 : 0;
    $hand_pain_level = $_POST['hand_pain_level'] ?? null;
    $hand_numbness = isset($_POST['hand_numbness']) ? 1 : 0;
    $finger_stiffness = isset($_POST['finger_stiffness']) ? 1 : 0;
    
    $breathing_difficulty = isset($_POST['breathing_difficulty']) ? 1 : 0;
    $chest_tightness = isset($_POST['chest_tightness']) ? 1 : 0;
    
    $exercise_duration = $_POST['exercise_duration'] ?? null;
    $exercise_intensity = $_POST['exercise_intensity'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    $sql_insert = "INSERT INTO tb_health_survey 
        (user_id, resting_heart_rate, avg_spo2, hand_strength, hand_flexibility, avg_hand_angle,
         hand_pain, hand_pain_level, hand_numbness, finger_stiffness,
         breathing_difficulty, chest_tightness, exercise_duration, exercise_intensity, notes, survey_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt_insert = $conn->prepare($sql_insert);
    
    if ($stmt_insert === false) {
        $error_message = "เกิดข้อผิดพลาด: " . $conn->error;
    } else {
        $stmt_insert->bind_param("iiissdiiiiiiiss", 
            $user_id, $resting_heart_rate, $avg_spo2, $hand_strength, $hand_flexibility, $avg_hand_angle,
            $hand_pain, $hand_pain_level, $hand_numbness, $finger_stiffness,
            $breathing_difficulty, $chest_tightness, $exercise_duration, $exercise_intensity, $notes
        );
        
        if ($stmt_insert->execute()) {
            $success_message = "บันทึกแบบสอบถามสุขภาพเรียบร้อยแล้ว";
            
            // บันทึก activity log
            $log_sql = "INSERT INTO tb_activity_log (user_id, log_type, log_details) VALUES (?, 'health_survey', 'บันทึกแบบสอบถามสุขภาพ')";
            $log_stmt = $conn->prepare($log_sql);
            if ($log_stmt) {
                $log_stmt->bind_param("i", $user_id);
                $log_stmt->execute();
            }
        } else {
            $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt_insert->error;
        }
    }
}

// ดึงข้อมูลเซนเซอร์ล่าสุด - แก้ไขชื่อตารางเป็น sensor_data
$latest_sensor = null;
$sql_latest = "SELECT * FROM sensor_data WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$stmt_latest = $conn->prepare($sql_latest);

if ($stmt_latest === false) {
    // ถ้าไม่มีข้อมูลของ user นี้ ให้ดึงข้อมูลล่าสุดทั้งหมด
    $sql_latest = "SELECT * FROM sensor_data ORDER BY created_at DESC LIMIT 1";
    $stmt_latest = $conn->prepare($sql_latest);
}

if ($stmt_latest) {
    if (strpos($sql_latest, '?') !== false) {
        $stmt_latest->bind_param("i", $user_id);
    }
    $stmt_latest->execute();
    $result_latest = $stmt_latest->get_result();
    $latest_sensor = $result_latest->fetch_assoc();
}

// ดึงประวัติแบบสอบถาม
$sql_history = "SELECT * FROM tb_health_survey WHERE user_id = ? ORDER BY survey_date DESC LIMIT 5";
$stmt_history = $conn->prepare($sql_history);

if ($stmt_history === false) {
    die("Error preparing history statement: " . $conn->error);
}

$stmt_history->bind_param("i", $user_id);
$stmt_history->execute();
$survey_history = $stmt_history->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบสอบถามสุขภาพ - Robotic Handwear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .survey-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            margin: 30px auto;
            max-width: 1200px;
        }
        
        .user-info-card {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
        }
        
        .user-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .user-info-item i {
            font-size: 1.2rem;
            margin-right: 10px;
            width: 25px;
        }
        
        .user-info-label {
            font-weight: 600;
            margin-right: 8px;
            opacity: 0.9;
        }
        
        .user-info-value {
            font-size: 1.1rem;
        }
        
        .sensor-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .sensor-value {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .sensor-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .checkbox-custom {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .checkbox-custom input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
        
        .pain-slider {
            width: 100%;
        }
        
        .slider-value {
            display: inline-block;
            width: 30px;
            text-align: center;
            font-weight: bold;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="survey-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-clipboard2-pulse"></i> แบบสอบถามสุขภาพ</h2>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-house-door"></i> กลับหน้าหลัก
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle"></i> <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- ข้อมูลผู้ใช้ -->
            <div class="user-info-card">
                <h5 class="mb-3"><i class="bi bi-person-circle"></i> ข้อมูลผู้ใช้งาน</h5>
                <?php if ($user_data): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="user-info-item">
                            <i class="bi bi-person-badge"></i>
                            <span class="user-info-label">ชื่อ-นามสกุล:</span>
                            <span class="user-info-value">
                                <?= !empty($user_data['user_fname']) ? htmlspecialchars($user_data['user_fname']) : '-' ?> 
                                <?= !empty($user_data['user_lname']) ? htmlspecialchars($user_data['user_lname']) : '' ?>
                            </span>
                        </div>
                        <div class="user-info-item">
                            <i class="bi bi-calendar-event"></i>
                            <span class="user-info-label">อายุ:</span>
                            <span class="user-info-value">
                                <?= !empty($user_data['user_age']) ? htmlspecialchars($user_data['user_age']) : '-' ?> ปี
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="user-info-item">
                            <i class="bi bi-gender-ambiguous"></i>
                            <span class="user-info-label">เพศ:</span>
                            <span class="user-info-value">
                                <?php 
                                    $gender = $user_data['user_sex'] ?? '';
                                    if ($gender == 'Male' || $gender == 'MALE' || $gender == 'male') {
                                        echo 'ชาย';
                                    } elseif ($gender == 'Female' || $gender == 'FEMALE' || $gender == 'female') {
                                        echo 'หญิง';
                                    } elseif ($gender == 'Other' || $gender == 'OTHER' || $gender == 'other') {
                                        echo 'อื่นๆ';
                                    } else {
                                        echo 'ไม่ระบุ';
                                    }
                                ?>
                            </span>
                        </div>
                        <div class="user-info-item">
                            <i class="bi bi-telephone"></i>
                            <span class="user-info-label">เบอร์โทร:</span>
                            <span class="user-info-value">
                                <?= !empty($user_data['user_phon']) ? htmlspecialchars($user_data['user_phon']) : '-' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> ไม่พบข้อมูลผู้ใช้
                    </div>
                <?php endif; ?>
            </div>

            <!-- ข้อมูลเซนเซอร์ล่าสุด -->
            <?php if ($latest_sensor): ?>
            <div class="sensor-card">
                <h5 class="mb-3"><i class="bi bi-activity"></i> ข้อมูลสุขภาพล่าสุด</h5>
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="sensor-value"><?= $latest_sensor['heart_rate'] ?? '-' ?></div>
                        <div class="sensor-label">BPM</div>
                        <small>อัตราการเต้นหัวใจ</small>
                    </div>
                    <div class="col-md-3">
                        <div class="sensor-value"><?= $latest_sensor['spo2'] ?? '-' ?>%</div>
                        <div class="sensor-label">SpO2</div>
                        <small>ออกซิเจนในเลือด</small>
                    </div>
                    <div class="col-md-3">
                        <div class="sensor-value"><?= number_format($latest_sensor['angle_z'] ?? 0, 1) ?>°</div>
                        <div class="sensor-label">Angle</div>
                        <small>มุมมือ</small>
                    </div>
                    <div class="col-md-3">
                        <div class="sensor-value"><?= $latest_sensor['speed_type'] ?? '-' ?></div>
                        <div class="sensor-label">Speed</div>
                        <small>ความเร็ว</small>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> ยังไม่มีข้อมูลเซนเซอร์ กรุณาเริ่มใช้งานระบบ
            </div>
            <?php endif; ?>

            <!-- ฟอร์มแบบสอบถาม -->
            <form method="POST" action="">
                
                <!-- ส่วนที่ 1: ข้อมูลพื้นฐาน -->
                <div class="form-section">
                    <h5 class="section-title"><i class="bi bi-heart-pulse"></i> ข้อมูลสุขภาพพื้นฐาน</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">อัตราการเต้นหัวใจขณะพัก (BPM)</label>
                            <input type="number" class="form-control" name="resting_heart_rate" 
                                   value="<?= $latest_sensor['heart_rate'] ?? '' ?>" min="40" max="200">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ออกซิเจนในเลือด SpO2 (%)</label>
                            <input type="number" class="form-control" name="avg_spo2" 
                                   value="<?= $latest_sensor['spo2'] ?? '' ?>" min="80" max="100">
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 2: ข้อมูลมือและข้อมือ -->
                <div class="form-section">
                    <h5 class="section-title"><i class="bi bi-hand-index"></i> สภาพมือและข้อมือ</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ความแข็งแรงของมือ</label>
                            <select class="form-select" name="hand_strength">
                                <option value="">-- เลือก --</option>
                                <option value="WEAK">อ่อนแรง</option>
                                <option value="NORMAL">ปกติ</option>
                                <option value="STRONG">แข็งแรง</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ความยืดหยุ่นของมือ</label>
                            <select class="form-select" name="hand_flexibility">
                                <option value="">-- เลือก --</option>
                                <option value="LOW">ต่ำ</option>
                                <option value="MEDIUM">ปานกลาง</option>
                                <option value="HIGH">สูง</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">มุมมือเฉลี่ย (องศา)</label>
                            <input type="number" class="form-control" name="avg_hand_angle" 
                                   value="<?= isset($latest_sensor['angle_z']) ? number_format($latest_sensor['angle_z'], 2) : '' ?>" 
                                   step="0.01" min="0" max="360">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="checkbox-custom">
                            <input type="checkbox" id="hand_pain" name="hand_pain" onchange="togglePainLevel()">
                            <label for="hand_pain">มีอาการปวดมือหรือข้อมือ</label>
                        </div>
                        <div id="pain_level_container" style="display: none; margin-left: 30px;">
                            <label class="form-label">ระดับความปวด (0-10)</label>
                            <div class="d-flex align-items-center">
                                <input type="range" class="pain-slider form-range" id="pain_level" 
                                       name="hand_pain_level" min="0" max="10" value="0" 
                                       oninput="document.getElementById('pain_value').textContent = this.value">
                                <span class="slider-value ms-3" id="pain_value">0</span>
                            </div>
                            <small class="text-muted">0 = ไม่ปวด | 10 = ปวดมากที่สุด</small>
                        </div>
                    </div>

                    <div class="checkbox-custom">
                        <input type="checkbox" id="hand_numbness" name="hand_numbness">
                        <label for="hand_numbness">มีอาการชามือหรือนิ้วมือ</label>
                    </div>

                    <div class="checkbox-custom">
                        <input type="checkbox" id="finger_stiffness" name="finger_stiffness">
                        <label for="finger_stiffness">มีอาการนิ้วมือแข็ง/เคลื่อนไหวลำบาก</label>
                    </div>
                </div>

                <!-- ส่วนที่ 3: การหายใจ -->
                <div class="form-section">
                    <h5 class="section-title"><i class="bi bi-lungs"></i> ระบบหายใจ</h5>
                    <div class="checkbox-custom">
                        <input type="checkbox" id="breathing_difficulty" name="breathing_difficulty">
                        <label for="breathing_difficulty">มีอาการหายใจลำบาก/หอบเหนื่อย</label>
                    </div>

                    <div class="checkbox-custom">
                        <input type="checkbox" id="chest_tightness" name="chest_tightness">
                        <label for="chest_tightness">มีอาการแน่นหน้าอก</label>
                    </div>
                </div>

                <!-- ส่วนที่ 4: การออกกำลังกาย -->
                <div class="form-section">
                    <h5 class="section-title"><i class="bi bi-bicycle"></i> การออกกำลังกาย</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ระยะเวลาออกกำลังกาย (นาที)</label>
                            <input type="number" class="form-control" name="exercise_duration" 
                                   min="0" max="300" placeholder="เช่น 30">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ความหนักของการออกกำลังกาย</label>
                            <select class="form-select" name="exercise_intensity">
                                <option value="">-- เลือก --</option>
                                <option value="LIGHT">เบา</option>
                                <option value="MODERATE">ปานกลาง</option>
                                <option value="VIGOROUS">หนัก</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 5: หมายเหตุเพิ่มเติม -->
                <div class="form-section">
                    <h5 class="section-title"><i class="bi bi-journal-text"></i> หมายเหตุเพิ่มเติม</h5>
                    <textarea class="form-control" name="notes" rows="4" 
                              placeholder="บันทึกอาการ หรือข้อสังเกตเพิ่มเติม..."></textarea>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-save"></i> บันทึกแบบสอบถาม
                    </button>
                </div>
            </form>

            <!-- ประวัติแบบสอบถาม -->
            <?php if ($survey_history && $survey_history->num_rows > 0): ?>
            <div class="mt-5">
                <h5 class="section-title"><i class="bi bi-clock-history"></i> ประวัติแบบสอบถาม</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>วันที่</th>
                                <th>HR (BPM)</th>
                                <th>SpO2 (%)</th>
                                <th>ความแข็งแรง</th>
                                <th>อาการปวด</th>
                                <th>การออกกำลังกาย</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $survey_history->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($row['survey_date'])) ?></td>
                                <td><?= $row['resting_heart_rate'] ?? '-' ?></td>
                                <td><?= $row['avg_spo2'] ?? '-' ?></td>
                                <td><?= $row['hand_strength'] ?? '-' ?></td>
                                <td><?= $row['hand_pain'] ? "ใช่ (". $row['hand_pain_level'] ."/10)" : "ไม่" ?></td>
                                <td><?= $row['exercise_duration'] ? $row['exercise_duration'] ." นาที" : '-' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePainLevel() {
            const checkbox = document.getElementById('hand_pain');
            const container = document.getElementById('pain_level_container');
            container.style.display = checkbox.checked ? 'block' : 'none';
            
            if (!checkbox.checked) {
                document.getElementById('pain_level').value = 0;
                document.getElementById('pain_value').textContent = 0;
            }
        }

        // Auto-fill จากข้อมูลเซนเซอร์ล่าสุด
        window.addEventListener('load', function() {
            console.log('Health Survey Form Loaded');
            <?php if ($user_data): ?>
            console.log('User Data:', <?= json_encode($user_data) ?>);
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
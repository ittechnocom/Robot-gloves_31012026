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
$success_message = '';
$error_message = '';

// ดึงข้อมูลผู้ใช้
$sql_user = "SELECT user_id, user_email, user_fname, user_lname, result_health_survey, 
             exercise_intensity, rounds_per_minute FROM tb_user WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user_data = $result_user->fetch_assoc();

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result_health_survey = $_POST['result_health_survey'] ?? null;
    $exercise_intensity = $_POST['exercise_intensity'] ?? null;
    $rounds_per_minute = intval($_POST['rounds_per_minute'] ?? 0);
    
    // Update ข้อมูล
    $sql_update = "UPDATE tb_user 
                   SET result_health_survey = ?, 
                       exercise_intensity = ?, 
                       rounds_per_minute = ?,
                       updated_at = NOW()
                   WHERE user_id = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssii", $result_health_survey, $exercise_intensity, $rounds_per_minute, $user_id);
    
    if ($stmt_update->execute()) {
        $success_message = "บันทึกการตั้งค่าเรียบร้อยแล้ว";
        
        // รีเฟรชข้อมูล
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        $user_data = $result_user->fetch_assoc();
        
        // บันทึก activity log
        $log_sql = "INSERT INTO tb_activity_log (user_id, log_type, log_details) 
                    VALUES (?, 'exercise_settings', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_details = "ตั้งค่าการออกกำลังกาย: $exercise_intensity - $rounds_per_minute รอบ/นาที";
        $log_stmt->bind_param("is", $user_id, $log_details);
        $log_stmt->execute();
    } else {
        $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าการออกกำลังกาย - Robotic Handwear</title>
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
        
        .settings-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            margin: 30px auto;
            max-width: 900px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .intensity-card {
            border: 3px solid transparent;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .intensity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .intensity-card.selected {
            border-color: var(--primary-color);
            background: #e3f2fd;
        }
        
        .intensity-card.light {
            border-left: 5px solid var(--light-color);
        }
        
        .intensity-card.medium {
            border-left: 5px solid var(--medium-color);
        }
        
        .intensity-card.high {
            border-left: 5px solid var(--high-color);
        }
        
        .intensity-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .intensity-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .intensity-desc {
            color: #666;
            margin-bottom: 15px;
        }
        
        .rounds-display {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .current-settings {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .btn-start-exercise {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 15px 50px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 50px;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-start-exercise:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="settings-container">
            <!-- Header -->
            <div class="header-section">
                <i class="bi bi-gear-fill" style="font-size: 3rem;"></i>
                <h2 class="mt-3 mb-2">ตั้งค่าการออกกำลังกาย</h2>
                <p class="mb-0">เลือกระดับความแรงและจำนวนรอบที่เหมาะกับคุณ</p>
            </div>

            <!-- แสดงข้อความสำเร็จ/ผิดพลาด -->
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- การตั้งค่าปัจจุบัน -->
            <?php if ($user_data['exercise_intensity']): ?>
            <div class="current-settings">
                <h5 class="mb-3"><i class="bi bi-info-circle"></i> การตั้งค่าปัจจุบัน</h5>
                <div class="row text-center">
                    <div class="col-md-6">
                        <div class="mb-2">ระดับความแรง</div>
                        <div class="fs-4 fw-bold">
                            <?php
                            $intensity_text = [
                                'LIGHT' => '🟢 เบา',
                                'MEDIUM' => '🟡 กลาง',
                                'HIGH' => '🔴 แรง'
                            ];
                            echo $intensity_text[$user_data['exercise_intensity']] ?? 'ไม่ระบุ';
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">จำนวนรอบ/นาที</div>
                        <div class="fs-4 fw-bold"><?= $user_data['rounds_per_minute'] ?? 0 ?> รอบ</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ฟอร์มตั้งค่า -->
            <form method="POST" id="settingsForm">
                <!-- เลือกผลการประเมิน -->
                <div class="mb-4">
                    <label class="form-label fs-5 fw-bold">
                        <i class="bi bi-clipboard-check"></i> ผลการประเมินสุขภาพ
                    </label>
                    <select class="form-select form-select-lg" name="result_health_survey" required>
                        <option value="">-- เลือกผลการประเมิน --</option>
                        <option value="LOW" <?= ($user_data['result_health_survey'] ?? '') == 'LOW' ? 'selected' : '' ?>>
                            ต่ำ (มีข้อจำกัดด้านสุขภาพ)
                        </option>
                        <option value="MEDIUM" <?= ($user_data['result_health_survey'] ?? '') == 'MEDIUM' ? 'selected' : '' ?>>
                            ปานกลาง (สุขภาพปกติ)
                        </option>
                        <option value="HIGH" <?= ($user_data['result_health_survey'] ?? '') == 'HIGH' ? 'selected' : '' ?>>
                            สูง (สุขภาพแข็งแรง)
                        </option>
                    </select>
                </div>

                <!-- เลือกระดับความแรง -->
                <div class="mb-4">
                    <label class="form-label fs-5 fw-bold">
                        <i class="bi bi-speedometer2"></i> ระดับความแรงการออกกำลังกาย
                    </label>
                    
                    <!-- เบา -->
                    <div class="intensity-card light" onclick="selectIntensity('LIGHT', 15)">
                        <input type="radio" name="exercise_intensity" value="LIGHT" 
                               id="intensity_light" style="display: none;"
                               <?= ($user_data['exercise_intensity'] ?? '') == 'LIGHT' ? 'checked' : '' ?>>
                        <div class="text-center">
                            <div class="intensity-icon" style="color: var(--light-color);">
                                <i class="bi bi-battery-half"></i>
                            </div>
                            <div class="intensity-title" style="color: var(--light-color);">🟢 เบา (LIGHT)</div>
                            <div class="intensity-desc">
                                เหมาะสำหรับผู้เริ่มต้น หรือผู้ที่มีข้อจำกัดด้านสุขภาพ
                            </div>
                            <div class="rounds-display">10-15 รอบ/นาที</div>
                            <small class="text-muted">ใช้เวลาฝึก 5-10 นาที</small>
                        </div>
                    </div>

                    <!-- กลาง -->
                    <div class="intensity-card medium" onclick="selectIntensity('MEDIUM', 25)">
                        <input type="radio" name="exercise_intensity" value="MEDIUM" 
                               id="intensity_medium" style="display: none;"
                               <?= ($user_data['exercise_intensity'] ?? '') == 'MEDIUM' ? 'checked' : '' ?>>
                        <div class="text-center">
                            <div class="intensity-icon" style="color: var(--medium-color);">
                                <i class="bi bi-battery-full"></i>
                            </div>
                            <div class="intensity-title" style="color: var(--medium-color);">🟡 กลาง (MEDIUM)</div>
                            <div class="intensity-desc">
                                เหมาะสำหรับผู้ที่มีสุขภาพปกติ ฝึกได้สม่ำเสมอ
                            </div>
                            <div class="rounds-display">20-25 รอบ/นาที</div>
                            <small class="text-muted">ใช้เวลาฝึก 10-15 นาที</small>
                        </div>
                    </div>

                    <!-- แรง -->
                    <div class="intensity-card high" onclick="selectIntensity('HIGH', 35)">
                        <input type="radio" name="exercise_intensity" value="HIGH" 
                               id="intensity_high" style="display: none;"
                               <?= ($user_data['exercise_intensity'] ?? '') == 'HIGH' ? 'checked' : '' ?>>
                        <div class="text-center">
                            <div class="intensity-icon" style="color: var(--high-color);">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </div>
                            <div class="intensity-title" style="color: var(--high-color);">🔴 แรง (HIGH)</div>
                            <div class="intensity-desc">
                                เหมาะสำหรับผู้ที่มีสุขภาพแข็งแรง ต้องการเพิ่มความแข็งแรง
                            </div>
                            <div class="rounds-display">30-40 รอบ/นาที</div>
                            <small class="text-muted">ใช้เวลาฝึก 15-20 นาที</small>
                        </div>
                    </div>
                </div>

                <!-- ปรับจำนวนรอบ/นาที -->
                <div class="mb-4">
                    <label class="form-label fs-5 fw-bold">
                        <i class="bi bi-arrow-repeat"></i> ปรับจำนวนรอบต่อนาที
                    </label>
                    <div class="d-flex align-items-center">
                        <input type="range" class="form-range flex-grow-1" 
                               name="rounds_per_minute" id="rounds_slider"
                               min="5" max="50" value="<?= $user_data['rounds_per_minute'] ?? 15 ?>"
                               oninput="updateRoundsDisplay()">
                        <div class="ms-4 text-center" style="min-width: 100px;">
                            <div class="fs-2 fw-bold text-primary" id="rounds_value">
                                <?= $user_data['rounds_per_minute'] ?? 15 ?>
                            </div>
                            <small class="text-muted">รอบ/นาที</small>
                        </div>
                    </div>
                    <div class="text-muted mt-2">
                        <small>
                            <i class="bi bi-info-circle"></i> 
                            แนะนำ: เบา 10-15 | กลาง 20-25 | แรง 30-40 รอบ/นาที
                        </small>
                    </div>
                </div>

                <!-- ปุ่มบันทึก -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5 me-3">
                        <i class="bi bi-save"></i> บันทึกการตั้งค่า
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg px-5">
                        <i class="bi bi-arrow-left"></i> กลับ
                    </a>
                </div>
            </form>

            <!-- ปุ่มเริ่มออกกำลังกาย -->
            <?php if ($user_data['exercise_intensity']): ?>
            <div class="text-center mt-5 pt-4 border-top">
                <h5 class="mb-3">พร้อมที่จะออกกำลังกายแล้วหรือยัง?</h5>
                <a href="start_exercise.php" class="btn btn-start-exercise">
                    <i class="bi bi-play-circle-fill"></i> เริ่มออกกำลังกาย
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // เลือกระดับความแรง
        function selectIntensity(intensity, suggestedRounds) {
            // ลบ class selected ทั้งหมด
            document.querySelectorAll('.intensity-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // เพิ่ม class selected ให้การ์ดที่เลือก
            const selectedCard = document.querySelector(`input[value="${intensity}"]`).closest('.intensity-card');
            selectedCard.classList.add('selected');
            
            // เลือก radio button
            document.getElementById(`intensity_${intensity.toLowerCase()}`).checked = true;
            
            // ตั้งค่าจำนวนรอบที่แนะนำ
            document.getElementById('rounds_slider').value = suggestedRounds;
            updateRoundsDisplay();
        }

        // อัพเดทจำนวนรอบที่แสดง
        function updateRoundsDisplay() {
            const value = document.getElementById('rounds_slider').value;
            document.getElementById('rounds_value').textContent = value;
        }

        // ตั้งค่า selected card เมื่อโหลดหน้า
        window.addEventListener('load', function() {
            const selectedRadio = document.querySelector('input[name="exercise_intensity"]:checked');
            if (selectedRadio) {
                selectedRadio.closest('.intensity-card').classList.add('selected');
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
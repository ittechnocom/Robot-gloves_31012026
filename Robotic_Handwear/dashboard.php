<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบควบคุมถุงมือหุ่นยนต์บำบัด</title>
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
            max-width: 1600px;
            margin: 0 auto;
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

        #dataChart {
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

        /* Responsive */
        @media (max-width: 1200px) {
            .main-grid {
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
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-hand-sparkles"></i> ระบบถุงมือหุ่นยนต์บำบัด</h1>
            <p>Robotic Handwear Therapy System</p>
            <div class="connection-status">
                <div class="status-indicator disconnected" id="connectionIndicator"></div>
                <span id="connectionText">กำลังเชื่อมต่อ...</span>
                <span class="loading-spinner" id="loadingSpinner"></span>
            </div>
        </div>

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
                    กราฟข้อมูลแบบเรียลไทม์ (แสดงล่าสุด 20 วินาที)
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
                <button class="control-btn secondary" onclick="testAllRelays()">
                    <i class="fas fa-vial"></i> ทดสอบรีเลย์
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

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Configuration
        const CONFIG = {
            sensorURL: 'get_latest_sensor.php',
            relayURL: 'get_relay_status.php',
            controlURL: 'control.php',
            updateInterval: 1000, // 1 วินาที
            maxDataPoints: 20 // ลดจาก 30 เหลือ 20 จุด เพื่อไม่ให้กราฟยืด
        };

        // Global Variables
        let isMonitoring = false;
        let monitoringInterval = null;
        let chartData = {
            labels: [],
            acceleration: [],
            gyroscope: [],
            angleZ: []
        };
        let chart = null;

        // Initialize Chart
        function initChart() {
            const ctx = document.getElementById('dataChart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Acceleration (m/s²)',
                            data: chartData.acceleration,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        },
                        {
                            label: 'Gyroscope (rad/s)',
                            data: chartData.gyroscope,
                            borderColor: '#f093fb',
                            backgroundColor: 'rgba(240, 147, 251, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        },
                        {
                            label: 'Angle Z (°)',
                            data: chartData.angleZ,
                            borderColor: '#4facfe',
                            backgroundColor: 'rgba(79, 172, 254, 0.1)',
                            tension: 0.4,
                            fill: true,
                            hidden: true,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: 'rgba(255, 255, 255, 0.2)',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: 30, // กำหนดค่าสูงสุดคงที่
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                padding: 10,
                                color: '#64748b',
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 10, // จำกัดจำนวน label บนแกน X
                                padding: 10,
                                color: '#64748b',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 0 // ปิด animation เพื่อไม่ให้ยืด
                    }
                }
            });
        }

        // Update Chart
        function updateChart(data) {
            const time = new Date().toLocaleTimeString('th-TH', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });

            chartData.labels.push(time);
            chartData.acceleration.push(parseFloat(data.acceleration));
            chartData.gyroscope.push(parseFloat(data.gyroscope));
            chartData.angleZ.push(parseFloat(data.angle_z));

            // Keep only last 30 data points - ตัดข้อมูลเก่าทิ้ง
            if (chartData.labels.length > CONFIG.maxDataPoints) {
                chartData.labels.shift();
                chartData.acceleration.shift();
                chartData.gyroscope.shift();
                chartData.angleZ.shift();
            }

            // Update chart with animation only for new data
            chart.update('none'); // ใช้ 'none' เพื่อไม่ให้มี animation ยืดกราฟ
        }

        // Fetch Sensor Data
        async function fetchSensorData() {
            try {
                const response = await fetch(CONFIG.sensorURL);
                const result = await response.json();

                if (result.status === 'success' && result.data) {
                    updateSensorDisplay(result.data);
                    updateChart(result.data);
                    updateConnectionStatus(true);
                } else {
                    updateConnectionStatus(false, 'ไม่มีข้อมูลใหม่');
                }

                document.getElementById('lastUpdateTime').textContent = 
                    new Date().toLocaleTimeString('th-TH');

            } catch (error) {
                console.error('Error:', error);
                updateConnectionStatus(false, 'เกิดข้อผิดพลาด');
            }
        }

        // Fetch Relay Status
        async function fetchRelayStatus() {
            try {
                const response = await fetch(CONFIG.relayURL);
                const result = await response.json();

                if (result.status === 'success') {
                    updateRelayDisplay(result.relay_status);
                }
            } catch (error) {
                console.error('Error fetching relay status:', error);
            }
        }

        // Update Sensor Display
        function updateSensorDisplay(data) {
            document.getElementById('accelerationValue').textContent = 
                parseFloat(data.acceleration).toFixed(2);
            document.getElementById('gyroscopeValue').textContent = 
                parseFloat(data.gyroscope).toFixed(2);
            document.getElementById('angleZValue').textContent = 
                parseFloat(data.angle_z).toFixed(1);

            document.getElementById('slowCount').textContent = data.slow_count;
            document.getElementById('mediumCount').textContent = data.medium_count;
            document.getElementById('fastCount').textContent = data.fast_count;
            document.getElementById('totalCount').textContent = 
                parseInt(data.slow_count) + parseInt(data.medium_count) + parseInt(data.fast_count);

            // Update last clap type
            const lastClapType = document.getElementById('lastClapType');
            const speedEmoji = {
                'SLOW': '🐢',
                'MEDIUM': '🚶',
                'FAST': '🏃'
            };
            
            const speedColor = {
                'SLOW': '#10b981',
                'MEDIUM': '#f59e0b',
                'FAST': '#ef4444'
            };

            lastClapType.innerHTML = `
                <span style="color: ${speedColor[data.speed_type]}">
                    ${speedEmoji[data.speed_type]} ${data.speed_type}
                </span>
            `;
        }

        // Update Relay Display
        function updateRelayDisplay(relayStatus) {
            const relays = ['relay1', 'relay2', 'relay3'];
            
            relays.forEach((relayId, index) => {
                const relayElement = document.getElementById(relayId);
                const isActive = relayStatus[`relay_${index + 1}`] == 1;
                
                if (isActive) {
                    relayElement.classList.add('active');
                    relayElement.querySelector('.relay-status').textContent = 'ON';
                } else {
                    relayElement.classList.remove('active');
                    relayElement.querySelector('.relay-status').textContent = 'OFF';
                }
            });
        }

        // Update Connection Status
        function updateConnectionStatus(connected, message = '') {
            const indicator = document.getElementById('connectionIndicator');
            const text = document.getElementById('connectionText');
            const spinner = document.getElementById('loadingSpinner');

            if (connected) {
                indicator.classList.add('connected');
                indicator.classList.remove('disconnected');
                text.textContent = 'เชื่อมต่อแล้ว';
                spinner.style.display = 'none';
            } else {
                indicator.classList.remove('connected');
                indicator.classList.add('disconnected');
                text.textContent = message || 'ไม่ได้เชื่อมต่อ';
                spinner.style.display = 'none';
            }
        }

        // Start Monitoring
        function startMonitoring() {
            isMonitoring = true;
            document.getElementById('startBtn').style.display = 'none';
            document.getElementById('stopBtn').style.display = 'inline-flex';
            
            fetchSensorData();
            fetchRelayStatus();
            
            monitoringInterval = setInterval(() => {
                fetchSensorData();
                fetchRelayStatus();
            }, CONFIG.updateInterval);

            showToast('เริ่มการตรวจสอบแล้ว', 'success');
        }

        // Stop Monitoring
        function stopMonitoring() {
            isMonitoring = false;
            document.getElementById('startBtn').style.display = 'inline-flex';
            document.getElementById('stopBtn').style.display = 'none';
            
            if (monitoringInterval) {
                clearInterval(monitoringInterval);
                monitoringInterval = null;
            }

            showToast('หยุดการตรวจสอบแล้ว', 'info');
        }

        // Test All Relays
        async function testAllRelays() {
            showToast('กำลังทดสอบรีเลย์...', 'info');
            
            for (let i = 1; i <= 3; i++) {
                await testRelay(i);
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
            
            showToast('ทดสอบรีเลย์เสร็จสิ้น', 'success');
        }

        // Test Single Relay
        async function testRelay(relayNum) {
            try {
                // Turn on
                const params = new URLSearchParams();
                params.set('relay_1', relayNum === 1 ? 1 : 0);
                params.set('relay_2', relayNum === 2 ? 1 : 0);
                params.set('relay_3', relayNum === 3 ? 1 : 0);
                
                await fetch(`${CONFIG.controlURL}?${params.toString()}`);
                await fetchRelayStatus();
                
                // Wait 1.5 seconds
                await new Promise(resolve => setTimeout(resolve, 1500));
                
                // Turn off
                params.set('relay_1', 0);
                params.set('relay_2', 0);
                params.set('relay_3', 0);
                
                await fetch(`${CONFIG.controlURL}?${params.toString()}`);
                await fetchRelayStatus();
                
            } catch (error) {
                console.error(`Error testing relay ${relayNum}:`, error);
            }
        }

        // Reset Counters
        async function resetCounters() {
            if (confirm('คุณต้องการรีเซ็ตค่าทั้งหมดหรือไม่?')) {
                // Clear chart data
                chartData.labels = [];
                chartData.acceleration = [];
                chartData.gyroscope = [];
                chartData.angleZ = [];
                chart.update();
                
                // Reset display
                document.getElementById('slowCount').textContent = '0';
                document.getElementById('mediumCount').textContent = '0';
                document.getElementById('fastCount').textContent = '0';
                document.getElementById('totalCount').textContent = '0';
                document.getElementById('lastClapType').innerHTML = '<span style="color: #64748b;">รอการตรวจจับ...</span>';
                
                showToast('รีเซ็ตค่าเรียบร้อยแล้ว', 'success');
            }
        }

        // Show Toast
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const icon = toast.querySelector('i');
            
            // Set icon based on type
            icon.className = type === 'success' ? 'fas fa-check-circle' : 
                           type === 'error' ? 'fas fa-exclamation-circle' : 
                           'fas fa-info-circle';
            
            // Set class
            toast.className = `toast ${type} show`;
            toastMessage.textContent = message;
            
            // Hide after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Initialize on page load
        window.addEventListener('load', () => {
            initChart();
            updateConnectionStatus(false, 'กำลังเชื่อมต่อ...');
            
            // Initial fetch
            fetchSensorData();
            fetchRelayStatus();
            
            // Show welcome message
            showToast('ระบบพร้อมใช้งาน', 'success');
        });

        // Auto-refresh relay status every 2 seconds
        setInterval(() => {
            if (!isMonitoring) {
                fetchRelayStatus();
            }
        }, 2000);
    </script>
</body>
</html>
// ===================================================================
// Integrated Dashboard JavaScript - ENHANCED VERSION
// รวมระบบเซนเซอร์เดิม + ระบบสุขภาพใหม่ (ปรับปรุงแล้ว)
// ===================================================================

// Configuration
const CONFIG = {
    // Original sensor endpoints
    sensorURL: 'get_latest_sensor.php',
    relayURL: 'get_relay_status.php',
    controlURL: 'control.php',

    // New health endpoints
    healthURL: 'get_health_data.php',

    updateInterval: 1000, // 1 second for sensors
    healthUpdateInterval: 5000, // 5 seconds for health
    maxDataPoints: 20, // Sensor chart
    maxHealthDataPoints: 60, // Health chart (1 hour with 1min intervals)

    // Health thresholds
    heartRate: {
        veryLow: 50,
        low: 60,
        normal: 100,
        high: 120,
        veryHigh: 140
    },
    spo2: {
        critical: 88,
        danger: 90,
        warning: 95,
        normal: 98
    }
};

// Global Variables
let isMonitoring = false;
let monitoringInterval = null;
let healthMonitoringInterval = null;
let currentTab = 'sensor';

// Chart Data - Sensor
let chartData = {
    labels: [],
    acceleration: [],
    gyroscope: [],
    angleZ: []
};

// Chart Data - Health
let healthChartData = {
    labels: [],
    heartRate: [],
    spo2: []
};

// Health Statistics
let healthStats = {
    heartRateHistory: [],
    spo2History: [],
    totalReadings: 0
};

let chart = null;
let healthChart = null;

// ===================================================================
// TAB SWITCHING
// ===================================================================

function switchTab(tab) {
    // Remove active class from all tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });

    // Add active class to selected tab
    if (tab === 'sensor') {
        document.getElementById('tabSensor').classList.add('active');
        document.getElementById('contentSensor').classList.add('active');
    } else if (tab === 'health') {
        document.getElementById('tabHealth').classList.add('active');
        document.getElementById('contentHealth').classList.add('active');
        if (typeof loadHealthData === 'function') {
            loadHealthData();
        }
    } else if (tab === 'exercise') {
        document.getElementById('tabExercise').classList.add('active');
        document.getElementById('contentExercise').classList.add('active');
    }
}

// ===================================================================
// SENSOR CHART (Original)
// ===================================================================

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
                    suggestedMax: 30,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        padding: 10,
                        color: '#64748b',
                        font: { size: 11 }
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
                        maxTicksLimit: 10,
                        padding: 10,
                        color: '#64748b',
                        font: { size: 11 }
                    }
                }
            },
            animation: { duration: 0 }
        }
    });
}

function updateChart(data) {
    const time = new Date().toLocaleTimeString('th-TH', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });

    chartData.labels.push(time);
    chartData.acceleration.push(parseFloat(data.acceleration) || 0);
    chartData.gyroscope.push(parseFloat(data.gyroscope) || 0);
    chartData.angleZ.push(parseFloat(data.angle_z) || 0);

    if (chartData.labels.length > CONFIG.maxDataPoints) {
        chartData.labels.shift();
        chartData.acceleration.shift();
        chartData.gyroscope.shift();
        chartData.angleZ.shift();
    }

    chart.update('none');
}

// ===================================================================
// HEALTH CHART (New)
// ===================================================================

function initHealthChart() {
    const ctx = document.getElementById('healthChart').getContext('2d');
    healthChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: healthChartData.labels,
            datasets: [
                {
                    label: 'Heart Rate (BPM)',
                    data: healthChartData.heartRate,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    yAxisID: 'y'
                },
                {
                    label: 'SpO2 (%)',
                    data: healthChartData.spo2,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    yAxisID: 'y1'
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
                    padding: 12
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: false,
                    suggestedMin: 50,
                    suggestedMax: 150,
                    title: {
                        display: true,
                        text: 'Heart Rate (BPM)',
                        color: '#ef4444',
                        font: { weight: 'bold' }
                    },
                    ticks: {
                        color: '#ef4444',
                        font: { size: 11 }
                    },
                    grid: {
                        color: 'rgba(239, 68, 68, 0.1)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: false,
                    suggestedMin: 85,
                    suggestedMax: 100,
                    title: {
                        display: true,
                        text: 'SpO2 (%)',
                        color: '#3b82f6',
                        font: { weight: 'bold' }
                    },
                    ticks: {
                        color: '#3b82f6',
                        font: { size: 11 }
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 12,
                        font: { size: 11 }
                    }
                }
            },
            animation: { duration: 0 }
        }
    });
}

function updateHealthChart(data) {
    const time = new Date().toLocaleTimeString('th-TH', {
        hour: '2-digit',
        minute: '2-digit'
    });

    const hr = parseInt(data.heart_rate) || 0;
    const spo2 = parseInt(data.spo2) || 0;

    healthChartData.labels.push(time);
    healthChartData.heartRate.push(hr);
    healthChartData.spo2.push(spo2);

    // Update statistics
    if (hr > 0 && spo2 > 0) {
        healthStats.heartRateHistory.push(hr);
        healthStats.spo2History.push(spo2);
        healthStats.totalReadings++;

        // Keep only last 100 readings for average calculation
        if (healthStats.heartRateHistory.length > 100) {
            healthStats.heartRateHistory.shift();
            healthStats.spo2History.shift();
        }
    }

    if (healthChartData.labels.length > CONFIG.maxHealthDataPoints) {
        healthChartData.labels.shift();
        healthChartData.heartRate.shift();
        healthChartData.spo2.shift();
    }

    healthChart.update('none');
}

// ===================================================================
// FETCH DATA - SENSOR (Original)
// ===================================================================

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
        console.error('Error fetching sensor data:', error);
        updateConnectionStatus(false, 'เกิดข้อผิดพลาด');
    }
}

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

function updateSensorDisplay(data) {
    document.getElementById('accelerationValue').textContent =
        parseFloat(data.acceleration || 0).toFixed(2);
    document.getElementById('gyroscopeValue').textContent =
        parseFloat(data.gyroscope || 0).toFixed(2);
    document.getElementById('angleZValue').textContent =
        parseFloat(data.angle_z || 0).toFixed(1);

    document.getElementById('slowCount').textContent = data.slow_count || 0;
    document.getElementById('mediumCount').textContent = data.medium_count || 0;
    document.getElementById('fastCount').textContent = data.fast_count || 0;

    const total = parseInt(data.slow_count || 0) +
        parseInt(data.medium_count || 0) +
        parseInt(data.fast_count || 0);
    document.getElementById('totalCount').textContent = total;

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

    if (data.speed_type && speedEmoji[data.speed_type]) {
        lastClapType.innerHTML = `
            <span style="color: ${speedColor[data.speed_type]}">
                ${speedEmoji[data.speed_type]} ${data.speed_type}
            </span>
        `;
    } else {
        lastClapType.innerHTML = '<span style="color: #64748b;">รอการตรวจจับ...</span>';
    }
}

function updateRelayDisplay(relayStatus) {
    const relays = ['relay1', 'relay2', 'relay3'];

    relays.forEach((relayId, index) => {
        const relayElement = document.getElementById(relayId);
        if (!relayElement) return;

        const isActive = relayStatus[`relay_${index + 1}`] == 1;

        if (isActive) {
            relayElement.classList.add('active');
            const statusEl = relayElement.querySelector('.relay-status');
            if (statusEl) statusEl.textContent = 'ON';
        } else {
            relayElement.classList.remove('active');
            const statusEl = relayElement.querySelector('.relay-status');
            if (statusEl) statusEl.textContent = 'OFF';
        }
    });
}

// ===================================================================
// FETCH DATA - HEALTH (New)
// ===================================================================

async function fetchHealthData() {
    try {
        // Check if currentUserId is defined
        if (typeof currentUserId === 'undefined') {
            console.warn('currentUserId not defined, skipping health data fetch');
            return;
        }

        const response = await fetch(`${CONFIG.healthURL}?action=latest&user_id=${currentUserId}`);
        const result = await response.json();

        if (result.status === 'success' && result.data) {
            updateHealthDisplay(result.data, result.health_status);
            updateHealthChart(result.data);
            calculateAndDisplayStats();
        }

        const updateEl = document.getElementById('healthLastUpdate');
        if (updateEl) {
            updateEl.textContent = new Date().toLocaleTimeString('th-TH');
        }

    } catch (error) {
        console.error('Error fetching health data:', error);
    }
}

async function fetchHealthSummary() {
    try {
        if (typeof currentUserId === 'undefined') return;

        const response = await fetch(`${CONFIG.healthURL}?action=summary&user_id=${currentUserId}`);
        const result = await response.json();

        if (result.status === 'success' && result.data) {
            updateHealthSummary(result.data);
        }
    } catch (error) {
        console.error('Error fetching health summary:', error);
    }
}

function updateHealthDisplay(data, healthStatus) {
    // Update values
    const hrValue = data.heart_rate || 0;
    const spo2Value = data.spo2 || 0;
    const angleValue = parseFloat(data.angle_z || 0).toFixed(1);

    const hrEl = document.getElementById('heartRateValue');
    const spo2El = document.getElementById('spo2Value');
    const angleEl = document.getElementById('healthAngleValue');

    if (hrEl) hrEl.textContent = hrValue;
    if (spo2El) spo2El.textContent = spo2Value;
    if (angleEl) angleEl.textContent = angleValue;

    // Update heart rate status
    updateHeartRateStatus(parseInt(hrValue), healthStatus);

    // Update SpO2 status
    updateSpO2Status(parseInt(spo2Value), healthStatus);

    // Update recommendations
    updateRecommendations(parseInt(hrValue), parseInt(spo2Value), healthStatus);
}

function updateHeartRateStatus(hr, healthStatus) {
    const hrStatus = document.getElementById('heartRateStatus');
    if (!hrStatus) return;

    if (healthStatus && healthStatus.heart_rate) {
        hrStatus.textContent = healthStatus.heart_rate.message;
        hrStatus.className = `health-status ${healthStatus.heart_rate.level}`;
    } else {
        // Fallback status calculation
        if (hr === 0) {
            hrStatus.textContent = 'ไม่มีข้อมูล';
            hrStatus.className = 'health-status warning';
        } else if (hr < CONFIG.heartRate.veryLow) {
            hrStatus.textContent = 'ต่ำมาก';
            hrStatus.className = 'health-status danger';
        } else if (hr < CONFIG.heartRate.low) {
            hrStatus.textContent = 'ต่ำกว่าปกติ';
            hrStatus.className = 'health-status warning';
        } else if (hr > CONFIG.heartRate.veryHigh) {
            hrStatus.textContent = 'สูงมาก';
            hrStatus.className = 'health-status danger';
        } else if (hr > CONFIG.heartRate.high) {
            hrStatus.textContent = 'สูงกว่าปกติ';
            hrStatus.className = 'health-status warning';
        } else {
            hrStatus.textContent = 'ปกติ';
            hrStatus.className = 'health-status good';
        }
    }
}

function updateSpO2Status(spo2, healthStatus) {
    const spo2Status = document.getElementById('spo2Status');
    if (!spo2Status) return;

    if (healthStatus && healthStatus.spo2) {
        spo2Status.textContent = healthStatus.spo2.message;
        spo2Status.className = `health-status ${healthStatus.spo2.level}`;
    } else {
        // Fallback status calculation
        if (spo2 === 0) {
            spo2Status.textContent = 'ไม่มีข้อมูล';
            spo2Status.className = 'health-status warning';
        } else if (spo2 < CONFIG.spo2.critical) {
            spo2Status.textContent = 'วิกฤต';
            spo2Status.className = 'health-status danger';
        } else if (spo2 < CONFIG.spo2.danger) {
            spo2Status.textContent = 'ต่ำมาก';
            spo2Status.className = 'health-status danger';
        } else if (spo2 < CONFIG.spo2.warning) {
            spo2Status.textContent = 'ต่ำกว่าปกติ';
            spo2Status.className = 'health-status warning';
        } else {
            spo2Status.textContent = 'ปกติ';
            spo2Status.className = 'health-status good';
        }
    }
}

function updateRecommendations(hr, spo2, healthStatus) {
    const recsElement = document.getElementById('healthRecommendations');
    if (!recsElement) return;

    let recommendations = [];

    if (healthStatus && healthStatus.recommendations && healthStatus.recommendations.length > 0) {
        recommendations = healthStatus.recommendations;
    } else {
        // Generate recommendations based on values
        if (hr > CONFIG.heartRate.veryHigh || spo2 < CONFIG.spo2.critical) {
            recommendations.push('🚨 หยุดการออกกำลังกายทันที และพักผ่อน');
        } else if (hr > CONFIG.heartRate.high) {
            recommendations.push('⚠️ ชะลอการออกกำลังกาย หัวใจเต้นเร็ว');
        } else if (spo2 < CONFIG.spo2.warning) {
            recommendations.push('⚠️ หายใจลึกๆ ออกซิเจนต่ำกว่าปกติ');
        }

        if (hr >= CONFIG.heartRate.low && hr <= CONFIG.heartRate.normal && spo2 >= CONFIG.spo2.normal) {
            recommendations.push('✅ สุขภาพดี ดำเนินการออกกำลังกายต่อได้');
        }
    }

    if (recommendations.length > 0) {
        recsElement.innerHTML = recommendations.map(rec => `• ${rec}`).join('<br>');
    } else {
        recsElement.textContent = 'ไม่มีคำแนะนำเพิ่มเติม';
    }
}

function calculateAndDisplayStats() {
    if (healthStats.heartRateHistory.length === 0) return;

    // Calculate averages
    const avgHR = Math.round(
        healthStats.heartRateHistory.reduce((a, b) => a + b, 0) /
        healthStats.heartRateHistory.length
    );

    const avgSpO2 = Math.round(
        healthStats.spo2History.reduce((a, b) => a + b, 0) /
        healthStats.spo2History.length
    );

    // Update display
    const avgHREl = document.getElementById('healthAvgHR');
    const avgSpO2El = document.getElementById('healthAvgSpO2');
    const totalCompEl = document.getElementById('healthTotalCompressions');
    const perfEl = document.getElementById('healthPerformance');

    if (avgHREl) avgHREl.textContent = avgHR;
    if (avgSpO2El) avgSpO2El.textContent = avgSpO2;
    if (totalCompEl) totalCompEl.textContent = healthStats.totalReadings;

    // Performance evaluation
    if (perfEl) {
        if (avgHR >= CONFIG.heartRate.low && avgHR <= CONFIG.heartRate.normal &&
            avgSpO2 >= CONFIG.spo2.normal) {
            perfEl.textContent = '✅ สุขภาพดีเยี่ยม';
        } else if (avgSpO2 < CONFIG.spo2.warning || avgHR > CONFIG.heartRate.high) {
            perfEl.textContent = '⚠️ ต้องการความระมัดระวัง';
        } else {
            perfEl.textContent = '⚡ สุขภาพดี';
        }
    }
}

function updateHealthSummary(summary) {
    const totalCompEl = document.getElementById('healthTotalCompressions');
    const avgHREl = document.getElementById('healthAvgHR');
    const avgSpO2El = document.getElementById('healthAvgSpO2');
    const perfEl = document.getElementById('healthPerformance');

    if (totalCompEl) {
        totalCompEl.textContent = summary.total_compressions || 0;
    }
    if (avgHREl) {
        avgHREl.textContent = summary.avg_heart_rate ?
            parseFloat(summary.avg_heart_rate).toFixed(0) : '0';
    }
    if (avgSpO2El) {
        avgSpO2El.textContent = summary.avg_spo2 ?
            parseFloat(summary.avg_spo2).toFixed(0) : '0';
    }

    if (perfEl && summary.performance) {
        perfEl.textContent = summary.performance.message || 'กำลังประเมิน...';
    }
}

// ===================================================================
// CONNECTION STATUS
// ===================================================================

function updateConnectionStatus(connected, message = '') {
    const indicator = document.getElementById('connectionIndicator');
    const text = document.getElementById('connectionText');
    const spinner = document.getElementById('loadingSpinner');

    if (!indicator || !text || !spinner) return;

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

// ===================================================================
// MONITORING CONTROLS
// ===================================================================

function startMonitoring() {
    isMonitoring = true;

    const startBtn = document.getElementById('startBtn');
    const stopBtn = document.getElementById('stopBtn');
    if (startBtn) startBtn.style.display = 'none';
    if (stopBtn) stopBtn.style.display = 'inline-flex';

    fetchSensorData();
    fetchRelayStatus();

    monitoringInterval = setInterval(() => {
        fetchSensorData();
        fetchRelayStatus();
    }, CONFIG.updateInterval);

    showToast('เริ่มการตรวจสอบแล้ว', 'success');
}

function stopMonitoring() {
    isMonitoring = false;

    const startBtn = document.getElementById('startBtn');
    const stopBtn = document.getElementById('stopBtn');
    if (startBtn) startBtn.style.display = 'inline-flex';
    if (stopBtn) stopBtn.style.display = 'none';

    if (monitoringInterval) {
        clearInterval(monitoringInterval);
        monitoringInterval = null;
    }

    showToast('หยุดการตรวจสอบแล้ว', 'info');
}

function startHealthMonitoring() {
    fetchHealthData();
    fetchHealthSummary();

    healthMonitoringInterval = setInterval(() => {
        fetchHealthData();
        fetchHealthSummary();
    }, CONFIG.healthUpdateInterval);
}

function stopHealthMonitoring() {
    if (healthMonitoringInterval) {
        clearInterval(healthMonitoringInterval);
        healthMonitoringInterval = null;
    }
}

// ===================================================================
// RELAY TESTING
// ===================================================================

async function testAllRelays() {
    showToast('กำลังทดสอบรีเลย์...', 'info');

    for (let i = 1; i <= 3; i++) {
        await testRelay(i);
        await new Promise(resolve => setTimeout(resolve, 2000));
    }

    showToast('ทดสอบรีเลย์เสร็จสิ้น', 'success');
}

async function testRelay(relayNum) {
    try {
        const params = new URLSearchParams();
        params.set('relay_1', relayNum === 1 ? 1 : 0);
        params.set('relay_2', relayNum === 2 ? 1 : 0);
        params.set('relay_3', relayNum === 3 ? 1 : 0);

        await fetch(`${CONFIG.controlURL}?${params.toString()}`);
        await fetchRelayStatus();

        await new Promise(resolve => setTimeout(resolve, 1500));

        params.set('relay_1', 0);
        params.set('relay_2', 0);
        params.set('relay_3', 0);

        await fetch(`${CONFIG.controlURL}?${params.toString()}`);
        await fetchRelayStatus();

    } catch (error) {
        console.error(`Error testing relay ${relayNum}:`, error);
    }
}

// ===================================================================
// RESET & REFRESH
// ===================================================================

function resetCounters() {
    if (confirm('คุณต้องการรีเซ็ตค่าทั้งหมดหรือไม่?')) {
        chartData.labels = [];
        chartData.acceleration = [];
        chartData.gyroscope = [];
        chartData.angleZ = [];
        if (chart) chart.update();

        const elements = [
            { id: 'slowCount', value: '0' },
            { id: 'mediumCount', value: '0' },
            { id: 'fastCount', value: '0' },
            { id: 'totalCount', value: '0' }
        ];

        elements.forEach(el => {
            const element = document.getElementById(el.id);
            if (element) element.textContent = el.value;
        });

        const lastClapType = document.getElementById('lastClapType');
        if (lastClapType) {
            lastClapType.innerHTML = '<span style="color: #64748b;">รอการตรวจจับ...</span>';
        }

        showToast('รีเซ็ตค่าเรียบร้อยแล้ว', 'success');
    }
}

function refreshHealthData() {
    showToast('กำลังรีเฟรชข้อมูลสุขภาพ...', 'info');
    fetchHealthData();
    fetchHealthSummary();
}

// ===================================================================
// HEALTH ACTIONS
// ===================================================================

function openHealthSurvey() {
    // Check if health survey page exists
    const surveyUrl = 'health_survey.php';
    window.open(surveyUrl, '_blank');
    showToast('เปิดแบบสอบถามสุขภาพ', 'info');
}

function exportHealthReport() {
    showToast('กำลังส่งออกรายงาน...', 'info');

    // Create health report data
    const reportData = {
        user: typeof currentUserName !== 'undefined' ? currentUserName : 'ผู้ใช้',
        userId: typeof currentUserId !== 'undefined' ? currentUserId : 0,
        timestamp: new Date().toISOString(),
        date: new Date().toLocaleDateString('th-TH'),
        time: new Date().toLocaleTimeString('th-TH'),
        stats: {
            totalReadings: healthStats.totalReadings,
            avgHeartRate: healthStats.heartRateHistory.length > 0 ?
                Math.round(healthStats.heartRateHistory.reduce((a, b) => a + b, 0) / healthStats.heartRateHistory.length) : 0,
            avgSpO2: healthStats.spo2History.length > 0 ?
                Math.round(healthStats.spo2History.reduce((a, b) => a + b, 0) / healthStats.spo2History.length) : 0,
            minHeartRate: healthStats.heartRateHistory.length > 0 ? Math.min(...healthStats.heartRateHistory) : 0,
            maxHeartRate: healthStats.heartRateHistory.length > 0 ? Math.max(...healthStats.heartRateHistory) : 0,
            minSpO2: healthStats.spo2History.length > 0 ? Math.min(...healthStats.spo2History) : 0,
            maxSpO2: healthStats.spo2History.length > 0 ? Math.max(...healthStats.spo2History) : 0
        },
        chartData: {
            labels: healthChartData.labels,
            heartRate: healthChartData.heartRate,
            spo2: healthChartData.spo2
        }
    };

    // Create and download JSON file
    const dataStr = JSON.stringify(reportData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `health-report-${Date.now()}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    showToast('ส่งออกรายงานเรียบร้อย', 'success');
}

// ===================================================================
// TOAST NOTIFICATION
// ===================================================================

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    const icon = toast ? toast.querySelector('i') : null;

    if (!toast || !toastMessage || !icon) {
        console.log('Toast:', type, message);
        return;
    }

    icon.className = type === 'success' ? 'fas fa-check-circle' :
        type === 'error' ? 'fas fa-exclamation-circle' :
            'fas fa-info-circle';

    toast.className = `toast ${type} show`;
    toastMessage.textContent = message;

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// ===================================================================
// INITIALIZATION
// ===================================================================

window.addEventListener('load', () => {
    console.log('Integrated Dashboard Initializing...');

    // Initialize charts
    initChart();
    initHealthChart();

    updateConnectionStatus(false, 'กำลังเชื่อมต่อ...');

    // Initial data fetch
    fetchSensorData();
    fetchRelayStatus();

    // Auto-refresh relay status
    setInterval(() => {
        if (!isMonitoring) {
            fetchRelayStatus();
        }
    }, 2000);

    showToast('ระบบพร้อมใช้งาน', 'success');
    console.log('Integrated Dashboard Ready!');
});
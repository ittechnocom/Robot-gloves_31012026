/*
 * Robotic Handwear Health Monitoring System v3.3 (WITH RELAY CONTROL)
 * เซนเซอร์: MPU6050 + MAX30102 + 3 Relay Modules
 * คุณสมบัติ: วัดมุมมือ, ความเร็วบีบ, อัตราการเต้นหัวใจ, SpO2 + Relay Feedback
 * 
 * 🔌 การต่อสายอัตโนมัติ:
 * MPU6050:
 *   - VCC  → 3.3V
 *   - GND  → GND
 *   - SCL  → GPIO 22
 *   - SDA  → GPIO 21
 * 
 * MAX30102:
 *   - VIN  → 3.3V  
 *   - GND  → GND
 *   - SCL  → GPIO 17
 *   - SDA  → GPIO 16
 * 
 * RELAY MODULES:
 *   - RELAY 1 (SLOW)   → GPIO 25
 *   - RELAY 2 (MEDIUM) → GPIO 26
 *   - RELAY 3 (FAST)   → GPIO 27
 *   - VCC → 5V, GND → GND
 * 
 * ✨ v3.3 Features:
 * - ✅ Relay feedback สำหรับแต่ละความเร็ว
 * - ✅ SLOW → Relay 1 ติด
 * - ✅ MEDIUM → Relay 2 ติด
 * - ✅ FAST → Relay 3 ติด
 */

#include <Wire.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include "MAX30105.h"
#include "heartRate.h"

// ==================== RELAY Configuration ====================
#define RELAY_SLOW 25      // Relay 1 สำหรับ SLOW
#define RELAY_MEDIUM 26    // Relay 2 สำหรับ MEDIUM
#define RELAY_FAST 27      // Relay 3 สำหรับ FAST

#define RELAY_ON LOW       // ปรับตามโมดูล Relay (บางตัวใช้ LOW = ON)
#define RELAY_OFF HIGH     // บางตัวใช้ HIGH = OFF

#define RELAY_PULSE_DURATION 500  // เปิด Relay นาน 500ms
// =============================================================

// ==================== I2C Bus Configuration ====================
#define I2C1_SDA 21
#define I2C1_SCL 22
TwoWire I2C_BUS1 = TwoWire(0);

#define I2C2_SDA 16
#define I2C2_SCL 17
TwoWire I2C_BUS2 = TwoWire(1);

#define I2C_SPEED_FAST 400000
// ===============================================================

// ตั้งค่า WiFi
const char* ssid = "Stupid";        
const char* password = "Delomy2547";

// URL ของ PHP Script
const char* serverURL = "http://154.215.14.103/Robotic_Handwear/sensor_data.php";

// สร้างออบเจกต์เซนเซอร์
MAX30105 particleSensor;

// ที่อยู่ I2C
const int MPU_ADDR = 0x68;
const int MAX_ADDR = 0x57;

// ตัวแปรสำหรับ MAX30102
const byte RATE_SIZE = 4;
byte rates[RATE_SIZE];
byte rateSpot = 0;
long lastBeat = 0;
float beatsPerMinute = 0;
int beatAvg = 0;
long irValue = 0;
int spo2 = 0;
bool max30102Available = false;
uint32_t redValue = 0;

// ตัวแปรสำหรับ MPU6050
float prevAccMag = 0;
float prevGyroMag = 0;
unsigned long prevTime = 0;
unsigned long lastClapTime = 0;
bool mpu6050Available = false;

// ตัวแปรสำหรับคำนวณมุม Z
float angleZ = 0;
unsigned long lastUpdateTime = 0;

// Threshold สำหรับจำแนกความเร็วการบีบมือ
const float CLAP_SLOW_THRESHOLD = 8.0;
const float CLAP_MEDIUM_THRESHOLD = 15.0;
const float CLAP_FAST_THRESHOLD = 22.0;

const float GYRO_SLOW_THRESHOLD = 1.5;
const float GYRO_MEDIUM_THRESHOLD = 3.0;
const float GYRO_FAST_THRESHOLD = 5.0;

const unsigned long CLAP_COOLDOWN = 250;

// นับจำนวนแต่ละประเภท
int slowClapCount = 0;
int mediumClapCount = 0;
int fastClapCount = 0;

// ตัวแปรสำหรับรายงานสุขภาพ
unsigned long lastHealthReport = 0;
const unsigned long HEALTH_REPORT_INTERVAL = 5000;

// ตัวแปรสำหรับแสดงผล Debug
unsigned long lastIRDisplay = 0;
bool fingerDetected = false;

// ตัวแปรสำหรับควบคุม Relay
unsigned long relaySlowTimer = 0;
unsigned long relayMediumTimer = 0;
unsigned long relayFastTimer = 0;

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  printHeader();
  
  // ตั้งค่า Relay Pins
  initRelays();
  
  // เชื่อมต่อ WiFi
  connectWiFi();
  
  // เริ่มต้น I2C Bus ทั้ง 2
  Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
  Serial.println("║  Initializing I2C Buses                                  ║");
  Serial.println("╚═══════════════════════════════════════════════════════════╝");
  
  Serial.println("\n[I2C Bus 1] MPU6050 Connection:");
  Serial.println("  📍 SDA → GPIO 21");
  Serial.println("  📍 SCL → GPIO 22");
  I2C_BUS1.begin(I2C1_SDA, I2C1_SCL, 400000);
  delay(100);
  
  Serial.println("\n[I2C Bus 2] MAX30102 Connection:");
  Serial.println("  📍 SDA → GPIO 16");
  Serial.println("  📍 SCL → GPIO 17");
  I2C_BUS2.begin(I2C2_SDA, I2C2_SCL, 400000);
  delay(100);

  // เริ่มต้น MPU6050 บน I2C Bus 1
  Serial.println("\n[1/2] Initializing MPU6050 on Bus 1...");
  if (initMPU6050()) {
    Serial.println("✅ MPU6050 Ready on GPIO 21/22!");
    mpu6050Available = true;
  } else {
    Serial.println("❌ MPU6050 Not Found!");
    Serial.println("⚠️  Check wiring: SDA→21, SCL→22, VCC→3.3V, GND→GND");
  }
  
  // เริ่มต้น MAX30102 บน I2C Bus 2
  Serial.println("\n[2/2] Initializing MAX30102 on Bus 2...");
  if (initMAX30102()) {
    Serial.println("✅ MAX30102 Ready on GPIO 16/17!");
    max30102Available = true;
  } else {
    Serial.println("❌ MAX30102 Not Found!");
    Serial.println("⚠️  Check wiring: SDA→16, SCL→17, VIN→3.3V, GND→GND");
  }
  
  printSystemStatus();
  
  lastUpdateTime = millis();
  lastHealthReport = millis();
  
  if (max30102Available) {
    Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
    Serial.println("║  👆 Place your finger on MAX30102 sensor                 ║");
    Serial.println("║  ⏳ Please wait 10-15 seconds for initialization         ║");
    Serial.println("╚═══════════════════════════════════════════════════════════╝\n");
  }
  
  delay(2000);
}

void printHeader() {
  Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
  Serial.println("║   Robotic Handwear Health Monitoring System v3.3         ║");
  Serial.println("║   Dual I2C Bus + 3 Relay Feedback System                 ║");
  Serial.println("╠═══════════════════════════════════════════════════════════╣");
  Serial.println("║   📦 MPU6050 (Motion Sensor)                              ║");
  Serial.println("║      VCC → 3.3V  |  GND → GND                            ║");
  Serial.println("║      SDA → GPIO 21  |  SCL → GPIO 22                     ║");
  Serial.println("║                                                           ║");
  Serial.println("║   ❤️  MAX30102 (Heart Rate & SpO2)                        ║");
  Serial.println("║      VIN → 3.3V  |  GND → GND                            ║");
  Serial.println("║      SDA → GPIO 16  |  SCL → GPIO 17                     ║");
  Serial.println("║                                                           ║");
  Serial.println("║   🔌 RELAY MODULES (3 Units)                              ║");
  Serial.println("║      Relay 1 (SLOW)   → GPIO 25                          ║");
  Serial.println("║      Relay 2 (MEDIUM) → GPIO 26                          ║");
  Serial.println("║      Relay 3 (FAST)   → GPIO 27                          ║");
  Serial.println("╚═══════════════════════════════════════════════════════════╝");
}

void initRelays() {
  Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
  Serial.println("║  Initializing Relay Modules                              ║");
  Serial.println("╚═══════════════════════════════════════════════════════════╝");
  
  pinMode(RELAY_SLOW, OUTPUT);
  pinMode(RELAY_MEDIUM, OUTPUT);
  pinMode(RELAY_FAST, OUTPUT);
  
  // ตั้งค่าเริ่มต้นให้ Relay ทุกตัวปิด
  digitalWrite(RELAY_SLOW, RELAY_OFF);
  digitalWrite(RELAY_MEDIUM, RELAY_OFF);
  digitalWrite(RELAY_FAST, RELAY_OFF);
  
  Serial.println("  ✅ Relay 1 (SLOW)   → GPIO 25 - OFF");
  Serial.println("  ✅ Relay 2 (MEDIUM) → GPIO 26 - OFF");
  Serial.println("  ✅ Relay 3 (FAST)   → GPIO 27 - OFF");
  
  // ทดสอบ Relay ทีละตัว
  Serial.println("\n  🔧 Testing Relays...");
  
  Serial.println("  → Relay 1 ON");
  digitalWrite(RELAY_SLOW, RELAY_ON);
  delay(300);
  digitalWrite(RELAY_SLOW, RELAY_OFF);
  
  Serial.println("  → Relay 2 ON");
  digitalWrite(RELAY_MEDIUM, RELAY_ON);
  delay(300);
  digitalWrite(RELAY_MEDIUM, RELAY_OFF);
  
  Serial.println("  → Relay 3 ON");
  digitalWrite(RELAY_FAST, RELAY_ON);
  delay(300);
  digitalWrite(RELAY_FAST, RELAY_OFF);
  
  Serial.println("  ✅ Relay test completed!\n");
}

void activateRelay(int relayPin, const char* speedName) {
  digitalWrite(relayPin, RELAY_ON);
  
  Serial.print("  🔔 RELAY ACTIVATED: ");
  Serial.print(speedName);
  Serial.print(" (GPIO ");
  Serial.print(relayPin);
  Serial.println(")");
  
  // บันทึกเวลาที่เปิด Relay
  unsigned long currentTime = millis();
  if (relayPin == RELAY_SLOW) relaySlowTimer = currentTime;
  else if (relayPin == RELAY_MEDIUM) relayMediumTimer = currentTime;
  else if (relayPin == RELAY_FAST) relayFastTimer = currentTime;
}

void updateRelays() {
  unsigned long now = millis();
  
  // ปิด Relay SLOW ถ้าครบเวลา
  if (relaySlowTimer > 0 && (now - relaySlowTimer >= RELAY_PULSE_DURATION)) {
    digitalWrite(RELAY_SLOW, RELAY_OFF);
    relaySlowTimer = 0;
  }
  
  // ปิด Relay MEDIUM ถ้าครบเวลา
  if (relayMediumTimer > 0 && (now - relayMediumTimer >= RELAY_PULSE_DURATION)) {
    digitalWrite(RELAY_MEDIUM, RELAY_OFF);
    relayMediumTimer = 0;
  }
  
  // ปิด Relay FAST ถ้าครบเวลา
  if (relayFastTimer > 0 && (now - relayFastTimer >= RELAY_PULSE_DURATION)) {
    digitalWrite(RELAY_FAST, RELAY_OFF);
    relayFastTimer = 0;
  }
}

void printSystemStatus() {
  Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
  Serial.println("║  System Status                                           ║");
  Serial.println("╠═══════════════════════════════════════════════════════════╣");
  
  Serial.print("║  📦 MPU6050 (GPIO 21/22)    : ");
  if (mpu6050Available) {
    Serial.println("✅ ONLINE              ║");
  } else {
    Serial.println("❌ OFFLINE             ║");
  }
  
  Serial.print("║  ❤️  MAX30102 (GPIO 16/17)  : ");
  if (max30102Available) {
    Serial.println("✅ ONLINE              ║");
  } else {
    Serial.println("❌ OFFLINE             ║");
  }
  
  Serial.println("║  🔌 Relay 1 (GPIO 25)       : ✅ READY               ║");
  Serial.println("║  🔌 Relay 2 (GPIO 26)       : ✅ READY               ║");
  Serial.println("║  🔌 Relay 3 (GPIO 27)       : ✅ READY               ║");
  
  Serial.println("╠═══════════════════════════════════════════════════════════╣");
  
  if (mpu6050Available && max30102Available) {
    Serial.println("║  🎉 All Systems Operational!                              ║");
    Serial.println("║  📊 Monitoring: Motion + Heart Rate + SpO2 + Relay        ║");
  } else if (mpu6050Available) {
    Serial.println("║  ⚠️  Partial Operation: Motion + Relay Only               ║");
    Serial.println("║  💡 Check MAX30102 wiring for full functionality         ║");
  } else if (max30102Available) {
    Serial.println("║  ⚠️  Partial Operation: Heart Rate Only                   ║");
    Serial.println("║  💡 Check MPU6050 wiring for motion tracking            ║");
  } else {
    Serial.println("║  ❌ System Error: No Sensors Detected                     ║");
    Serial.println("║  💡 Check all sensor connections                         ║");
  }
  
  Serial.println("╠═══════════════════════════════════════════════════════════╣");
  Serial.println("║  🏥 Health Report Interval: 5 seconds                     ║");
  Serial.println("║  📡 Data Upload: Enabled                                  ║");
  Serial.println("║  🔧 IR Threshold: 10,000 (Maximum Sensitivity)          ║");
  Serial.println("║  🔔 Relay Pulse: 500ms                                    ║");
  Serial.println("║  🔊 Debug Mode: ON                                        ║");
  Serial.println("╚═══════════════════════════════════════════════════════════╝\n");
}

bool initMPU6050() {
  I2C_BUS1.beginTransmission(MPU_ADDR);
  byte error = I2C_BUS1.endTransmission();
  
  if (error != 0) {
    return false;
  }
  
  // Wake up MPU6050
  I2C_BUS1.beginTransmission(MPU_ADDR);
  I2C_BUS1.write(0x6B);
  I2C_BUS1.write(0);
  I2C_BUS1.endTransmission(true);
  delay(100);
  
  // ตั้งค่า Accelerometer range ±16g
  I2C_BUS1.beginTransmission(MPU_ADDR);
  I2C_BUS1.write(0x1C);
  I2C_BUS1.write(0x18);
  I2C_BUS1.endTransmission(true);
  
  // ตั้งค่า Gyroscope range ±1000°/s
  I2C_BUS1.beginTransmission(MPU_ADDR);
  I2C_BUS1.write(0x1B);
  I2C_BUS1.write(0x10);
  I2C_BUS1.endTransmission(true);
  
  return true;
}

bool initMAX30102() {
  if (!particleSensor.begin(I2C_BUS2, I2C_SPEED_FAST)) {
    Serial.println("  ❌ particleSensor.begin() failed!");
    return false;
  }
  
  Serial.println("  ✅ particleSensor.begin() success!");
  
  // ตั้งค่าเซนเซอร์แบบละเอียด - เพิ่มความสว่าง LED
  byte ledBrightness = 0xFF;
  byte sampleAverage = 4;
  byte ledMode = 2;
  int sampleRate = 400;
  int pulseWidth = 411;
  int adcRange = 16384;
  
  particleSensor.setup(ledBrightness, sampleAverage, ledMode, sampleRate, pulseWidth, adcRange);
  particleSensor.setPulseAmplitudeRed(0xFF);
  particleSensor.setPulseAmplitudeGreen(0);
  
  Serial.println("  🔧 MAX30102 Configuration:");
  Serial.printf("     LED Brightness: 0x%02X\n", ledBrightness);
  Serial.printf("     Sample Rate: %d Hz\n", sampleRate);
  Serial.printf("     Pulse Width: %d µs\n", pulseWidth);
  
  return true;
}

void connectWiFi() {
  Serial.println("\n📡 Connecting to WiFi...");
  Serial.print("SSID: ");
  Serial.println(ssid);
  
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✅ WiFi Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n❌ WiFi Connection Failed!");
  }
}

void readMPU6050(float &ax, float &ay, float &az, float &gx, float &gy, float &gz) {
  if (!mpu6050Available) {
    ax = ay = az = gx = gy = gz = 0;
    return;
  }
  
  I2C_BUS1.beginTransmission(MPU_ADDR);
  I2C_BUS1.write(0x3B);
  I2C_BUS1.endTransmission(false);
  I2C_BUS1.requestFrom((uint8_t)MPU_ADDR, (uint8_t)14, (uint8_t)true);
  
  int16_t axRaw = I2C_BUS1.read() << 8 | I2C_BUS1.read();
  int16_t ayRaw = I2C_BUS1.read() << 8 | I2C_BUS1.read();
  int16_t azRaw = I2C_BUS1.read() << 8 | I2C_BUS1.read();
  I2C_BUS1.read(); I2C_BUS1.read();
  int16_t gxRaw = I2C_BUS1.read() << 8 | I2C_BUS1.read();
  int16_t gyRaw = I2C_BUS1.read() << 8 | I2C_BUS1.read();
  int16_t gzRaw = I2C_BUS1.read() << 8 | I2C_BUS1.read();
  
  ax = axRaw / 2048.0;
  ay = ayRaw / 2048.0;
  az = azRaw / 2048.0;
  gx = (gxRaw / 32.8) * 0.0174533;
  gy = (gyRaw / 32.8) * 0.0174533;
  gz = (gzRaw / 32.8) * 0.0174533;
}

void readMAX30102() {
  if (!max30102Available) {
    irValue = 0;
    beatsPerMinute = 0;
    beatAvg = 0;
    spo2 = 0;
    return;
  }
  
  irValue = particleSensor.getIR();
  redValue = particleSensor.getRed();
  
  // 🔥 Debug ค่าที่อ่านได้ (แสดงทุก 2 วินาที)
  static unsigned long lastDebug = 0;
  if (millis() - lastDebug > 2000) {
    Serial.printf("📊 IR=%ld, Red=%ld, BPM=%.1f, AvgBPM=%d, SpO2=%d%%\n", 
                  irValue, redValue, beatsPerMinute, beatAvg, spo2);
    lastDebug = millis();
  }
  
  // ✅ เพิ่ม threshold เป็น 50000 (เดิม 10000)
  if (irValue < 50000) {
    if (fingerDetected) {
      Serial.println("⚠️  No finger detected (IR < 50000)");
      fingerDetected = false;
    }
    beatsPerMinute = 0;
    beatAvg = 0;
    spo2 = 0;
    return;
  } else {
    if (!fingerDetected) {
      Serial.println("✅ Finger detected!");
      Serial.printf("   IR Value: %ld (threshold: 50000)\n", irValue);
      Serial.println("⏳ Waiting for heartbeat...");
      fingerDetected = true;
    }
  }
  
  // ตรวจจับการเต้นของหัวใจ
  if (checkForBeat(irValue) == true) {
    Serial.println("\n💓💓💓 HEARTBEAT DETECTED! 💓💓💓");
    
    long delta = millis() - lastBeat;
    lastBeat = millis();
    
    beatsPerMinute = 60 / (delta / 1000.0);
    
    Serial.printf("  ⏱️  Time between beats: %ld ms\n", delta);
    Serial.printf("  💗 Instant BPM: %.2f\n", beatsPerMinute);
    
    if (beatsPerMinute < 255 && beatsPerMinute > 20) {
      rates[rateSpot++] = (byte)beatsPerMinute;
      rateSpot %= RATE_SIZE;
      
      beatAvg = 0;
      for (byte x = 0; x < RATE_SIZE; x++)
        beatAvg += rates[x];
      beatAvg /= RATE_SIZE;
      
      Serial.printf("  ✅ Average BPM: %d (from %d samples)\n", beatAvg, RATE_SIZE);
    } else {
      Serial.printf("  ⚠️  Invalid BPM: %.2f (out of range 20-255)\n", beatsPerMinute);
    }
    Serial.println();
  }
  
  // คำนวณ SpO2 (ปรับสูตรให้ดีขึ้น)
  if (redValue > 0 && irValue > 0) {
    float ratio = (float)redValue / (float)irValue;
    
    // ใช้สูตรมาตรฐานสำหรับ MAX30102
    if (ratio < 0.4) {
      spo2 = 100;
    } else if (ratio > 2.0) {
      spo2 = 90;
    } else {
      spo2 = 110 - 25 * ratio;
    }
    
    // จำกัดค่าให้อยู่ในช่วง 0-100
    if (spo2 > 100) spo2 = 100;
    if (spo2 < 0) spo2 = 0;
  }
}

void sendHealthReport(String speedType, float accDiff, float gyroDiff, float angleZ) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("❌ WiFi not connected!");
    return;
  }
  
  HTTPClient http;
  
  String url = String(serverURL) + 
               "?speed_type=" + speedType +
               "&acceleration=" + String(accDiff, 2) +
               "&gyroscope=" + String(gyroDiff, 2) +
               "&angle_z=" + String(angleZ, 1) +
               "&heart_rate=" + String(beatAvg) +
               "&spo2=" + String(spo2) +
               "&ir_value=" + String(irValue) +
               "&slow_count=" + String(slowClapCount) +
               "&medium_count=" + String(mediumClapCount) +
               "&fast_count=" + String(fastClapCount);
  
  http.begin(url);
  int httpCode = http.GET();
  
  if (httpCode > 0) {
    String payload = http.getString();
    Serial.println("📤 Data sent successfully!");
  } else {
    Serial.println("❌ Send error: " + String(httpCode));
  }
  
  http.end();
}

void classifyClap(float accDiff, float gyroDiff, float currentAngleZ) {
  String speedType = "";
  String emoji = "";
  int relayPin = 0;
  
  if (accDiff < CLAP_SLOW_THRESHOLD && gyroDiff < GYRO_SLOW_THRESHOLD) {
    speedType = "SLOW";
    emoji = "🐢";
    relayPin = RELAY_SLOW;
    slowClapCount++;
  } else if (accDiff < CLAP_MEDIUM_THRESHOLD && gyroDiff < GYRO_MEDIUM_THRESHOLD) {
    speedType = "MEDIUM";
    emoji = "🚶";
    relayPin = RELAY_MEDIUM;
    mediumClapCount++;
  } else {
    speedType = "FAST";
    emoji = "🏃";
    relayPin = RELAY_FAST;
    fastClapCount++;
  }
  
  // เปิด Relay ที่เกี่ยวข้อง
  activateRelay(relayPin, speedType.c_str());
  
  Serial.println("\n╔════════════════════════════════════════════════════════╗");
  Serial.print("║ ");
  Serial.print(emoji);
  Serial.print(" ");
  Serial.print(speedType);
  Serial.println(" HAND COMPRESSION                            ║");
  Serial.println("╠════════════════════════════════════════════════════════╣");
  Serial.printf("║ 📐 Acceleration : %-33.2f║\n", accDiff);
  Serial.printf("║ 🔄 Gyroscope    : %-33.2f║\n", gyroDiff);
  Serial.printf("║ 📏 Z-Axis Angle : %-33.1f║\n", currentAngleZ);
  Serial.println("╠════════════════════════════════════════════════════════╣");
  Serial.printf("║ ❤️  Heart Rate   : %-3d BPM                              ║\n", beatAvg);
  Serial.printf("║ 🫁 SpO2         : %-3d%%                                 ║\n", spo2);
  Serial.printf("║ 📈 IR Signal    : %-6ld                                ║\n", irValue);
  Serial.println("╠════════════════════════════════════════════════════════╣");
  Serial.printf("║ Count: 🐢%-2d | 🚶%-2d | 🏃%-2d                           ║\n", 
                slowClapCount, mediumClapCount, fastClapCount);
  Serial.println("╚════════════════════════════════════════════════════════╝\n");
  
  sendHealthReport(speedType, accDiff, gyroDiff, currentAngleZ);
}

void sendPeriodicHealthReport() {
  unsigned long now = millis();
  
  if (now - lastHealthReport >= HEALTH_REPORT_INTERVAL) {
    Serial.println("\n📊 Periodic Health Report");
    Serial.println("═══════════════════════════════════════");
    Serial.printf("❤️  Heart Rate : %d BPM\n", beatAvg);
    Serial.printf("🫁 SpO2       : %d%%\n", spo2);
    Serial.printf("📏 Hand Angle : %.1f°\n", angleZ);
    Serial.printf("📈 IR Signal  : %ld\n", irValue);
    Serial.printf("🔴 Red Signal : %ld\n", redValue);
    Serial.printf("👆 Finger     : %s\n", fingerDetected ? "Detected" : "Not Detected");
    
    // คำนวณ ratio สำหรับ debug
    if (redValue > 0 && irValue > 0) {
      float ratio = (float)redValue / (float)irValue;
      Serial.printf("📐 Red/IR Ratio: %.4f\n", ratio);
    }
    
    Serial.println("═══════════════════════════════════════\n");
    
    // เปลี่ยนจาก "MONITORING" เป็น "IDLE" เมื่อไม่มีการบีบมือ
    sendHealthReport("IDLE", 0, 0, angleZ);
    
    lastHealthReport = now;
  }
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected! Reconnecting...");
    connectWiFi();
  }
  
  // อัปเดตสถานะ Relay (ปิดอัตโนมัติเมื่อครบเวลา)
  updateRelays();
  
  readMAX30102();
  
  // แสดง Real-time IR Signal (ทุก 1 วินาที แทน 1000ms)
  if (max30102Available && millis() - lastIRDisplay > 1000) {
    Serial.printf("💡 IR=%ld, BPM=%.1f, Avg=%d", irValue, beatsPerMinute, beatAvg);
    if (irValue < 50000)  // อัปเดต threshold ให้ตรงกับ readMAX30102()
      Serial.print(" ⚠️ No finger");
    Serial.println();
    lastIRDisplay = millis();
  }
  
  float ax, ay, az, gx, gy, gz;
  readMPU6050(ax, ay, az, gx, gy, gz);
  
  unsigned long now = millis();
  float deltaTime = (now - lastUpdateTime) / 1000.0;
  
  if (mpu6050Available) {
    float gzDegrees = gz * 57.2958;
    angleZ += gzDegrees * deltaTime;
    
    while (angleZ >= 360.0) angleZ -= 360.0;
    while (angleZ < 0.0) angleZ += 360.0;
  }
  
  lastUpdateTime = now;

  float accMag = sqrt(ax * ax + ay * ay + az * az);
  float gyroMag = sqrt(gx * gx + gy * gy + gz * gz);

  if (mpu6050Available && now - prevTime > 50) {
    float accDiff = abs(accMag - prevAccMag);
    float gyroDiff = abs(gyroMag - prevGyroMag);

    if (now - lastClapTime > CLAP_COOLDOWN) {
      if (accDiff > 5.0 || gyroDiff > 1.0) {
        classifyClap(accDiff, gyroDiff, angleZ);
        lastClapTime = now;
      }
    }

    prevAccMag = accMag;
    prevGyroMag = gyroMag;
    prevTime = now;
  }
  
  sendPeriodicHealthReport();

  delay(10);
}
#include <Wire.h>
#include <WiFi.h>
#include <HTTPClient.h>

// ตั้งค่า WiFi
const char* ssid = "Stupid";        
const char* password = "Delomy2547";

// URL ของ PHP Script บน XAMPP
const char* serverURL = "http://192.168.25.216/Robotic_Handwear/sensor.php";

// ที่อยู่ MPU6050
const int MPU_ADDR = 0x68;

// กำหนดพิน I2C สำหรับ ESP32
#define I2C_SDA 21
#define I2C_SCL 22

// เก็บค่าก่อนหน้า
float prevAccMag = 0;
float prevGyroMag = 0;
unsigned long prevTime = 0;
unsigned long lastClapTime = 0;

// ตัวแปรสำหรับคำนวณมุม Z
float angleZ = 0;
unsigned long lastUpdateTime = 0;

// Threshold สำหรับจำแนกความเร็วการแบ
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

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("\n=== MPU6050 Clap Detector with Database (ESP32) ===");
  
  // เชื่อมต่อ WiFi
  connectWiFi();
  
  Serial.println("\nInitializing I2C...");
  Wire.begin(I2C_SDA, I2C_SCL);
  Wire.setClock(400000);
  delay(100);

  // ตรวจสอบการเชื่อมต่อ MPU6050
  Wire.beginTransmission(MPU_ADDR);
  byte error = Wire.endTransmission();
  
  if (error != 0) {
    Serial.println("\n*** ERROR: Cannot connect to MPU6050! ***");
    Serial.print("I2C Error code: ");
    Serial.println(error);
    Serial.println("\nCheck wiring:");
    Serial.println("  SDA -> GPIO 21");
    Serial.println("  SCL -> GPIO 22");
    Serial.println("  VCC -> 3.3V");
    Serial.println("  GND -> GND");
    while (1) delay(1000);
  }
  
  Serial.println("MPU6050 Connected!");
  
  // Wake up MPU6050
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x6B);
  Wire.write(0);
  Wire.endTransmission(true);
  delay(100);
  
  // ตั้งค่า Accelerometer range ±16g
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x1C);
  Wire.write(0x18);
  Wire.endTransmission(true);
  
  // ตั้งค่า Gyroscope range ±1000°/s
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x1B);
  Wire.write(0x10);
  Wire.endTransmission(true);
  
  Serial.println("\n>> MPU6050 Ready!");
  Serial.println(">> Sensor mounted on fingertip");
  Serial.println(">> Clap speed detection enabled:");
  Serial.println("   🐢 SLOW    : Acc < 8 or Gyro < 1.5 rad/s");
  Serial.println("   🚶 MEDIUM  : Acc 8-15 or Gyro 1.5-3.0 rad/s");
  Serial.println("   🏃 FAST    : Acc > 15 or Gyro > 3.0 rad/s");
  Serial.println("\n>> Data will be saved to MySQL database");
  Serial.println(">> Start clapping with different speeds!\n");
  
  lastUpdateTime = millis();
  delay(2000);
}

void connectWiFi() {
  Serial.println("\nConnecting to WiFi...");
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
    Serial.println("\n✓ WiFi Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n✗ WiFi Connection Failed!");
    Serial.println("Please check SSID and Password");
  }
}

void readMPU6050(float &ax, float &ay, float &az, float &gx, float &gy, float &gz) {
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x3B);
  Wire.endTransmission(false);
  Wire.requestFrom((uint8_t)MPU_ADDR, (uint8_t)14, (uint8_t)true);
  
  int16_t axRaw = Wire.read() << 8 | Wire.read();
  int16_t ayRaw = Wire.read() << 8 | Wire.read();
  int16_t azRaw = Wire.read() << 8 | Wire.read();
  Wire.read(); Wire.read();
  int16_t gxRaw = Wire.read() << 8 | Wire.read();
  int16_t gyRaw = Wire.read() << 8 | Wire.read();
  int16_t gzRaw = Wire.read() << 8 | Wire.read();
  
  ax = axRaw / 2048.0;
  ay = ayRaw / 2048.0;
  az = azRaw / 2048.0;
  gx = (gxRaw / 32.8) * 0.0174533;
  gy = (gyRaw / 32.8) * 0.0174533;
  gz = (gzRaw / 32.8) * 0.0174533;
}

void sendToDatabase(String speedType, float accDiff, float gyroDiff, float angleZ) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    
    // สร้าง URL พร้อมพารามิเตอร์
    String url = String(serverURL) + 
                 "?speed_type=" + speedType +
                 "&acceleration=" + String(accDiff, 2) +
                 "&gyroscope=" + String(gyroDiff, 2) +
                 "&angle_z=" + String(angleZ, 1) +
                 "&slow_count=" + String(slowClapCount) +
                 "&medium_count=" + String(mediumClapCount) +
                 "&fast_count=" + String(fastClapCount);
    
    http.begin(url);
    int httpCode = http.GET();
    
    if (httpCode > 0) {
      String payload = http.getString();
      Serial.println("📤 Data sent to database: " + payload);
    } else {
      Serial.println("❌ Error sending data: " + String(httpCode));
    }
    
    http.end();
  } else {
    Serial.println("❌ WiFi not connected!");
  }
}

void classifyClap(float accDiff, float gyroDiff, float currentAngleZ) {
  String speedType = "";
  String emoji = "";
  
  if (accDiff < CLAP_SLOW_THRESHOLD && gyroDiff < GYRO_SLOW_THRESHOLD) {
    speedType = "SLOW";
    emoji = "🐢";
    slowClapCount++;
  } else if (accDiff < CLAP_MEDIUM_THRESHOLD && gyroDiff < GYRO_MEDIUM_THRESHOLD) {
    speedType = "MEDIUM";
    emoji = "🚶";
    mediumClapCount++;
  } else {
    speedType = "FAST";
    emoji = "🏃";
    fastClapCount++;
  }
  
  // แสดงผลบน Serial Monitor
  Serial.println("\n╔════════════════════════════════════╗");
  Serial.print("║ ");
  Serial.print(emoji);
  Serial.print(" ");
  Serial.print(speedType);
  Serial.println(" CLAP                        ║");
  Serial.println("╠════════════════════════════════════╣");
  Serial.printf("║ Acceleration : %-18.2f║\n", accDiff);
  Serial.printf("║ Gyroscope    : %-18.2f║\n", gyroDiff);
  Serial.printf("║ Z-Axis Angle : %-18.1f║\n", currentAngleZ);
  Serial.println("╠════════════════════════════════════╣");
  Serial.printf("║ Count: 🐢%-2d | 🚶%-2d | 🏃%-2d        ║\n", 
                slowClapCount, mediumClapCount, fastClapCount);
  Serial.println("╚════════════════════════════════════╝\n");
  
  // ส่งข้อมูลไปยังฐานข้อมูล
  sendToDatabase(speedType, accDiff, gyroDiff, currentAngleZ);
}

void loop() {
  // ตรวจสอบการเชื่อมต่อ WiFi
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected! Reconnecting...");
    connectWiFi();
  }
  
  float ax, ay, az, gx, gy, gz;
  readMPU6050(ax, ay, az, gx, gy, gz);
  
  unsigned long now = millis();
  float deltaTime = (now - lastUpdateTime) / 1000.0;
  
  float gzDegrees = gz * 57.2958;
  angleZ += gzDegrees * deltaTime;
  
  while (angleZ >= 360.0) angleZ -= 360.0;
  while (angleZ < 0.0) angleZ += 360.0;
  
  lastUpdateTime = now;

  float accMag = sqrt(ax * ax + ay * ay + az * az);
  float gyroMag = sqrt(gx * gx + gy * gy + gz * gz);

  static unsigned long lastDisplayTime = 0;
  if (now - lastDisplayTime > 200) {
    Serial.printf("📍 Z: %.1f° | Speed: %.1f °/s | Acc: %.1f m/s²\n", 
                  angleZ, gzDegrees, accMag);
    lastDisplayTime = now;
  }

  if (now - prevTime > 50) {
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

  delay(10);
}
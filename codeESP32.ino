#include <WiFi.h>
#include <WiFiManager.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ESP32Servo.h>
#include <WiFiClientSecure.h>
#include <UniversalTelegramBot.h>

// --- Pins Setup ---
#define LED_WIFI      2    
#define LED_WHITE     4    
#define LED_GREEN     16   
#define LED_RED       17   
#define IR_SLOT1      35   
#define IR_SLOT2      33   
#define LDR_PIN       34   
#define BUZZER_PIN    26   
#define SERVO_PIN     13   
#define SS_PIN        5    
#define RST_PIN       27   
#define TRIG_PIN      12   
#define ECHO_PIN      14   
#define RESET_SW_PIN  32   

// --- Configuration ---
#define BOTtoken = YOUR_BOTtoken
#define CHAT_ID = YOUR_CHAT_ID
const char* serverUrl = "http://172.24.162.203/smart_parking/api.php"; 

// --- Objects ---
LiquidCrystal_I2C lcd(0x27, 16, 2); 
MFRC522 rfid(SS_PIN, RST_PIN);
Servo gateServo;
WiFiClientSecure client;
UniversalTelegramBot bot(BOTtoken, client);
WiFiManager wm; 

// --- Variables ---
unsigned long lastDashTime = 0;
unsigned long lastBlinkTime = 0;
int webGate = 0, webLight = 0, currentGatePos = 0; 
int lastParkedCount = -1; 
bool isFullNotified = false;
static int lastLightState = -1; 

void beep(int ms) {
  tone(BUZZER_PIN, 2500); 
  delay(ms);
  noTone(BUZZER_PIN);
}

void updateGate(int pos) {
  gateServo.attach(SERVO_PIN);
  gateServo.write(pos);
  delay(600); 
  gateServo.detach(); 
  currentGatePos = (pos >= 90) ? 1 : 0;
}

long getDistance() {
  digitalWrite(TRIG_PIN, LOW); delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH); delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  long duration = pulseIn(ECHO_PIN, HIGH, 20000);
  if (duration == 0) return 999;
  return duration * 0.034 / 2;
}

void setup() {
  Serial.begin(115200);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(LED_WIFI, OUTPUT);
  pinMode(LED_WHITE, OUTPUT); 
  pinMode(LED_GREEN, OUTPUT); 
  pinMode(LED_RED, OUTPUT);
  pinMode(IR_SLOT1, INPUT); 
  pinMode(IR_SLOT2, INPUT);
  pinMode(TRIG_PIN, OUTPUT); 
  pinMode(ECHO_PIN, INPUT);
  pinMode(RESET_SW_PIN, INPUT_PULLUP);

  beep(200); delay(100); beep(200);
  lcd.init(); lcd.backlight();
  lcd.setCursor(0, 0); lcd.print("CONNECTING WIFI");
  
  // ตั้งค่า WiFiManager แบบไม่บล็อกนานเกินไป
  wm.setConfigPortalTimeout(60); // ถ้าเชื่อมไม่ได้ใน 60 วินาที ให้หลุดออกมาทำ loop ต่อ
  
  if (!wm.autoConnect("SmartParking_AP")) {
    Serial.println("WiFi Failed to connect, running in offline mode.");
    lcd.setCursor(0, 1); lcd.print("Offline Mode");
  } else {
    digitalWrite(LED_WIFI, HIGH); 
    client.setInsecure();
    bot.sendMessage(CHAT_ID, "🚀 ระบบออนไลน์แล้ว!", "");
    lcd.clear(); lcd.print("WiFi Connected!");
    delay(1000);
  }

  SPI.begin(); 
  rfid.PCD_Init();
  updateGate(0); 
  lcd.clear();
}

void loop() {
  wm.process(); 

  // --- 1. WiFi Reset ---
  if (digitalRead(RESET_SW_PIN) == LOW) {
    unsigned long startPress = millis();
    while(digitalRead(RESET_SW_PIN) == LOW) {
      if (millis() - startPress > 5000) { 
        wm.resetSettings(); 
        ESP.restart(); 
      }
    }
  }

  // --- เช็คสถานะเน็ตและการกระพริบไฟ ---
  bool isConnected = (WiFi.status() == WL_CONNECTED);
  
  if (!isConnected) {
    // ถ้าไม่มีเน็ต ให้กระพริบไฟน้ำเงินทุกๆ 300ms
    if (millis() - lastBlinkTime > 300) {
      digitalWrite(LED_WIFI, !digitalRead(LED_WIFI));
      lastBlinkTime = millis();
    }
  } else {
    digitalWrite(LED_WIFI, HIGH); // มีเน็ตแล้ว ติดค้าง
  }

  // --- 2. Light Control ---
  int ldrValue = analogRead(LDR_PIN);
  int currentLightActual = 0;

  if (webLight == 1) {
    digitalWrite(LED_WHITE, HIGH);
    currentLightActual = 1;
  } else {
    if (ldrValue > 2500) { 
        digitalWrite(LED_WHITE, HIGH); 
        currentLightActual = 1;
    } else {
        digitalWrite(LED_WHITE, LOW);
        currentLightActual = 0;
    }
  }

  // ส่งแจ้งเตือนไฟ
  if (isConnected && currentLightActual != lastLightState) {
    if (currentLightActual == 1) bot.sendMessage(CHAT_ID, "💡 ไฟถนน: เปิดทำงาน", "");
    else bot.sendMessage(CHAT_ID, "🌑 ไฟถนน: ปิดทำงาน", "");
    lastLightState = currentLightActual;
  }

  // --- 3. Web Gate Control ---
  if (isConnected) {
    if (webGate == 1 && currentGatePos == 0) updateGate(90); 
    else if (webGate == 0 && currentGatePos == 1) updateGate(0);
  }

  // --- 4. RFID Access (ทำงานได้แม้ไม่มีเน็ต) ---
  if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
    long dist = getDistance();
    if (dist < 15) { 
      lcd.clear(); lcd.print("RFID GRANTED");
      beep(150);
      updateGate(90);
      if (isConnected) bot.sendMessage(CHAT_ID, "🎫 บัตรผ่าน: ยินดีต้อนรับ", "");
      delay(4000); 
      if (webGate == 0) updateGate(0);
      lcd.clear();
      lastParkedCount = -1; 
    }
    rfid.PICC_HaltA(); rfid.PCD_StopCrypto1();
  }

  // --- 5. Parking Status ---
  int cars = (digitalRead(IR_SLOT1) == LOW ? 1 : 0) + (digitalRead(IR_SLOT2) == LOW ? 1 : 0);
  if (cars != lastParkedCount) {
    lcd.setCursor(0, 0); lcd.print("CARS: "); lcd.print(cars); lcd.print("/2");
    if (cars >= 2) {
      digitalWrite(LED_RED, HIGH); digitalWrite(LED_GREEN, LOW);
      lcd.setCursor(0, 1); lcd.print("STATUS: FULL");
      if (!isFullNotified && isConnected) { bot.sendMessage(CHAT_ID, "🔴 ที่จอดรถเต็ม!", ""); isFullNotified = true; }
    } else {
      digitalWrite(LED_RED, LOW); digitalWrite(LED_GREEN, HIGH);
      lcd.setCursor(0, 1); lcd.print("FREE: "); lcd.print(2 - cars);
      if (isFullNotified && isConnected) { bot.sendMessage(CHAT_ID, "🟢 มีที่ว่างแล้ว!", ""); isFullNotified = false; }
    }
    lastParkedCount = cars;
  }

  // --- 6. Sync Data ---
  if (isConnected && (millis() - lastDashTime > 500)) { 
    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    String postData = "parked=" + String(cars) + "&ldr=" + String(ldrValue) + "&gate_status=" + String(currentGatePos); 
    int httpCode = http.POST(postData);
    if (httpCode == 200) {
      String payload = http.getString();
      StaticJsonDocument<512> doc;
      if (!deserializeJson(doc, payload)) {
        webGate = doc["gate"] | 0;   
        webLight = doc["light"] | 0; 
      }
    }
    http.end();
    lastDashTime = millis();
  }
}

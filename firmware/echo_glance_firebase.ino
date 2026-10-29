#include <ArduinoJson.h>
#include <DFRobotDFPlayerMini.h>
#include <FirebaseESP32.h>
#include <WiFi.h>
#include <Wire.h>
#include <math.h>
#include <time.h>
#include <esp_task_wdt.h>

// ============================================================
// ECHO-GLANCE : ROCK-SOLID REWRITE
// Offline-First + Background WiFi/Firebase + FreeRTOS Dual Core
// ============================================================

// ============================================================
// 1. PIN HARDWARE
// ============================================================
const int PIN_MATA_KIRI = 34;
const int PIN_MATA_KANAN = 35;
const int PIN_PB_MODE = 26;
const int PIN_BUTTON_PERAWAT = 27;
const int FSR_PIN = 32;

HardwareSerial mySerial(2);
DFRobotDFPlayerMini dfPlayer;

// ============================================================
// 2. WiFi & FIREBASE
// ============================================================
const char *WIFI_SSID = "OXYMETER";
const char *WIFI_PASSWORD = "Artanda12";

#define FIREBASE_HOST "echo-glance-default-rtdb.asia-southeast1.firebasedatabase.app"
const char FIREBASE_CLIENT_EMAIL[] PROGMEM = "firebase-adminsdk-fbsvc@echo-glance.iam.gserviceaccount.com";
const char FIREBASE_PROJECT_ID[] PROGMEM = "echo-glance";
const char FIREBASE_PRIVATE_KEY[] PROGMEM = "-----BEGIN PRIVATE KEY-----\n\
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDqZefwrgbMUnte\n\
eGqHpJFnxpzPoWsLXJTLfrZEPksq6ZuAT2SdqP40L3a0eZMihv7Y6l4BIUd47iYr\n\
KZ+0gBwmpv7sahADKzTxvZePo4WoNiJ0h0gqhDH/32KcbOnledYBpr5Jweet5wfV\n\
NwkAhDixJUGl469+zXYyVthJ8Km0+c0UNOzI+dRlcNopbsPq0RiIQZtxHBU/SrSo\n\
U3ruKdh798+/6NYeP2mLy67cLkHCCHiDpOLrDH+ALuUkIbu7WhEV8ytuTs5dcJah\n\
XC2o3trpNVbQj1ExhmwKTEo7qYPNPr8yrG949L+jBeNi5pxOXodS8akcx6bOYgEn\n\
yEaQWOmVAgMBAAECggEAFBmJ/UmxsTVaI+c33LWAIdcrsjhOzr9xI6QUKspGtYF+\n\
a/nNkHbMhEVqV0kyP0c2ilD1SlaEDlApphDgg4tqyTu6Z3kkMgld9bmCyiFFFaq9\n\
Y02zIrDe/WiL8wr54aDZePRx+S7aAuppRwom86GNn6R+D890SJouH7HybnYR2hVZ\n\
oxd8CQ2FI5FK9+fYps7+d+mgyNieEsWOfi8tmPUqEmhIH7wfP8/eJM679WoMkgIt\n\
zFd1UngqADlfaSuLUoYl44p0xmYxTFJKkdTsa6lUjOSvpfY6xBJ7tLQ8bZmb2QHt\n\
lUe3cc+ffV/oRxoHTsBTtvL7p3vtFF0/YcqZEWP+/wKBgQD8lMwDC7N1wtHlQE5l\n\
snvftanzjrhc3dJ36i2XEGSrkxsbCZzCIOqfjfqUfL2Azhnqqx/s4speTvfRvSwG\n\
7yeWwpcH3+X1s+lxMzNl9UD6jzBoi74NVcBulIGmKG6EiNqQmhFd82tzAhO+RdDi\n\
UB+TJceBtIyv2UPPB6XfZWeOywKBgQDtkhqQUyYvdB1Nwp6vC/UrJVPNpC0fAFws\n\
FQpBylCU0Ajr1JYWUNbxhA8v6Z/+KnKHoHpBma3dHlLys2hDwUApsiXBzmXSwT/U\n\
l0s+nR4HqzjIhX42L7s4JYW2mZB+rGgSmhhnnY4Jz2oh0XXqbQweRw1xek241TNn\n\
mGcWjBD9HwKBgQDGEeV+SfG8Wj+BGJGh/HpZrzsMLRJ0eNswgCqqOQfI6H4sELim\n\
H2v+HTews7VjybvXI8TQXfS2ShTanA9njKmt9wC7AyBxMlnRFIas4MvI8PrUMSmj\n\
iWhzDvAwnao1MqC7HqaZ12aedLWI03T/1Dc+jDTMiQp+Z9FeC7BO5FV2SwKBgQC2\n\
mBwIn4/y2xkGwjX6roMiRU9S+AOiF0HAs+8tP9WNexPQRpWQ01NRT8HofnT1AiUC\n\
7ynoL7j9AaiZV6EkqmypX347pmqGgPeRazNvDY2eMVC0dj3aYfdQoS5TeVYgtlxL\n\
J9roEKWyDpQxJ++sXWH6B3xiorEIBf27etyI7ODF2wKBgBIdXDBn3dXgtth0wrh3\n\
8JDajrdbWZVOC5htWpJy+xK6P+/VLUNxdgeotc6LUxQQo6ZYjtvSOMilU+jixFK4\n\
sEKA4bycFwnFma+zWsrQ/WdUvtfye86rk3GHco6LWQLs74J1s9BjXMS3D44l4ovH\n\
ZR+k71NyhYEDWMEK+q4BA4d4\n\
-----END PRIVATE KEY-----\n";

FirebaseData fbdo;
FirebaseAuth auth;
FirebaseConfig config;
FirebaseData streamFbdo;

TaskHandle_t TaskFirebase = NULL;

const unsigned long FB_STATUS_INTERVAL = 5000;
const unsigned long FB_MPU_INTERVAL = 500;
const unsigned long FB_FSR_INTERVAL = 500;
const unsigned long WIFI_RETRY_INTERVAL = 10000;

unsigned long waktuFBStatusTerakhir = 0;
unsigned long waktuFBMpuTerakhir = 0;
unsigned long waktuFBFsrTerakhir = 0;
unsigned long waktuWiFiRetryTerakhir = 0;

bool firebaseReady = false;
bool streamReady = false;

// ============================================================
// 3. FREERTOS QUEUE (Pengganti Mutex untuk keamanan Core 0 & 1)
// ============================================================
struct NotifMessage {
  char jenisKebutuhan[64];
  char layer[16];
  time_t eventTime;
};
QueueHandle_t notifQueue = NULL;

// ============================================================
// 4. WAKTU / NTP
// ============================================================
const char *NTP_SERVER_1 = "pool.ntp.org";
const char *NTP_SERVER_2 = "time.nist.gov";
const long GMT_OFFSET_SEC = 7 * 3600;
const int DAYLIGHT_OFFSET_SEC = 0;

bool waktuValid = false;
unsigned long waktuNTPTerakhir = 0;
const unsigned long NTP_CHECK_INTERVAL = 10000;

// Data FSR untuk dikirim Core 0
int lastCurrentStrength = 0;
int lastAverageStrength = 0;

// ============================================================
// 5. MPU6050
// ============================================================
#define MPU_ADDR 0x68
#define PWR_MGMT_1 0x6B
#define ACCEL_CONFIG 0x1C
#define ACCEL_XOUT_H 0x3B

const float BATAS_PERUBAHAN = 15.0;
const unsigned long WAKTU_DETEKSI = 2500;
const unsigned long INTERVAL_BACA = 200;

float referenceRoll = 0;
float referencePitch = 0;
float roll = 0;
float pitch = 0;
float perubahanRoll = 0;
float perubahanPitch = 0;

bool posisiBerubah = false;
unsigned long waktuMulaiPerubahan = 0;
unsigned long waktuPembacaanMPU = 0;
unsigned long waktuAlarmKepalaTerakhir = 0;

// ============================================================
// 6. TCRT5000 / FSM KEDIPAN
// ============================================================
int thresholdMataKanan = 4095;
const int OFFSET_TOLERANSI = 80;

const unsigned long DURASI_SHORT_MIN = 200;
const unsigned long DURASI_SHORT_MAX = 1000;
const unsigned long DURASI_LONG_MIN = 1200;
const unsigned long DURASI_LONG_MAX = 3500;
const unsigned long TIMEOUT_SEQUENCE = 2000;

unsigned long waktuMulaiTertutup = 0;
bool sedangTertutup = false;

String sequenceKedipan = "";
unsigned long waktuKedipanTerakhir = 0;

// ============================================================
// 7. FSR402
// ============================================================
const int ADC_MIN = 80;
const int ADC_MAX = 2000;
const int NUM_SAMPLES = 20;

int samples[NUM_SAMPLES];
int sampleIndex = 0;
long totalSum = 0;

const unsigned long INTERVAL_FSR = 20;
unsigned long waktuPembacaanFSR = 0;

// ============================================================
// 8. MENU & ALARM
// ============================================================
const int DAFTAR_MENU[] = {4, 5, 6, 7, 8, 9};
const int TOTAL_MENU = sizeof(DAFTAR_MENU) / sizeof(DAFTAR_MENU[0]);
int indeksMenu = 0;

bool isInsideMenu = false;
bool isAlarmActive = false;
int currentMenuAudio = 4;

unsigned long waktuAlarmTerakhir = 0;
const unsigned long JEDA_ALARM = 5000;

bool tungguMenuOpsiAwal = false;
unsigned long waktuMulaiTungguMenu = 0;
unsigned long waktuAbaikanMataTerakhir = 0;

// ============================================================
// 9. TOMBOL
// ============================================================
int statePBMode = LOW;
int lastStatePBMode = -1;

int statePBPerawat = LOW;
int lastStatePBPerawat = -1;
int lastPhysicalStatePBMode = -1;

// ============================================================
// HELPER MPU
// ============================================================
void writeRegister(byte reg, byte data) {
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(reg);
  Wire.write(data);
  Wire.endTransmission();
}

int16_t read16(byte reg) {
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(reg);
  Wire.endTransmission(false);
  Wire.requestFrom(MPU_ADDR, 2);
  int16_t value = 0;
  if (Wire.available() >= 2) {
    value = (Wire.read() << 8);
    value |= Wire.read();
  }
  return value;
}

void bacaAccelerometer(float &ax, float &ay, float &az) {
  int16_t rawX = read16(ACCEL_XOUT_H);
  int16_t rawY = read16(ACCEL_XOUT_H + 2);
  int16_t rawZ = read16(ACCEL_XOUT_H + 4);

  ax = rawX / 16384.0;
  ay = rawY / 16384.0;
  az = rawZ / 16384.0;
}

void bacaPosisiKepala() {
  float ax, ay, az;
  bacaAccelerometer(ax, ay, az);
  roll = atan2(ay, az) * 180.0 / PI;
  pitch = atan2(-ax, sqrt(ay * ay + az * az)) * 180.0 / PI;
  perubahanRoll = abs(roll - referenceRoll);
  perubahanPitch = abs(pitch - referencePitch);
}

// ============================================================
// WAKTU
// ============================================================
bool isTimeValid() {
  time_t now = time(nullptr);
  return (now > 1700000000);
}

void updateTimeStatus() {
  if (!isTimeValid()) {
    waktuValid = false;
    return;
  }
  waktuValid = true;
}

String formatTimestamp(time_t t) {
  if (t <= 0) {
    return "waktu_tidak_tersedia";
  }
  struct tm timeinfo;
  localtime_r(&t, &timeinfo);
  char buffer[25];
  strftime(buffer, sizeof(buffer), "%Y-%m-%d %H:%M:%S", &timeinfo);
  return String(buffer);
}

time_t getEventTimestamp() {
  time_t now = time(nullptr);
  if (now > 1700000000) {
    return now;
  }
  return 0;
}

void setupNTP() {
  configTime(GMT_OFFSET_SEC, DAYLIGHT_OFFSET_SEC, NTP_SERVER_1, NTP_SERVER_2);
  Serial.println("[TIME] Sinkronisasi waktu NTP dimulai...");
}

// ============================================================
// MAPPING KEBUTUHAN
// ============================================================
String getNamaKebutuhan(int audioNum) {
  switch (audioNum) {
  case 4: return "Ingin makan?";
  case 5: return "Ingin minum?";
  case 6: return "Ingin buang air kecil?";
  case 7: return "Ingin buang air besar?";
  case 8: return "Ingin berjemur?";
  case 9: return "Ingin tidur?";
  default: return "Kebutuhan Pasien";
  }
}

// ============================================================
// KALIBRASI
// ============================================================
void kalibrasiSistem() {
  Serial.println();
  Serial.println("==========================================");
  Serial.println("   PROSES KALIBRASI ADAPTIF MULTI-SENSOR");
  Serial.println("==========================================");
  Serial.println("1. Pasien harap MEMBUKA MATA KANAN dengan normal.");
  Serial.println("2. Posisikan KEPALA PASIEN secara lurus.");
  Serial.println("Proses perekaman data dimulai dalam 3 detik...");

  delay(3000);

  long totalMataKanan = 0;
  float totalRoll = 0;
  float totalPitch = 0;
  const int jumlahSampel = 100;

  for (int i = 0; i < jumlahSampel; i++) {
    analogRead(PIN_MATA_KANAN);
    delayMicroseconds(20);
    totalMataKanan += analogRead(PIN_MATA_KANAN);

    float ax, ay, az;
    bacaAccelerometer(ax, ay, az);
    totalRoll += atan2(ay, az) * 180.0 / PI;
    totalPitch += atan2(-ax, sqrt(ay * ay + az * az)) * 180.0 / PI;
    delay(20);
  }

  int rataMataKananTerbuka = totalMataKanan / jumlahSampel;
  thresholdMataKanan = rataMataKananTerbuka - OFFSET_TOLERANSI;
  thresholdMataKanan = constrain(thresholdMataKanan, 0, 4095);
  referenceRoll = totalRoll / jumlahSampel;
  referencePitch = totalPitch / jumlahSampel;

  Serial.println();
  Serial.println("✓ KALIBRASI SELESAI!");
  Serial.print("Baseline Mata Kanan : ");
  Serial.println(rataMataKananTerbuka);
  Serial.print("Threshold Kedip     : ");
  Serial.println(thresholdMataKanan);
  Serial.print("Referensi Roll      : ");
  Serial.println(referenceRoll, 1);
  Serial.print("Referensi Pitch     : ");
  Serial.println(referencePitch, 1);
  Serial.println("==========================================");
}

// ============================================================
// FUNGSI FIREBASE (KHUSUS CORE 0)
// ============================================================
bool firebaseIsUsable() {
  return WiFi.status() == WL_CONNECTED && firebaseReady && Firebase.ready();
}

void publishDeviceStatus() {
  if (!firebaseIsUsable()) return;
  FirebaseJson json;
  json.set("mode", (statePBMode == HIGH) ? "aktif" : "tidak_aktif");
  json.set("sensors/mata", true);
  json.set("sensors/mpu", statePBMode == HIGH);
  json.set("sensors/fsr", statePBMode == HIGH);
  json.set("wifi", "connected");
  json.set("last_updated", (long)time(nullptr));

  esp_task_wdt_reset();
  if (!Firebase.setJSON(fbdo, "/echoglance/device/status", json)) {
    Serial.print("[Firebase] Status gagal: ");
    Serial.println(fbdo.errorReason());
  }
  vTaskDelay(pdMS_TO_TICKS(10));
}

void publishMPUData() {
  if (!firebaseIsUsable()) return;
  FirebaseJson json;
  json.set("pitch", round(pitch * 10.0) / 10.0);
  json.set("roll", round(roll * 10.0) / 10.0);
  json.set("timestamp", (long)time(nullptr));

  esp_task_wdt_reset();
  Firebase.setJSON(fbdo, "/echoglance/device/sensor/mpu", json);
  vTaskDelay(pdMS_TO_TICKS(10));
}

void publishFSRData(int currentStrength, int averageStrength) {
  if (!firebaseIsUsable()) return;
  FirebaseJson json;
  json.set("force", currentStrength);
  json.set("averageStrength", averageStrength);
  json.set("timestamp", (long)time(nullptr));

  esp_task_wdt_reset();
  Firebase.setJSON(fbdo, "/echoglance/device/sensor/fsr", json);
  vTaskDelay(pdMS_TO_TICKS(10));
}

void doPublishNotifikasiCore0(const char* jenisKebutuhan, const char* layer, time_t eventTime) {
  if (!firebaseIsUsable()) return;

  FirebaseJson json;
  json.set("jenis_kebutuhan", jenisKebutuhan);
  json.set("layer", layer);
  json.set("timestamp", (long)eventTime);
  json.set("timestamp_text", formatTimestamp(eventTime));
  json.set("is_read", 0);
  json.set("source", "online");

  String path = "/notifications/notif_" + String((unsigned long)millis());

  esp_task_wdt_reset();
  if (!Firebase.setJSON(fbdo, path.c_str(), json)) {
    Serial.print("[Firebase] Notifikasi gagal: ");
    Serial.println(fbdo.errorReason());
  } else {
    Serial.print("[Firebase] Notifikasi berhasil dikirim: ");
    Serial.println(jenisKebutuhan);
  }
  vTaskDelay(pdMS_TO_TICKS(10));
}

// ============================================================
// KIRIM NOTIFIKASI VIA QUEUE (DARI CORE 1 KE CORE 0)
// ============================================================
void pushNotifikasiToQueue(const String &jenisKebutuhan, const String &layer) {
  time_t eventTime = getEventTimestamp();
  
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[Offline] WiFi tidak tersedia, pesan dibatalkan.");
    return; // Pesan diabaikan 100% jika offline. Tidak disimpan.
  }

  if (notifQueue != NULL) {
    NotifMessage msg;
    strncpy(msg.jenisKebutuhan, jenisKebutuhan.c_str(), sizeof(msg.jenisKebutuhan) - 1);
    msg.jenisKebutuhan[sizeof(msg.jenisKebutuhan) - 1] = '\0';
    
    strncpy(msg.layer, layer.c_str(), sizeof(msg.layer) - 1);
    msg.layer[sizeof(msg.layer) - 1] = '\0';
    
    msg.eventTime = eventTime;

    if (xQueueSend(notifQueue, &msg, 0) != pdPASS) {
      Serial.println("[Queue] Gagal mengirim pesan ke Core 0 (Antrean Penuh!)");
    } else {
      Serial.println("[Queue] Pesan diteruskan ke Core 0 untuk diupload.");
    }
  }
}

void publishNotifikasi(const String &jenisKebutuhan, const String &layer) {
  pushNotifikasiToQueue(jenisKebutuhan, layer);
}

void buatNotifikasiKebutuhan(const String &jenisKebutuhan, const String &layer) {
  pushNotifikasiToQueue(jenisKebutuhan, layer);
}

// ============================================================
// WIFI BACKGROUND
// ============================================================
void startWiFiConnection() {
  Serial.print("[WiFi] Mencoba koneksi ke: ");
  Serial.println(WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  waktuWiFiRetryTerakhir = millis();
}

void handleWiFiBackground() {
  unsigned long sekarang = millis();
  if (WiFi.status() == WL_CONNECTED) {
    if (!waktuValid) {
      updateTimeStatus();
      if (waktuValid) {
        Serial.println("[TIME] Waktu NTP sudah tersedia.");
        Serial.print("[TIME] ");
        Serial.println(formatTimestamp(time(nullptr)));
      }
    }
    return;
  }

  if (sekarang - waktuWiFiRetryTerakhir >= WIFI_RETRY_INTERVAL) {
    waktuWiFiRetryTerakhir = sekarang;
    Serial.println("[WiFi] Belum terhubung. Mencoba kembali di background...");
    WiFi.disconnect();
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  }
}

// ============================================================
// FIREBASE STREAM
// ============================================================
void streamCallback(StreamData data) {
  if (data.dataType() != "json") return;
  FirebaseJson json;
  FirebaseJsonData jsonData;
  json.setJsonData(data.jsonString());
  json.get(jsonData, "command");
  if (!jsonData.success) return;
  if (jsonData.stringValue == "toggle_mode") {
    Serial.println("[Web Command] Toggle Mode diterima!");
    statePBMode = (statePBMode == HIGH) ? LOW : HIGH;
  }
}

void streamTimeoutCallback(bool timeout) {
  if (timeout) {
    Serial.println("[Firebase] Stream timeout, mencoba kembali...");
    streamReady = false;
  }
}

void setupFirebase() {
  static bool isFirebaseInitialized = false;

  if (!isFirebaseInitialized) {
    Serial.println("[Firebase] Memulai Inisialisasi Firebase...");
    config.database_url = FIREBASE_HOST;
    config.service_account.data.client_email = FIREBASE_CLIENT_EMAIL;
    config.service_account.data.project_id = FIREBASE_PROJECT_ID;
    config.service_account.data.private_key = FIREBASE_PRIVATE_KEY;

    Firebase.begin(&config, &auth);
    Firebase.reconnectWiFi(true);
    fbdo.setResponseSize(1024);

    isFirebaseInitialized = true;
    Serial.println("[Firebase] Inisialisasi Firebase Selesai.");
  }
  firebaseReady = true;
  Serial.println("[Firebase] Client siap. Menunggu token otentikasi...");
}

// ============================================================
// FIREBASE TASK - CORE 0 (MURNI TANPA MUTEX, PAKAI QUEUE)
// ============================================================
void firebaseTaskCode(void *pvParameters) {
  esp_task_wdt_add(NULL); // Daftarkan task ini ke Watchdog Timer
  bool previousWiFiConnected = false;
  for (;;) {
    esp_task_wdt_reset(); // Beri tahu Watchdog bahwa task ini masih hidup
    
    handleWiFiBackground();
    bool wifiConnected = WiFi.status() == WL_CONNECTED;

    if (wifiConnected && !previousWiFiConnected) {
      Serial.println("\n==========================================");
      Serial.println("[WiFi] TERHUBUNG KEMBALI");
      Serial.print("[WiFi] IP: ");
      Serial.println(WiFi.localIP());
      Serial.println("==========================================");

      setupNTP();
      delay(500);
      updateTimeStatus();
      
      if (!firebaseReady) {
        setupFirebase();
      }
    }

    if (!wifiConnected && previousWiFiConnected) {
      Serial.println("\n[WiFi] Koneksi terputus.");
      Serial.println("[WiFi] Sistem utama tetap berjalan.");
    }
    previousWiFiConnected = wifiConnected;

    // Background Firebase worker
    if (wifiConnected && firebaseReady) {
      if (Firebase.ready()) {
        
        // Memulai Stream jika belum jalan
        if (!streamReady) {
          Serial.println("[Firebase] Token siap! Membuka jalur stream...");
          if (Firebase.beginStream(streamFbdo, "/echoglance/device/status/set")) {
            Firebase.setStreamCallback(streamFbdo, streamCallback, streamTimeoutCallback);
            streamReady = true;
            Serial.println("[Firebase] Stream aktif.");
          } else {
            Serial.print("[Firebase] Gagal stream: ");
            Serial.println(streamFbdo.errorReason());
          }
        }

        unsigned long sekarang = millis();

        // 1. Kirim Status
        if (sekarang - waktuFBStatusTerakhir >= FB_STATUS_INTERVAL) {
          waktuFBStatusTerakhir = sekarang;
          publishDeviceStatus();
        }
        // 2. Kirim MPU
        if (statePBMode == HIGH && sekarang - waktuFBMpuTerakhir >= FB_MPU_INTERVAL) {
          waktuFBMpuTerakhir = sekarang;
          publishMPUData();
        }
        // 3. Kirim FSR
        if (statePBMode == HIGH && sekarang - waktuFBFsrTerakhir >= FB_FSR_INTERVAL) {
          waktuFBFsrTerakhir = sekarang;
          publishFSRData(lastCurrentStrength, lastAverageStrength);
        }

        // 4. Proses Antrean Notifikasi dari Core 1
        NotifMessage msg;
        while (xQueueReceive(notifQueue, &msg, 0) == pdTRUE) {
          doPublishNotifikasiCore0(msg.jenisKebutuhan, msg.layer, msg.eventTime);
        }
      }
    }
    delay(20);
  }
}

// ============================================================
// ALARM KEBUTUHAN SELESAI
// ============================================================
void matikanAlarmMata() {
  isAlarmActive = false;
  isInsideMenu = false;
  tungguMenuOpsiAwal = false;
  Serial.println("-> KEPUTUSAN: PESAN TERPENUHI! Alarm Berhenti.");
  dfPlayer.play(10);
}

// ============================================================
// SETUP MAIN
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n==========================================");
  Serial.println("        ECHO-GLANCE STARTING");
  Serial.println(" Rock-Solid Version + Thread Safe");
  Serial.println("==========================================");

  Wire.begin(21, 22);

  pinMode(PIN_MATA_KIRI, INPUT);
  pinMode(PIN_MATA_KANAN, INPUT);
  pinMode(PIN_PB_MODE, INPUT);
  pinMode(PIN_BUTTON_PERAWAT, INPUT);
  pinMode(FSR_PIN, INPUT);

  for (int i = 0; i < NUM_SAMPLES; i++) {
    samples[i] = 0;
  }
  totalSum = 0;

  // Membuat FreeRTOS Queue untuk mengoper pesan dari Core 1 ke Core 0
  notifQueue = xQueueCreate(10, sizeof(NotifMessage));

  mySerial.begin(9600, SERIAL_8N1, 16, 17);
  Serial.println("Menginisialisasi DFPlayer...");
  int dfRetry = 0;
  while (!dfPlayer.begin(mySerial) && dfRetry < 3) {
    dfRetry++;
    Serial.print("DFPlayer gagal, percobaan ");
    Serial.println(dfRetry);
    delay(1000);
  }
  if (dfRetry >= 3) {
    Serial.println("Melanjutkan tanpa audio.");
  }
  dfPlayer.volume(25);

  writeRegister(PWR_MGMT_1, 0x00);
  writeRegister(ACCEL_CONFIG, 0x00);

  startWiFiConnection();

  // Task Firebase di Core 0 (Stack 32KB mencegah BearSSL Crash)
  xTaskCreatePinnedToCore(firebaseTaskCode, "TaskFirebase", 32768, NULL, 1, &TaskFirebase, 0);

  Serial.println("\n✓ Sistem utama siap.");
  Serial.println("==========================================");
}

// ============================================================
// LOOP - CORE 1
// ============================================================
void loop() {

  // 1. TOGGLE MODE FISIK
  int currentPhysicalState = digitalRead(PIN_PB_MODE);
  if (lastPhysicalStatePBMode == -1) {
    lastPhysicalStatePBMode = currentPhysicalState;
  }
  if (currentPhysicalState != lastPhysicalStatePBMode) {
    statePBMode = currentPhysicalState;
    lastPhysicalStatePBMode = currentPhysicalState;
  }

  // 2. TOMBOL PERAWAT
  statePBPerawat = digitalRead(PIN_BUTTON_PERAWAT);
  if (lastStatePBPerawat == -1) {
    lastStatePBPerawat = statePBPerawat;
  }
  if (lastStatePBMode == -1) {
    lastStatePBMode = statePBMode;
  }

  // 3. TCRT5000
  analogRead(PIN_MATA_KIRI);
  delayMicroseconds(50);
  int valMataKiri = analogRead(PIN_MATA_KIRI);

  analogRead(PIN_MATA_KANAN);
  delayMicroseconds(50);
  int valMataKanan = analogRead(PIN_MATA_KANAN);

  bool mataSedangAktif = (valMataKanan <= (thresholdMataKanan + 20));

  // 4. FSR402
  if (millis() - waktuPembacaanFSR >= INTERVAL_FSR) {
    waktuPembacaanFSR = millis();
    int currentStrength = 0;
    int averageStrength = 0;

    if (!mataSedangAktif) {
      analogRead(FSR_PIN);
      delayMicroseconds(50);
      int rawADC = analogRead(FSR_PIN);
      currentStrength = map(rawADC, ADC_MIN, ADC_MAX, 0, 100);
      currentStrength = constrain(currentStrength, 0, 100);
      if (currentStrength <= 8) {
        currentStrength = 0;
      }
    } else {
      currentStrength = 0;
    }

    totalSum = totalSum - samples[sampleIndex];
    samples[sampleIndex] = currentStrength;
    totalSum = totalSum + samples[sampleIndex];
    sampleIndex = (sampleIndex + 1) % NUM_SAMPLES;
    averageStrength = totalSum / NUM_SAMPLES;
    if (averageStrength <= 3) {
      averageStrength = 0;
    }
    lastCurrentStrength = currentStrength;
    lastAverageStrength = averageStrength;
  }

  // 5. PERUBAHAN MODE
  if (statePBMode != lastStatePBMode) {
    if (statePBMode == HIGH) {
      Serial.println("pbMode DITEKAN: Memulai Kalibrasi -> Play 0001");
      dfPlayer.play(1);
      kalibrasiSistem();
    } else {
      Serial.println("pbMode DILEPAS: Mode MPU & FSR Mati -> Play 0002");
      dfPlayer.play(2);
      if (posisiBerubah) {
        posisiBerubah = false;
        dfPlayer.stop();
      }
      waktuMulaiPerubahan = 0;
    }
    lastStatePBMode = statePBMode;
    waktuFBStatusTerakhir = 0;
    delay(50);
  }

  // 6. MPU6050
  if (statePBMode == HIGH) {
    if (millis() - waktuPembacaanMPU >= INTERVAL_BACA) {
      waktuPembacaanMPU = millis();
      bacaPosisiKepala();
      bool terdeteksiPerubahan = (perubahanRoll >= BATAS_PERUBAHAN) || (perubahanPitch >= BATAS_PERUBAHAN);

      if (terdeteksiPerubahan) {
        if (waktuMulaiPerubahan == 0) {
          waktuMulaiPerubahan = millis();
        }
        if (millis() - waktuMulaiPerubahan >= WAKTU_DETEKSI && !posisiBerubah) {
          posisiBerubah = true;
          Serial.println("⚠️ ALARM: KEPALA PASIEN MIRING!");
          dfPlayer.play(11);
          waktuAlarmKepalaTerakhir = millis();

          publishNotifikasi("⚠️ Peringatan: Posisi kepala miring berlebih!", "pesan");
        }
      } else {
        waktuMulaiPerubahan = 0;
        if (posisiBerubah) {
          posisiBerubah = false;
          Serial.println("✓ INFORMASI: POSISI KEPALA KEMBALI NORMAL");
          dfPlayer.stop();
          waktuAlarmTerakhir = millis();
        }
      }
    }
  } else {
    posisiBerubah = false;
  }

  // 7. TRANSISI MENU
  if (tungguMenuOpsiAwal) {
    if (millis() - waktuMulaiTungguMenu >= 2500) {
      tungguMenuOpsiAwal = false;
      dfPlayer.play(currentMenuAudio);
      waktuAlarmTerakhir = millis();
      waktuAbaikanMataTerakhir = millis();
    }
  }

  // 8. PRIORITAS ALARM
  if (posisiBerubah && statePBMode == HIGH) {
    if (millis() - waktuAlarmKepalaTerakhir >= JEDA_ALARM) {
      Serial.println("[Priority Overwrite] Mengulang Voice 0011");
      dfPlayer.play(11);
      waktuAlarmKepalaTerakhir = millis();
    }
  } else if (isAlarmActive && !tungguMenuOpsiAwal) {
    if (millis() - waktuAlarmTerakhir >= JEDA_ALARM) {
      Serial.print("Alarm Pengingat: Voice Kebutuhan ");
      Serial.println(currentMenuAudio);
      dfPlayer.play(currentMenuAudio);
      waktuAlarmTerakhir = millis();
    }
  }

  // 9. TOMBOL FISIK PERAWAT
  if (statePBPerawat != lastStatePBPerawat) {
    if (statePBPerawat == HIGH && isAlarmActive) {
      Serial.println("[Tombol Fisik] Perawat mengonfirmasi kebutuhan selesai.");
      matikanAlarmMata();
    }
    lastStatePBPerawat = statePBPerawat;
    delay(50);
  }

  // 10. FSM KEDIPAN MATA
  if (millis() - waktuAbaikanMataTerakhir >= 500) {
    if (valMataKanan <= thresholdMataKanan && !sedangTertutup) {
      waktuMulaiTertutup = millis();
      sedangTertutup = true;
    } else if (valMataKanan > (thresholdMataKanan + 50) && sedangTertutup) {
      unsigned long durasi = millis() - waktuMulaiTertutup;
      sedangTertutup = false;
      char tipeKedipan = 'X';

      if (durasi >= DURASI_SHORT_MIN && durasi <= DURASI_SHORT_MAX) {
        tipeKedipan = 'S';
        Serial.println("-> Kedipan Kanan PENDEK (S)");
      } else if (durasi >= DURASI_LONG_MIN && durasi <= DURASI_LONG_MAX) {
        tipeKedipan = 'P';
        Serial.println("-> Kedipan Kanan PANJANG (P)");
      }

      if (tipeKedipan != 'X') {
        sequenceKedipan += tipeKedipan;
        waktuKedipanTerakhir = millis();
      }
    }
  } else {
    sedangTertutup = false;
  }

  // 11. EVALUASI POLA
  if (sequenceKedipan.length() > 0 && millis() - waktuKedipanTerakhir > TIMEOUT_SEQUENCE && !sedangTertutup) {
    Serial.print("Pola Mata Kanan Terdeteksi: ");
    Serial.println(sequenceKedipan);

    if (!posisiBerubah) {
      if (sequenceKedipan == "P") {
        if (isInsideMenu && !isAlarmActive && !tungguMenuOpsiAwal) {
          Serial.println("[R1] Keputusan: IYA. Masuk Mode Alarm Kebutuhan.");
          isAlarmActive = true;
          waktuAlarmTerakhir = millis();
          dfPlayer.play(currentMenuAudio);
          String kebutuhan = getNamaKebutuhan(currentMenuAudio);
          buatNotifikasiKebutuhan(kebutuhan, "layer_2");
        }
      } else if (sequenceKedipan == "SS") {
        if (isInsideMenu && !isAlarmActive && !tungguMenuOpsiAwal) {
          Serial.println("[R2] Keputusan: TIDAK (Pindah Opsi Menu)");
          indeksMenu++;
          if (indeksMenu >= TOTAL_MENU) {
            indeksMenu = 0;
          }
          currentMenuAudio = DAFTAR_MENU[indeksMenu];
          dfPlayer.play(currentMenuAudio);
          waktuAbaikanMataTerakhir = millis();
        }
      } else if (sequenceKedipan == "PP") {
        if (!isInsideMenu && !isAlarmActive) {
          Serial.println("[R3] Keputusan: MASUK MENU NAVIGASI");
          isInsideMenu = true;
          currentMenuAudio = 4;
          indeksMenu = 0;
          dfPlayer.play(3);
          tungguMenuOpsiAwal = true;
          waktuMulaiTungguMenu = millis();
          publishNotifikasi("Pasien membuka Menu Navigasi", "layer_1");
        }
      } else if (sequenceKedipan == "PS") {
        if (isAlarmActive) {
          Serial.println("[R4] Isyarat Mata Pasien: Terpenuhi.");
          matikanAlarmMata();
          publishNotifikasi("Kebutuhan pasien telah terpenuhi ✓", "layer_1");
        }
      } else {
        Serial.println("Pola tidak sesuai aturan sistem. Diabaikan.");
      }
    } else {
      Serial.println("Pola diabaikan karena kepala pasien sedang miring!");
    }
    sequenceKedipan = "";
  }

  delay(20);
}

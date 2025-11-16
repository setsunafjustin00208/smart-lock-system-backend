/*
 * ESP32 Test Code for Smart Lock Backend Integration
 * API-based command system using credentials configuration
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include "credentials.h"

// WiFi credentials from config
const char* ssid = WIFI_SSID;
const char* password = WIFI_PASSWORD;

// Server configuration from config
const char* serverURL = SERVER_URL;
const char* hardwareId = ESP32_HARDWARE_ID;

#define LED_PIN 2
#define LOCK_PIN 4  // GPIO 4 for lock control (relay/servo)

// Connection status
bool wifiConnected = false;
bool backendConnected = false;
bool stateSynced = false;

// Lock state
bool isLocked = true;
bool previousLockState = true;

// Timing variables
unsigned long lastHeartbeat = 0;
unsigned long lastStatusUpdate = 0;
unsigned long lastCommandCheck = 0;
unsigned long lastLEDUpdate = 0;
// Timing variables from config
const unsigned long heartbeatInterval = HEARTBEAT_INTERVAL;
const unsigned long statusInterval = STATUS_INTERVAL;
const unsigned long commandInterval = COMMAND_INTERVAL;

// Function declarations
void sendLog(String type, String message, String level, bool stateChanged);

void setup() {
  Serial.begin(115200);
  
  pinMode(LED_PIN, OUTPUT);
  pinMode(LOCK_PIN, OUTPUT);
  digitalWrite(LED_PIN, HIGH); // LED OFF initially
  digitalWrite(LOCK_PIN, HIGH); // Lock ENGAGED initially (locked)
  
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  
  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(LED_PIN, !digitalRead(LED_PIN));
    delay(500);
    Serial.print(".");
  }
  
  wifiConnected = true;
  digitalWrite(LED_PIN, LOW); // LED ON when connected
  Serial.println();
  Serial.println("WiFi connected!");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
  
  // Force sync with database on startup
  Serial.println("Force syncing with database...");
  forceSyncWithDatabase();
  
  Serial.println("ESP32 Smart Lock initialized!");
  Serial.println("Using ngrok tunnel: " + String(serverURL));
  Serial.println("API-based system - polling for commands every 1 second");
}

void loop() {
  if (millis() - lastHeartbeat > heartbeatInterval) {
    sendHeartbeat();
    lastHeartbeat = millis();
  }
  
  if (millis() - lastStatusUpdate > statusInterval) {
    sendStatusUpdate();
    lastStatusUpdate = millis();
  }
  
  if (millis() - lastCommandCheck > commandInterval) {
    checkForCommands();
    lastCommandCheck = millis();
  }
  
  updateStatusLED();
  handleSerialCommands();
  
  delay(100);
}

void sendHeartbeat() {
  WiFiClientSecure client;
  client.setInsecure(); // Skip SSL verification for ngrok
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/heartbeat");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    backendConnected = true;
    String response = http.getString();
    
    // Check if forced sync is requested
    if (response.indexOf("\"force_sync\":true") > -1) {
      Serial.println("FORCED SYNC requested in heartbeat!");
      String syncCommandId = extractSyncCommandId(response);
      sendLog("HEARTBEAT", "Forced sync requested via heartbeat", "info", false);
      forceSyncWithDatabase();
      if (syncCommandId.length() > 0) {
        confirmCommand(syncCommandId, "completed");
      }
    }
    
    // Check if device was auto-registered
    if (response.indexOf("registered") > -1) {
      Serial.println("Device auto-registered successfully!");
    } else {
      Serial.println("Heartbeat: " + response);
    }
    
    digitalWrite(LED_PIN, HIGH);
    delay(100);
    digitalWrite(LED_PIN, LOW);
  } else {
    backendConnected = false;
    Serial.println("Heartbeat failed: " + String(httpCode));
  }
  
  http.end();
}

void sendStatusUpdate() {
  WiFiClientSecure client;
  client.setInsecure(); // Skip SSL verification for ngrok
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/status");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\",\"is_locked\":" + (isLocked ? "true" : "false") + "}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    Serial.println("Status updated");
  } else {
    Serial.println("Status update failed: " + String(httpCode));
  }
  
  http.end();
}

void checkForCommands() {
  WiFiClientSecure client;
  client.setInsecure(); // Skip SSL verification for ngrok
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/command");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    String response = http.getString();
    Serial.println("Command response: " + response);
    
    if (response.indexOf("unlock") > -1 && response.indexOf("command") > -1) {
      String commandId = extractCommandId(response);
      Serial.println("UNLOCK command received!");
      sendLog("COMMAND", "Unlock command received (ID: " + commandId + ")", "info", false);
      unlockDoor();
      confirmCommand(commandId, "completed");
    }
    else if (response.indexOf("lock") > -1 && response.indexOf("command") > -1 && response.indexOf("unlock") == -1) {
      String commandId = extractCommandId(response);
      Serial.println("LOCK command received!");
      sendLog("COMMAND", "Lock command received (ID: " + commandId + ")", "info", false);
      lockDoor();
      confirmCommand(commandId, "completed");
    }
    else if (response.indexOf("status") > -1 && response.indexOf("command") > -1) {
      String commandId = extractCommandId(response);
      Serial.println("STATUS command received!");
      sendLog("COMMAND", "Status command received (ID: " + commandId + ")", "info", false);
      sendStatusUpdate();
      confirmCommand(commandId, "completed");
    }
    else if (response.indexOf("sync") > -1 && response.indexOf("command") > -1) {
      String commandId = extractCommandId(response);
      Serial.println("FORCED SYNC command received!");
      sendLog("COMMAND", "Forced sync command received (ID: " + commandId + ")", "info", false);
      forceSyncWithDatabase();
      confirmCommand(commandId, "completed");
    }
  } else if (httpCode != 200) {
    Serial.println("Command check failed: " + String(httpCode));
  }
  
  http.end();
}

String extractCommandId(String response) {
  int start = response.indexOf("\"command_id\":") + 13;
  int end = response.indexOf(",", start);
  if (end == -1) end = response.indexOf("}", start);
  return response.substring(start, end);
}

String extractSyncCommandId(String response) {
  int start = response.indexOf("\"sync_command_id\":") + 18;
  int end = response.indexOf(",", start);
  if (end == -1) end = response.indexOf("}", start);
  return response.substring(start, end);
}

void confirmCommand(String commandId, String status) {
  if (commandId.length() == 0) return;
  
  WiFiClientSecure client;
  client.setInsecure(); // Skip SSL verification for ngrok
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/confirm");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"command_id\":" + commandId + ",\"status\":\"" + status + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    Serial.println("Command confirmed: " + commandId);
  }
  
  http.end();
}

void lockDoor() {
  digitalWrite(LOCK_PIN, HIGH); // Engage lock (HIGH = locked)
  isLocked = true;
  Serial.println("Door LOCKED");
  
  for(int i = 0; i < 3; i++) {
    digitalWrite(LED_PIN, HIGH);
    delay(100);
    digitalWrite(LED_PIN, LOW);
    delay(100);
  }
  
  sendStatusUpdate();
}

void unlockDoor() {
  digitalWrite(LOCK_PIN, LOW); // Disengage lock (LOW = unlocked)
  isLocked = false;
  Serial.println("Door UNLOCKED");
  
  digitalWrite(LED_PIN, HIGH);
  delay(500);
  digitalWrite(LED_PIN, LOW);
  
  sendStatusUpdate();
}

void updateStatusLED() {
  if (millis() - lastLEDUpdate > 2000) {
    if (wifiConnected && backendConnected) {
      digitalWrite(LED_PIN, LOW); // LED ON = all good
    } else {
      digitalWrite(LED_PIN, !digitalRead(LED_PIN)); // Blink = issues
    }
    lastLEDUpdate = millis();
  }
}

void handleSerialCommands() {
  if (Serial.available()) {
    String command = Serial.readString();
    command.trim();
    
    if (command == "lock") {
      lockDoor();
    } else if (command == "unlock") {
      unlockDoor();
    } else if (command == "status") {
      printStatus();
    } else if (command == "test") {
      runFullTest();
    } else if (command == "sync") {
      forceSyncWithDatabase();
    }
  }
}

void printStatus() {
  Serial.println("\n=== Device Status ===");
  Serial.println("Hardware ID: " + String(hardwareId));
  Serial.println("WiFi: " + String(wifiConnected ? "Connected" : "Disconnected"));
  Serial.println("Backend: " + String(backendConnected ? "Connected" : "Disconnected"));
  Serial.println("IP Address: " + WiFi.localIP().toString());
  Serial.println("Lock Status: " + String(isLocked ? "LOCKED" : "UNLOCKED"));
  Serial.println("Server URL: " + String(serverURL));
  Serial.println("====================");
}

void forceSyncWithDatabase() {
  Serial.println("FORCED sync with database initiated...");
  sendLog("FORCE_SYNC", "Forced synchronization started", "info", false);
  
  // Call the regular sync function
  syncWithDatabaseState();
  
  // Send confirmation log
  sendLog("FORCE_SYNC", "Forced synchronization completed - State: " + String(isLocked ? "LOCKED" : "UNLOCKED"), "info", false);
}

void syncWithDatabaseState() {
  Serial.println("Syncing with database state...");
  
  HTTPClient http;
  
  // Get current lock status from database
  http.begin(String(serverURL) + "/api/locks");
  http.addHeader("Authorization", "Bearer demo_token");
  
  int httpCode = http.GET();
  
  if (httpCode == 200) {
    String response = http.getString();
    Serial.println("Database response: " + response);
    
    // Parse response to find our lock's current state
    if (response.indexOf("\"hardware_id\":\"" + String(hardwareId) + "\"") > -1) {
      int lockStart = response.indexOf("\"hardware_id\":\"" + String(hardwareId) + "\"");
      int statusStart = response.indexOf("\"is_locked\":", lockStart);
      
      if (statusStart > -1) {
        int valueStart = statusStart + 12;
        String lockValue = response.substring(valueStart, valueStart + 5);
        
        if (lockValue.indexOf("true") > -1) {
          isLocked = true;
          Serial.println("Synced: Lock is LOCKED");
        } else if (lockValue.indexOf("false") > -1) {
          isLocked = false;
          Serial.println("Synced: Lock is UNLOCKED");
        }
        
        previousLockState = isLocked;
        stateSynced = true;
        
        sendLog("STATE_SYNC", "Hardware state synced with database: " + String(isLocked ? "LOCKED" : "UNLOCKED"), "info", false);
      }
    }
  } else {
    Serial.println("Failed to sync with database: " + String(httpCode));
    isLocked = true;
    previousLockState = true;
    stateSynced = false;
    
    sendLog("STATE_SYNC", "Failed to sync with database, using default LOCKED state", "warning", false);
  }
  
  http.end();
  updatePhysicalLockState();
}

void sendLog(String type, String message, String level, bool stateChanged) {
  HTTPClient http;
  
  http.begin(String(serverURL) + "/api/hardware/log");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{";
  payload += "\"hardware_id\":\"" + String(hardwareId) + "\",";
  payload += "\"type\":\"" + type + "\",";
  payload += "\"message\":\"" + message + "\",";
  payload += "\"level\":\"" + level + "\",";
  payload += "\"timestamp\":" + String(millis()) + ",";
  payload += "\"state_changed\":";
  payload += stateChanged ? "true" : "false";
  payload += ",";
  payload += "\"current_state\":\"" + String(isLocked ? "LOCKED" : "UNLOCKED") + "\"";
  payload += "}";
  
  int httpCode = http.POST(payload);
  
  if (httpCode != 200 && type != "LOG_ERROR") {
    Serial.println("Log send failed: " + String(httpCode));
  }
  
  http.end();
}

void updatePhysicalLockState() {
  // Update physical lock to match database state
  digitalWrite(LOCK_PIN, isLocked ? HIGH : LOW);
  Serial.println("Physical lock state updated: " + String(isLocked ? "LOCKED" : "UNLOCKED"));
}

void runFullTest() {
  Serial.println("\n=== Running Full System Test ===");
  sendHeartbeat();
  sendStatusUpdate();
  checkForCommands();
  
  Serial.println("\nAPI-based system working with ngrok!");
  Serial.println("Commands: 'status', 'lock', 'unlock', 'test', 'sync'");
  Serial.println("=====================================");
}
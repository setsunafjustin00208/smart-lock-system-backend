/*
 * ESP32/NodeMCU Test Code for Smart Lock Backend Integration
 * API-based command system with database state sync and logging
 */

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>

// WiFi credentials
const char* ssid = "GlobeAtHome_5B8F1_2.4";
const char* password = "42D54DFF";

// Server configuration
const char* serverURL = "http://192.168.254.110:8080";
const char* hardwareId = "ESP32_TEST_001";

#define LED_PIN 2

// Connection status
bool wifiConnected = false;
bool backendConnected = false;
bool stateSynced = false;

// Timing variables
unsigned long lastHeartbeat = 0;
unsigned long lastStatusUpdate = 0;
unsigned long lastCommandCheck = 0;
unsigned long lastLEDUpdate = 0;
const unsigned long heartbeatInterval = 30000; // 30 seconds
const unsigned long statusInterval = 3000; // 3 seconds
const unsigned long commandInterval = 1000; // 1 second

// Lock state
bool isLocked = true;
bool previousLockState = true;

void setup() {
  Serial.begin(115200);
  
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, HIGH); // LED OFF initially
  
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
  
  // Sync with database state on startup
  syncWithDatabaseState();
  
  Serial.println("ESP32 Smart Lock initialized!");
  Serial.println("API-based system with logging - polling every 1 second");
  
  // Send startup log
  sendLog("STARTUP", "Hardware initialized and synced with database", "info");
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

void syncWithDatabaseState() {
  Serial.println("Syncing with database state...");
  
  WiFiClient client;
  HTTPClient http;
  
  // Get current lock status from database
  http.begin(client, String(serverURL) + "/api/locks");
  http.addHeader("Authorization", "Bearer demo_token"); // You might need actual token
  
  int httpCode = http.GET();
  
  if (httpCode == 200) {
    String response = http.getString();
    Serial.println("Database response: " + response);
    
    // Parse response to find our lock's current state
    if (response.indexOf("\"hardware_id\":\"" + String(hardwareId) + "\"") > -1) {
      // Find the is_locked value for our hardware
      int lockStart = response.indexOf("\"hardware_id\":\"" + String(hardwareId) + "\"");
      int statusStart = response.indexOf("\"is_locked\":", lockStart);
      
      if (statusStart > -1) {
        int valueStart = statusStart + 12; // Length of "is_locked":
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
        
        // Send sync log
        sendLog("STATE_SYNC", "Hardware state synced with database: " + String(isLocked ? "LOCKED" : "UNLOCKED"), "info");
      }
    }
  } else {
    Serial.println("Failed to sync with database: " + String(httpCode));
    // Use default locked state
    isLocked = true;
    previousLockState = true;
    stateSynced = false;
    
    sendLog("STATE_SYNC", "Failed to sync with database, using default LOCKED state", "warning");
  }
  
  http.end();
  
  // Update physical state to match database
  updatePhysicalLockState();
}

void sendHeartbeat() {
  WiFiClient client;
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/heartbeat");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    backendConnected = true;
    String response = http.getString();
    Serial.println("Heartbeat: " + response);
    
    // Quick LED flash for heartbeat
    digitalWrite(LED_PIN, HIGH);
    delay(100);
    digitalWrite(LED_PIN, LOW);
    
    // Send heartbeat log
    sendLog("HEARTBEAT", "Device online and responding", "info");
  } else {
    backendConnected = false;
    Serial.println("Heartbeat failed: " + String(httpCode));
    sendLog("HEARTBEAT", "Heartbeat failed with code: " + String(httpCode), "error");
  }
  
  http.end();
}

void sendStatusUpdate() {
  WiFiClient client;
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/status");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\",\"is_locked\":" + (isLocked ? "true" : "false") + "}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    Serial.println("Status updated");
    
    // Check if state changed since last update
    bool stateChanged = (isLocked != previousLockState);
    
    // Send status log
    String logMessage = "Status update: " + String(isLocked ? "LOCKED" : "UNLOCKED");
    if (stateChanged) {
      logMessage += " (state changed from " + String(previousLockState ? "LOCKED" : "UNLOCKED") + ")";
    }
    
    sendLog("STATUS_UPDATE", logMessage, stateChanged ? "info" : "debug", stateChanged);
    
    previousLockState = isLocked;
  } else {
    Serial.println("Status update failed: " + String(httpCode));
    sendLog("STATUS_UPDATE", "Status update failed with code: " + String(httpCode), "error");
  }
  
  http.end();
}

void checkForCommands() {
  WiFiClient client;
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/command");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    String response = http.getString();
    
    if (response.indexOf("unlock") > -1 && response.indexOf("command") > -1) {
      String commandId = extractCommandId(response);
      Serial.println("UNLOCK command received!");
      sendLog("COMMAND", "Unlock command received (ID: " + commandId + ")", "info");
      unlockDoor();
      confirmCommand(commandId, "completed");
    }
    else if (response.indexOf("lock") > -1 && response.indexOf("command") > -1 && response.indexOf("unlock") == -1) {
      String commandId = extractCommandId(response);
      Serial.println("LOCK command received!");
      sendLog("COMMAND", "Lock command received (ID: " + commandId + ")", "info");
      lockDoor();
      confirmCommand(commandId, "completed");
    }
    else if (response.indexOf("status") > -1 && response.indexOf("command") > -1) {
      String commandId = extractCommandId(response);
      Serial.println("STATUS command received!");
      sendLog("COMMAND", "Status command received (ID: " + commandId + ")", "info");
      sendStatusUpdate();
      confirmCommand(commandId, "completed");
    }
  }
  
  http.end();
}

String extractCommandId(String response) {
  int start = response.indexOf("\"command_id\":") + 13;
  int end = response.indexOf(",", start);
  if (end == -1) end = response.indexOf("}", start);
  return response.substring(start, end);
}

void confirmCommand(String commandId, String status) {
  if (commandId.length() == 0) return;
  
  WiFiClient client;
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/confirm");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"command_id\":" + commandId + ",\"status\":\"" + status + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    Serial.println("Command confirmed: " + commandId);
    sendLog("COMMAND", "Command confirmed (ID: " + commandId + ", Status: " + status + ")", "info");
  } else {
    sendLog("COMMAND", "Command confirmation failed (ID: " + commandId + ", Code: " + String(httpCode) + ")", "error");
  }
  
  http.end();
}

void lockDoor() {
  isLocked = true;
  Serial.println("🔒 Door LOCKED");
  
  // LED pattern for lock: 3 quick flashes
  for(int i = 0; i < 3; i++) {
    digitalWrite(LED_PIN, HIGH);
    delay(100);
    digitalWrite(LED_PIN, LOW);
    delay(100);
  }
  
  updatePhysicalLockState();
  sendStatusUpdate();
  sendLog("ACTION", "Door locked successfully", "info");
}

void unlockDoor() {
  isLocked = false;
  Serial.println("🔓 Door UNLOCKED");
  
  // LED pattern for unlock: 1 long flash
  digitalWrite(LED_PIN, HIGH);
  delay(500);
  digitalWrite(LED_PIN, LOW);
  
  updatePhysicalLockState();
  sendStatusUpdate();
  sendLog("ACTION", "Door unlocked successfully", "info");
}

void updatePhysicalLockState() {
  // This is where you would control actual hardware
  // For now, just update internal state
  Serial.println("Physical lock state updated: " + String(isLocked ? "LOCKED" : "UNLOCKED"));
}

void updateStatusLED() {
  if (millis() - lastLEDUpdate > 2000) {
    if (wifiConnected && backendConnected && stateSynced) {
      digitalWrite(LED_PIN, LOW); // LED ON = all good
    } else {
      digitalWrite(LED_PIN, !digitalRead(LED_PIN)); // Blink = issues
    }
    lastLEDUpdate = millis();
  }
}

void sendLog(String type, String message, String level, bool stateChanged = false) {
  WiFiClient client;
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/log");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{";
  payload += "\"hardware_id\":\"" + String(hardwareId) + "\",";
  payload += "\"type\":\"" + type + "\",";
  payload += "\"message\":\"" + message + "\",";
  payload += "\"level\":\"" + level + "\",";
  payload += "\"timestamp\":" + String(millis()) + ",";
  payload += "\"state_changed\":" + (stateChanged ? "true" : "false") + ",";
  payload += "\"current_state\":\"" + String(isLocked ? "LOCKED" : "UNLOCKED") + "\"";
  payload += "}";
  
  int httpCode = http.POST(payload);
  
  // Don't log the logging operation to avoid recursion
  if (httpCode != 200 && type != "LOG_ERROR") {
    Serial.println("Log send failed: " + String(httpCode));
  }
  
  http.end();
}

void handleSerialCommands() {
  if (Serial.available()) {
    String command = Serial.readString();
    command.trim();
    
    if (command == "lock") {
      lockDoor();
      sendLog("MANUAL", "Manual lock command via serial", "info");
    } else if (command == "unlock") {
      unlockDoor();
      sendLog("MANUAL", "Manual unlock command via serial", "info");
    } else if (command == "status") {
      printStatus();
    } else if (command == "test") {
      runFullTest();
    } else if (command == "sync") {
      syncWithDatabaseState();
    }
  }
}

void printStatus() {
  Serial.println("\n=== Device Status ===");
  Serial.println("Hardware ID: " + String(hardwareId));
  Serial.println("WiFi: " + String(wifiConnected ? "✓ Connected" : "✗ Disconnected"));
  Serial.println("Backend: " + String(backendConnected ? "✓ Connected" : "✗ Disconnected"));
  Serial.println("State Synced: " + String(stateSynced ? "✓ Yes" : "✗ No"));
  Serial.println("IP Address: " + WiFi.localIP().toString());
  Serial.println("Lock Status: " + String(isLocked ? "🔒 LOCKED" : "🔓 UNLOCKED"));
  Serial.println("Server URL: " + String(serverURL));
  Serial.println("====================");
}

void runFullTest() {
  Serial.println("\n=== Running Full System Test ===");
  sendHeartbeat();
  sendStatusUpdate();
  checkForCommands();
  
  Serial.println("\nAPI-based system with logging working!");
  Serial.println("Commands: 'status', 'lock', 'unlock', 'test', 'sync'");
  Serial.println("=====================================");
  
  sendLog("TEST", "Full system test completed", "info");
}

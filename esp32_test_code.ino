/*
 * ESP32/NodeMCU Test Code for Smart Lock Backend Integration
 * API-based command system with detailed serial monitoring
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

// Timing variables
unsigned long lastHeartbeat = 0;
unsigned long lastStatusUpdate = 0;
unsigned long lastCommandCheck = 0;
unsigned long lastLEDUpdate = 0;
unsigned long lastReport = 0;
const unsigned long heartbeatInterval = 30000; // 30 seconds
const unsigned long statusInterval = 3000; // 3 seconds
const unsigned long commandInterval = 1000; // 1 second
const unsigned long reportInterval = 30000; // 30 seconds

// Lock state and statistics
bool isLocked = true;
unsigned long commandsReceived = 0;
unsigned long commandsExecuted = 0;
unsigned long heartbeatsSent = 0;
unsigned long statusUpdatesSent = 0;
String lastCommandReceived = "none";
unsigned long lastCommandTime = 0;

void setup() {
  Serial.begin(115200);
  
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, HIGH); // LED OFF initially
  
  Serial.println("\n=== Smart Lock System Started ===");
  Serial.println("Hardware ID: " + String(hardwareId));
  Serial.println("Server: " + String(serverURL));
  Serial.println("API Polling Mode: Commands every 1s, Status every 3s");
  Serial.println("=====================================");
  
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
  Serial.println("✓ WiFi Connected!");
  Serial.println("IP Address: " + WiFi.localIP().toString());
  Serial.println("Signal Strength: " + String(WiFi.RSSI()) + " dBm");
  Serial.println("Free Memory: " + String(ESP.getFreeHeap()) + " bytes");
  Serial.println("=====================================");
  
  Serial.println("Smart Lock initialized - Starting API polling...");
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
  
  if (millis() - lastReport > reportInterval) {
    printDetailedStatus();
    lastReport = millis();
  }
  
  updateStatusLED();
  handleSerialCommands();
  
  delay(100);
}

void sendHeartbeat() {
  Serial.print("[HEARTBEAT] Sending... ");
  
  WiFiClient client;
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/heartbeat");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    backendConnected = true;
    heartbeatsSent++;
    String response = http.getString();
    Serial.println("✓ SUCCESS - " + response);
    
    // Quick LED flash for successful heartbeat
    digitalWrite(LED_PIN, HIGH);
    delay(100);
    digitalWrite(LED_PIN, LOW);
  } else {
    backendConnected = false;
    Serial.println("✗ FAILED - Code: " + String(httpCode));
  }
  
  http.end();
}

void sendStatusUpdate() {
  Serial.print("[STATUS] Updating lock state... ");
  
  WiFiClient client;
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/status");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\",\"is_locked\":" + (isLocked ? "true" : "false") + "}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    statusUpdatesSent++;
    Serial.println("✓ SUCCESS - State: " + String(isLocked ? "LOCKED" : "UNLOCKED"));
  } else {
    Serial.println("✗ FAILED - Code: " + String(httpCode));
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
    
    if (response.indexOf("\"command\":\"none\"") > -1) {
      // No commands - silent
      return;
    }
    
    Serial.println("[COMMAND] Received: " + response);
    commandsReceived++;
    
    if (response.indexOf("unlock") > -1 && response.indexOf("command") > -1) {
      String commandId = extractCommandId(response);
      Serial.println("[EXECUTE] UNLOCK command - ID: " + commandId);
      lastCommandReceived = "unlock";
      lastCommandTime = millis();
      unlockDoor();
      confirmCommand(commandId, "completed");
      commandsExecuted++;
    }
    else if (response.indexOf("lock") > -1 && response.indexOf("command") > -1 && response.indexOf("unlock") == -1) {
      String commandId = extractCommandId(response);
      Serial.println("[EXECUTE] LOCK command - ID: " + commandId);
      lastCommandReceived = "lock";
      lastCommandTime = millis();
      lockDoor();
      confirmCommand(commandId, "completed");
      commandsExecuted++;
    }
    else if (response.indexOf("status") > -1 && response.indexOf("command") > -1) {
      String commandId = extractCommandId(response);
      Serial.println("[EXECUTE] STATUS command - ID: " + commandId);
      lastCommandReceived = "status";
      lastCommandTime = millis();
      sendStatusUpdate();
      confirmCommand(commandId, "completed");
      commandsExecuted++;
    }
  } else if (httpCode != 200) {
    Serial.println("[COMMAND] Poll failed - Code: " + String(httpCode));
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
  
  Serial.print("[CONFIRM] Command " + commandId + "... ");
  
  WiFiClient client;
  HTTPClient http;
  
  http.begin(client, String(serverURL) + "/api/hardware/confirm");
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"command_id\":" + commandId + ",\"status\":\"" + status + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    Serial.println("✓ CONFIRMED");
  } else {
    Serial.println("✗ FAILED - Code: " + String(httpCode));
  }
  
  http.end();
}

void lockDoor() {
  isLocked = true;
  Serial.println("🔒 DOOR LOCKED - Physical action simulated");
  
  // LED pattern for lock: 3 quick flashes
  for(int i = 0; i < 3; i++) {
    digitalWrite(LED_PIN, HIGH);
    delay(100);
    digitalWrite(LED_PIN, LOW);
    delay(100);
  }
  
  sendStatusUpdate();
}

void unlockDoor() {
  isLocked = false;
  Serial.println("🔓 DOOR UNLOCKED - Physical action simulated");
  
  // LED pattern for unlock: 1 long flash
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
    
    Serial.println("\n[USER CMD] " + command);
    
    if (command == "lock") {
      lockDoor();
    } else if (command == "unlock") {
      unlockDoor();
    } else if (command == "status") {
      printStatus();
    } else if (command == "test") {
      runFullTest();
    } else if (command == "stats") {
      printStats();
    } else if (command == "reset") {
      resetStats();
    } else {
      Serial.println("Commands: lock, unlock, status, test, stats, reset");
    }
  }
}

void printStatus() {
  Serial.println("\n=== CURRENT STATUS ===");
  Serial.println("Hardware ID: " + String(hardwareId));
  Serial.println("WiFi: " + String(wifiConnected ? "✓ Connected" : "✗ Disconnected"));
  Serial.println("Backend: " + String(backendConnected ? "✓ Connected" : "✗ Disconnected"));
  Serial.println("IP Address: " + WiFi.localIP().toString());
  Serial.println("Signal: " + String(WiFi.RSSI()) + " dBm");
  Serial.println("Lock Status: " + String(isLocked ? "🔒 LOCKED" : "🔓 UNLOCKED"));
  Serial.println("Server URL: " + String(serverURL));
  Serial.println("Uptime: " + String(millis()/1000) + " seconds");
  Serial.println("======================");
}

void printStats() {
  Serial.println("\n=== STATISTICS ===");
  Serial.println("Heartbeats Sent: " + String(heartbeatsSent));
  Serial.println("Status Updates: " + String(statusUpdatesSent));
  Serial.println("Commands Received: " + String(commandsReceived));
  Serial.println("Commands Executed: " + String(commandsExecuted));
  Serial.println("Last Command: " + lastCommandReceived);
  if (lastCommandTime > 0) {
    Serial.println("Last Command Time: " + String((millis() - lastCommandTime)/1000) + "s ago");
  }
  Serial.println("Free Memory: " + String(ESP.getFreeHeap()) + " bytes");
  Serial.println("==================");
}

void resetStats() {
  commandsReceived = 0;
  commandsExecuted = 0;
  heartbeatsSent = 0;
  statusUpdatesSent = 0;
  lastCommandReceived = "none";
  lastCommandTime = 0;
  Serial.println("[RESET] Statistics cleared");
}

void printDetailedStatus() {
  Serial.println("\n=== SYSTEM STATUS REPORT ===");
  Serial.println("Uptime: " + String(millis()/1000) + "s");
  Serial.println("WiFi Signal: " + String(WiFi.RSSI()) + " dBm");
  Serial.println("Free Memory: " + String(ESP.getFreeHeap()) + " bytes");
  Serial.println("Lock State: " + String(isLocked ? "🔒 LOCKED" : "🔓 UNLOCKED"));
  Serial.println("Backend: " + String(backendConnected ? "✓ ONLINE" : "✗ OFFLINE"));
  Serial.println("Commands: " + String(commandsReceived) + " received, " + String(commandsExecuted) + " executed");
  Serial.println("Last Activity: " + String((millis() - lastCommandCheck)/1000) + "s ago");
  Serial.println("============================");
}

void runFullTest() {
  Serial.println("\n=== RUNNING FULL SYSTEM TEST ===");
  Serial.println("Testing heartbeat...");
  sendHeartbeat();
  delay(1000);
  
  Serial.println("Testing status update...");
  sendStatusUpdate();
  delay(1000);
  
  Serial.println("Testing command polling...");
  checkForCommands();
  delay(1000);
  
  printStats();
  
  Serial.println("\n✓ Test complete!");
  Serial.println("Commands: lock, unlock, status, test, stats, reset");
  Serial.println("API System: Commands every 1s, Status every 3s");
  Serial.println("=====================================");
}

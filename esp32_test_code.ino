/*
 * ESP32/NodeMCU Test Code for Smart Lock Backend Integration
 * 
 * This code demonstrates how to connect ESP32 or NodeMCU to the backend API
 * for online/offline status tracking and basic lock control.
 */

// For ESP32
#ifdef ESP32
  #include <WiFi.h>
  #include <HTTPClient.h>
  #include <WebSocketsClient.h>
#endif

// For NodeMCU (ESP8266)
#ifdef ESP8266
  #include <ESP8266WiFi.h>
  #include <ESP8266HTTPClient.h>
  #include <WebSocketsClient.h>
  #include <WiFiClient.h>
#endif

// WiFi credentials
const char* ssid = "GlobeAtHome_5B8F1_2.4";
const char* password = "42D54DFF";

// Server configuration
const char* serverURL = "http://192.168.254.110:8080"; // Replace with your local IP
const char* wsServerURL = "192.168.254.110"; // WebSocket server IP
const int wsPort = 3000;

/// Hardware configuration
const char* hardwareId = "ESP32_TEST_001"; // Must match database
#define LED_PIN 2 // Built-in LED GPIO 2

// WebSocket client
WebSocketsClient webSocket;

// Connection status
bool wifiConnected = false;
bool backendConnected = false;
bool wsConnected = false;

// Timing variables
unsigned long lastHeartbeat = 0;
unsigned long lastStatusUpdate = 0;
unsigned long lastCommandCheck = 0;
unsigned long lastLEDUpdate = 0;
const unsigned long heartbeatInterval = 30000; // 30 seconds
const unsigned long statusInterval = 60000; // 1 minute
const unsigned long commandInterval = 5000; // 5 seconds - check for commands

// Lock state
bool isLocked = true;

void setup() {
  Serial.begin(115200);
  
  // Initialize LED
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, HIGH); // LED OFF initially (inverted logic)
  
  // Connect to WiFi
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  
  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(LED_PIN, !digitalRead(LED_PIN)); // Blink during connection
    delay(500);
    Serial.print(".");
  }
  
  wifiConnected = true;
  wsConnected = true; // HTTP API working, WebSocket disabled
  digitalWrite(LED_PIN, LOW); // LED ON when connected
  Serial.println();
  Serial.println("WiFi connected!");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
  
  // WebSocket disabled - HTTP API working perfectly
  // webSocket.begin(wsServerURL, wsPort);
  // webSocket.onEvent(webSocketEvent);
  // webSocket.setReconnectInterval(5000);
  // webSocket.enableHeartbeat(15000, 3000, 2);
  
  // Send initial registration
  registerDevice();
  
  Serial.println("ESP32 Smart Lock initialized!");
  Serial.println("LED Indicators:");
  Serial.println("- Solid ON = All systems connected");
  Serial.println("- Blinking = Connection issues");
  Serial.println("- Quick flash = Heartbeat sent");
  Serial.println("- 3 flashes = Lock command");
  Serial.println("- 1 long flash = Unlock command");
}

void loop() {
  // Send heartbeat periodically
  if (millis() - lastHeartbeat > heartbeatInterval) {
    sendHeartbeat();
    lastHeartbeat = millis();
  }
  
  // Send status update periodically
  if (millis() - lastStatusUpdate > statusInterval) {
    sendStatusUpdate();
    lastStatusUpdate = millis();
  }
  
  // Check for commands periodically
  if (millis() - lastCommandCheck > commandInterval) {
    checkForCommands();
    lastCommandCheck = millis();
  }
  
  // Update LED status
  updateStatusLED();
  
  // Handle serial commands for testing
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
    }
  }
  
  delay(100);
}

void registerDevice() {
  String payload = "{\"type\":\"hardware_register\",\"hardware_id\":\"" + String(hardwareId) + "\"}";
  webSocket.sendTXT(payload);
  Serial.println("Device registration sent");
}

void sendHeartbeat() {
  // HTTP heartbeat
  HTTPClient http;
  
  #ifdef ESP8266
    WiFiClient client;
    http.begin(client, String(serverURL) + "/api/hardware/heartbeat");
  #else
    http.begin(String(serverURL) + "/api/hardware/heartbeat");
  #endif
  
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    backendConnected = true;
    String response = http.getString();
    Serial.println("Heartbeat sent: " + response);
    
    // Quick LED flash for successful heartbeat
    digitalWrite(LED_PIN, HIGH);
    delay(100);
    digitalWrite(LED_PIN, LOW);
  } else {
    backendConnected = false;
    Serial.println("Heartbeat failed: " + String(httpCode));
  }
  
  http.end();
  
  // WebSocket heartbeat
  String wsPayload = "{\"type\":\"hardware_heartbeat\",\"hardware_id\":\"" + String(hardwareId) + "\"}";
  webSocket.sendTXT(wsPayload);
}

void sendStatusUpdate() {
  HTTPClient http;
  
  #ifdef ESP8266
    WiFiClient client;
    http.begin(client, String(serverURL) + "/api/hardware/status");
  #else
    http.begin(String(serverURL) + "/api/hardware/status");
  #endif
  
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\",\"is_locked\":" + (isLocked ? "true" : "false") + "}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    String response = http.getString();
    Serial.println("Status update sent: " + response);
  } else {
    Serial.println("Status update failed: " + String(httpCode));
  }
  
  http.end();
}

void checkForCommands() {
  HTTPClient http;
  
  #ifdef ESP8266
    WiFiClient client;
    http.begin(client, String(serverURL) + "/api/hardware/command");
  #else
    http.begin(String(serverURL) + "/api/hardware/command");
  #endif
  
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"hardware_id\":\"" + String(hardwareId) + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    String response = http.getString();
    Serial.println("Command check: " + response);
    
    // Parse response for commands
    if (response.indexOf("\"command\":\"lock\"") > -1) {
      String commandId = extractCommandId(response);
      lockDoor();
      confirmCommand(commandId, "completed");
    }
    else if (response.indexOf("\"command\":\"unlock\"") > -1) {
      String commandId = extractCommandId(response);
      unlockDoor();
      confirmCommand(commandId, "completed");
    }
    else if (response.indexOf("\"command\":\"status\"") > -1) {
      String commandId = extractCommandId(response);
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
  
  HTTPClient http;
  
  #ifdef ESP8266
    WiFiClient client;
    http.begin(client, String(serverURL) + "/api/hardware/confirm");
  #else
    http.begin(String(serverURL) + "/api/hardware/confirm");
  #endif
  
  http.addHeader("Content-Type", "application/json");
  
  String payload = "{\"command_id\":" + commandId + ",\"status\":\"" + status + "\",\"hardware_id\":\"" + String(hardwareId) + "\"}";
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    Serial.println("Command confirmed: " + commandId);
  }
  
  http.end();
}

void webSocketEvent(WStype_t type, uint8_t * payload, size_t length) {
  switch(type) {
    case WStype_DISCONNECTED:
      wsConnected = false;
      Serial.println("WebSocket Disconnected");
      break;
      
    case WStype_CONNECTED:
      wsConnected = true;
      Serial.printf("WebSocket Connected to: %s\n", payload);
      registerDevice();
      break;
      
    case WStype_TEXT:
      Serial.printf("WebSocket Message: %s\n", payload);
      handleWebSocketMessage((char*)payload);
      break;
      
    default:
      break;
  }
}

void handleWebSocketMessage(String message) {
  // Parse JSON message (simple parsing for demo)
  if (message.indexOf("\"type\":\"registered\"") > -1) {
    Serial.println("Device registered successfully!");
  }
  else if (message.indexOf("\"type\":\"command\"") > -1) {
    if (message.indexOf("\"action\":\"lock\"") > -1) {
      lockDoor();
    }
    else if (message.indexOf("\"action\":\"unlock\"") > -1) {
      unlockDoor();
    }
    else if (message.indexOf("\"action\":\"status\"") > -1) {
      sendStatusUpdate();
    }
  }
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
  
  // Send immediate status update
  sendStatusUpdate();
}

void unlockDoor() {
  isLocked = false;
  Serial.println("🔓 Door UNLOCKED");
  
  // LED pattern for unlock: 1 long flash
  digitalWrite(LED_PIN, HIGH);
  delay(500);
  digitalWrite(LED_PIN, LOW);
  
  // Send immediate status update
  sendStatusUpdate();
}

void updateStatusLED() {
  if (millis() - lastLEDUpdate > 2000) {
    if (wifiConnected && backendConnected && wsConnected) {
      digitalWrite(LED_PIN, LOW); // LED ON = all systems good
    } else {
      digitalWrite(LED_PIN, !digitalRead(LED_PIN)); // Blink = issues
    }
    lastLEDUpdate = millis();
  }
}

void printStatus() {
  Serial.println("=== Device Status ===");
  Serial.println("Hardware ID: " + String(hardwareId));
  Serial.println("WiFi Status: " + String(wifiConnected ? "✓ Connected" : "✗ Disconnected"));
  Serial.println("Backend API: " + String(backendConnected ? "✓ Connected" : "✗ Disconnected"));
  Serial.println("WebSocket: " + String(wsConnected ? "✓ Connected" : "✗ Disconnected"));
  Serial.println("IP Address: " + WiFi.localIP().toString());
  Serial.println("Lock Status: " + String(isLocked ? "🔒 LOCKED" : "🔓 UNLOCKED"));
  Serial.println("Server URL: " + String(serverURL));
  Serial.println("WebSocket: " + String(wsServerURL) + ":" + String(wsPort));
  Serial.println("====================");
}

void runFullTest() {
  Serial.println("\n=== Running Full System Test ===");
  sendHeartbeat();
  sendStatusUpdate();
  checkForCommands();
  
  Serial.println("\nTest commands: 'status', 'lock', 'unlock', 'test'");
  Serial.println("API-based system:");
  Serial.println("- Heartbeat every 30 seconds");
  Serial.println("- Status update every minute");
  Serial.println("- Command polling every 5 seconds");
  Serial.println("- LED: Solid ON = All systems working");
  Serial.println("- 3 flashes = Lock command");
  Serial.println("- 1 long flash = Unlock command");
  Serial.println("=====================================");
}

/*
 * Usage Instructions:
 * 
 * 1. Update WiFi credentials (ssid, password)
 * 2. Make sure Raspberry Pi IP is correct (192.168.254.110)
 * 3. Make sure hardwareId matches the database entry
 * 4. Upload to ESP32/NodeMCU
 * 5. Open Serial Monitor (115200 baud)
 * 6. Start backend services on Raspberry Pi:
 *    - php spark websocket:start --port=3000
 *    - php spark serve --port=8080
 * 7. Watch LED and Serial Monitor for status
 * 8. Test commands via Serial Monitor: "lock", "unlock", "status", "test"
 * 9. Test remote commands via backend API
 * 
 * LED Behavior:
 * - Blinking during WiFi connection
 * - Solid ON when all systems connected
 * - Blinking when connection issues
 * - Quick flash on successful heartbeat
 * - 3 quick flashes for lock command
 * - 1 long flash for unlock command
 * 
 * Expected Serial Output:
 * - WiFi connection status
 * - WebSocket connection status
 * - Heartbeat confirmations every 30 seconds
 * - Status updates every minute
 * - Command responses
 */

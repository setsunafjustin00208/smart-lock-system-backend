/*
 * Smart Lock System Credentials Configuration
 * 
 * IMPORTANT: 
 * - Update these values for your specific setup
 * - Keep this file secure and don't commit to public repositories
 * - Copy this file to your Arduino sketch folder
 */

#ifndef CREDENTIALS_H
#define CREDENTIALS_H

// WiFi Configuration
#define WIFI_SSID "GlobeAtHome_5B8F1_2.4"
#define WIFI_PASSWORD "42D54DFF"

// Server Configuration
// --- #define SERVER_URL "http://192.168.254.175:8080" --// Local server example
// --- #define NGROK_URL "https://lockey.ngrok.io" --// Ngrok tunnel example
#define SERVER_URL "https://lockey.ngrok.io"  // Update with

// Hardware Configuration
#define ESP32_HARDWARE_ID "ESP32_TEST_001"
#define NODEMCU_HARDWARE_ID "NODEMCU_TEST_001"

// Pin Configuration
#define LED_PIN 2
#define LOCK_PIN 4

// Timing Configuration (milliseconds)
#define HEARTBEAT_INTERVAL 30000  // 30 seconds
#define STATUS_INTERVAL 3000      // 3 seconds  
#define COMMAND_INTERVAL 1000     // 1 second

// Network Configuration
#define HTTP_TIMEOUT 10000        // 10 seconds
#define WIFI_TIMEOUT 30000        // 30 seconds

#endif // CREDENTIALS_H

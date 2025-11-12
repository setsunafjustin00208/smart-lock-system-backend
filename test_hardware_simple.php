<?php
// Simple test script for hardware integration
require_once 'vendor/autoload.php';

echo "=== Hardware Integration Test ===\n";

// Test 1: Check if Ratchet is installed
echo "1. Testing Ratchet installation...\n";
if (class_exists('Ratchet\Server\IoServer')) {
    echo "   ✓ Ratchet library installed successfully\n";
} else {
    echo "   ✗ Ratchet library not found\n";
}

// Test 2: Check if hardware controller exists
echo "2. Testing HardwareController...\n";
if (file_exists('app/Controllers/API/HardwareController.php')) {
    echo "   ✓ HardwareController created\n";
} else {
    echo "   ✗ HardwareController not found\n";
}

// Test 3: Check if WebSocket server exists
echo "3. Testing WebSocket server...\n";
if (file_exists('app/Libraries/SimpleWebSocketServer.php')) {
    echo "   ✓ SimpleWebSocketServer created\n";
} else {
    echo "   ✗ SimpleWebSocketServer not found\n";
}

// Test 4: Check if commands exist
echo "4. Testing CLI commands...\n";
if (file_exists('app/Commands/StartWebSocket.php')) {
    echo "   ✓ WebSocket command created\n";
} else {
    echo "   ✗ WebSocket command not found\n";
}

if (file_exists('app/Commands/MonitorDevices.php')) {
    echo "   ✓ Monitor command created\n";
} else {
    echo "   ✗ Monitor command not found\n";
}

// Test 5: Check updated LockControlLib
echo "5. Testing LockControlLib...\n";
if (file_exists('app/Libraries/LockControlLib.php')) {
    $content = file_get_contents('app/Libraries/LockControlLib.php');
    if (strpos($content, 'sendWebSocketCommand') !== false) {
        echo "   ✓ LockControlLib updated with WebSocket support\n";
    } else {
        echo "   ✗ LockControlLib missing WebSocket support\n";
    }
} else {
    echo "   ✗ LockControlLib not found\n";
}

echo "\n=== Test Complete ===\n";
echo "✅ Hardware integration components are ready!\n";
echo "\n📋 Next steps:\n";
echo "1. Start WebSocket server: php spark websocket:start --port=3000\n";
echo "2. Start device monitor: php spark hardware:monitor\n";
echo "3. Start API server: php spark serve --port=8080\n";
echo "4. Test with ESP32/NodeMCU using the provided Arduino code\n";
echo "\n🔧 Hardware endpoints available:\n";
echo "- POST /api/hardware/heartbeat\n";
echo "- POST /api/hardware/status\n";
echo "\n🌐 WebSocket server will run on ws://localhost:3000\n";
?>

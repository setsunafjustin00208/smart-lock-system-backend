# Raspberry Pi Tunnel Setup for Smart Lock System

## Overview
Since the Raspberry Pi acts as both the backend API server and the ESP32 lock controller, tunneling is essential to provide remote access while keeping the Pi local for hardware control.

## Working Solutions

### Option 1: ngrok (Recommended for stability)
```bash
# Install ngrok (one time)
wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-arm64.tgz
tar xvzf ngrok-v3-stable-linux-arm64.tgz
sudo mv ngrok /usr/local/bin/

# Start tunnel
ngrok http 8080
```

### Option 2: localtunnel (Good backup)
```bash
# Install (one time)
sudo apt install nodejs npm -y
sudo npm install -g localtunnel

# Start tunnel
lt --port 8080
```

### Option 3: Cloudflared (When working)
```bash
# Quick tunnel
cloudflared tunnel --url http://localhost:8080

# Named tunnel (more stable)
cloudflared tunnel login
cloudflared tunnel create smartlock-backend
```

## Startup Script

Create an automated startup script:

```bash
nano ~/start-smartlock.sh
```

```bash
#!/bin/bash
# Smart Lock System Startup Script

echo "Starting Smart Lock Backend..."

# Start backend
cd ~/Documents/lockey/backend
php spark serve --host=0.0.0.0 --port=8080 &
BACKEND_PID=$!

# Wait for backend to start
sleep 5

# Test backend
if curl -s http://localhost:8080/api/auth/login > /dev/null; then
    echo "✅ Backend started successfully"
else
    echo "❌ Backend failed to start"
    exit 1
fi

# Try tunnels in order of preference
echo "Starting tunnel..."

# Try ngrok first
if command -v ngrok &> /dev/null; then
    echo "Using ngrok tunnel..."
    ngrok http 8080
elif command -v lt &> /dev/null; then
    echo "Using localtunnel..."
    lt --port 8080
elif command -v cloudflared &> /dev/null; then
    echo "Using cloudflared..."
    cloudflared tunnel --url http://localhost:8080
else
    echo "❌ No tunnel service available"
    echo "Backend running on local port 8080"
    wait $BACKEND_PID
fi
```

```bash
chmod +x ~/start-smartlock.sh
```

## Usage

**Start everything:**
```bash
./start-smartlock.sh
```

**Test the system:**
```bash
# Test local backend
curl http://localhost:8080/api/auth/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Test tunnel (replace with your tunnel URL)
curl https://your-tunnel-url.com/api/auth/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

## Architecture Benefits

```
Internet → Tunnel Service → Raspberry Pi → ESP32 Locks
                          ↓
                      Local Network
                      (Direct ESP32 control)
```

**Advantages:**
- ✅ Remote API access via tunnel
- ✅ Local ESP32 control (no latency)
- ✅ Pi stays on local network
- ✅ No port forwarding needed
- ✅ Works behind NAT/firewall
- ✅ SSL/HTTPS automatically handled

## Tunnel URL Management

Since free tunnel URLs change, update your frontend configuration:

**Frontend config update:**
```javascript
// Use environment variable for tunnel URL
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8080/api'
```

**Or create a URL update script:**
```bash
nano ~/update-frontend-url.sh
```

```bash
#!/bin/bash
# Update frontend with new tunnel URL

if [ -z "$1" ]; then
    echo "Usage: ./update-frontend-url.sh <tunnel-url>"
    exit 1
fi

TUNNEL_URL=$1
FRONTEND_DIR="~/path/to/frontend"

# Update frontend config
sed -i "s|const API_BASE_URL = .*|const API_BASE_URL = '$TUNNEL_URL/api'|" $FRONTEND_DIR/src/config.js

echo "Frontend updated with tunnel URL: $TUNNEL_URL"
```

## Monitoring

**Check if services are running:**
```bash
# Check backend
curl -s http://localhost:8080/api/health || echo "Backend down"

# Check tunnel (replace with your URL)
curl -s https://your-tunnel-url.com/api/health || echo "Tunnel down"

# Check processes
ps aux | grep -E "(php|ngrok|cloudflared|lt)"
```

## Troubleshooting

**Backend not responding:**
```bash
pkill -f "php spark serve"
cd ~/Documents/lockey/backend
php spark serve --host=0.0.0.0 --port=8080
```

**Tunnel not working:**
```bash
# Try different tunnel service
pkill ngrok
pkill cloudflared
pkill -f "lt --port"

# Start alternative
lt --port 8080
```

**ESP32 connection issues:**
```bash
# Check local network connectivity
ping 192.168.254.175  # Pi IP
curl http://192.168.254.175:8080/api/locks/status
```

## Production Considerations

**For more stability:**
1. **Paid ngrok account** - stable URLs, custom domains
2. **Cloudflare named tunnels** - more reliable than quick tunnels  
3. **VPS with reverse proxy** - if you need guaranteed uptime
4. **Dynamic DNS** - if you can configure router port forwarding

**Current setup is perfect for:**
- Development and testing
- Home automation projects
- Small-scale deployments
- Learning and prototyping

The Pi + tunnel approach gives you the best of both worlds: local hardware control with remote API access!
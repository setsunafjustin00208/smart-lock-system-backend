# Smart Lock System - Troubleshooting & Deployment Guide

## Overview
This guide covers common issues and solutions encountered during deployment and operation of the Smart Lock backend system on Raspberry Pi with tunnel access.

## Fixed Issues

### 1. User Update Endpoint Error (500 Internal Server Error)

**Problem:** PUT `/api/users/{id}` returning 500 error
**Root Cause:** Method name typo in UsersController
**Solution:** Fixed `failValidationError()` to `failValidationErrors()`

**Files Modified:**
- `app/Controllers/API/UsersController.php` (lines 98, 118)

**Fix Applied:**
```php
// Before (causing 500 error)
return $this->failValidationError('No valid fields to update');

// After (working)
return $this->failValidationErrors(['error' => 'No valid fields to update']);
```

### 2. JWT Token Expiry Issues

**Problem:** "Invalid token" errors after 1 hour
**Root Cause:** JWT tokens expire after 1 hour by default
**Solutions:**

**Backend Configuration (`.env`):**
```bash
# Extend token expiry (optional)
JWT_EXPIRY = 3600          # 1 hour (default)
JWT_REFRESH_EXPIRY = 604800 # 7 days
```

**Frontend Token Management:**
```javascript
// Check token expiry before API calls
const isTokenExpired = (token) => {
  try {
    const payload = JSON.parse(atob(token.split('.')[1]));
    return Date.now() >= payload.exp * 1000;
  } catch {
    return true;
  }
};

// Auto-refresh or redirect to login
if (isTokenExpired(localStorage.getItem('token'))) {
  // Redirect to login or refresh token
  window.location.href = '/login';
}
```

### 3. Cloudflare Tunnel Connection Issues

**Problem:** Cloudflare tunnel connecting but returning 404/timeouts
**Root Cause:** Network routing and DNS resolution issues
**Solution:** Use ngrok or localtunnel as more reliable alternatives

**Working Solutions:**
```bash
# Option 1: ngrok (most reliable)
ngrok http 8080

# Option 2: localtunnel (good backup)
lt --port 8080

# Option 3: cloudflared (when working)
cloudflared tunnel --url http://localhost:8080
```

### 4. PHP Server Network Binding

**Problem:** Backend only accessible on localhost, not through tunnels
**Root Cause:** PHP server binding to IPv6 localhost only
**Solution:** Force IPv4 binding with proper host parameter

**Correct Startup:**
```bash
cd ~/Documents/lockey/backend
php spark serve --host=0.0.0.0 --port=8080
```

**Verification:**
```bash
# Should show 0.0.0.0:8080, not ::1:8080
sudo netstat -tlnp | grep 8080
```

## Current System Status

### ✅ Working Features
- **Authentication:** Login/logout with JWT tokens
- **Lock Management:** List, control, and monitor 6 ESP32 locks
- **User Management:** Create users (admin only)
- **Notifications:** Real-time polling system
- **Tunnel Access:** Remote API access via ngrok/localtunnel

### ⚠️ Known Issues
- **Token Refresh Endpoint:** Still has `failValidationError()` method issue
- **User Update:** Fixed but requires valid token
- **Tunnel Stability:** Free tunnels change URLs frequently

### ❌ Missing Features (From Bug Report)
- `PUT /api/auth/profile` - Update user profile
- `PUT /api/auth/password` - Change password  
- `GET/PUT /api/auth/notifications` - Notification settings

## Deployment Checklist

### Prerequisites
- [x] Raspberry Pi 5 with Raspberry Pi OS
- [x] PHP 8.3+ installed
- [x] PostgreSQL 13+ installed
- [x] Composer installed
- [x] Backend code deployed

### Backend Setup
```bash
# 1. Install dependencies
cd ~/Documents/lockey/backend
composer install

# 2. Configure environment
cp .env.example .env
# Edit .env with database credentials

# 3. Run migrations
php spark migrate
php spark db:seed UserSeeder
php spark db:seed LockSeeder
php spark db:seed NotificationSeeder

# 4. Start backend
php spark serve --host=0.0.0.0 --port=8080
```

### Tunnel Setup
```bash
# Option 1: ngrok (recommended)
wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-arm64.tgz
tar xvzf ngrok-v3-stable-linux-arm64.tgz
sudo mv ngrok /usr/local/bin/
ngrok http 8080

# Option 2: localtunnel
sudo apt install nodejs npm -y
sudo npm install -g localtunnel
lt --port 8080
```

### Testing Endpoints

**Authentication Test:**
```bash
curl -X POST https://your-tunnel-url.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

**Locks Test:**
```bash
curl -X GET https://your-tunnel-url.com/api/locks \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**User Management Test:**
```bash
# Create user
curl -X POST https://your-tunnel-url.com/api/users \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@example.com","password":"password123","roles":["user"]}'

# Update user (fixed)
curl -X PUT https://your-tunnel-url.com/api/users/2 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"email":"updated@example.com","roles":["user"]}'
```

## Common Error Solutions

### "Authorization token required"
**Cause:** Missing or invalid JWT token
**Solution:** 
1. Check if token is included in Authorization header
2. Verify token hasn't expired (1-hour limit)
3. Get fresh token via login endpoint

### "Invalid token"
**Cause:** Token expired or malformed
**Solution:**
1. Login again to get new token
2. Implement token refresh logic in frontend
3. Check token format: `Bearer eyJ0eXAiOiJKV1Q...`

### "500 Internal Server Error"
**Cause:** Backend code errors
**Solution:**
1. Check logs: `tail -20 ~/Documents/lockey/backend/writable/logs/log-*.log`
2. Restart PHP server after code changes
3. Verify database connection

### Tunnel Connection Issues
**Cause:** Network routing or DNS problems
**Solution:**
1. Try different tunnel service (ngrok → localtunnel → cloudflared)
2. Restart tunnel service
3. Check if backend is accessible locally first

### CORS Errors
**Cause:** Cross-origin request blocking
**Solution:**
1. Verify CORS settings in `.env`
2. Add tunnel domain to CORS_ALLOWED_ORIGINS
3. Check if OPTIONS requests are handled

## Production Recommendations

### Security
- [ ] Use strong JWT secrets (64+ characters)
- [ ] Enable HTTPS only in production
- [ ] Configure proper CORS origins
- [ ] Implement rate limiting
- [ ] Use paid tunnel service for stable URLs

### Performance
- [ ] Optimize PostgreSQL settings for Pi hardware
- [ ] Enable PHP OPcache
- [ ] Implement API response caching
- [ ] Monitor system resources

### Reliability
- [ ] Set up systemd services for auto-restart
- [ ] Implement health check endpoints
- [ ] Configure log rotation
- [ ] Set up monitoring and alerts

## System Architecture

```
Internet → Tunnel Service → Raspberry Pi → ESP32 Locks
                          ↓
                      Local Network
                      (Direct hardware control)
```

**Benefits:**
- Remote API access via secure tunnel
- Local ESP32 control (no latency)
- No port forwarding required
- Works behind NAT/firewall
- SSL/HTTPS automatically handled

## Startup Script

Create automated startup script:

```bash
#!/bin/bash
# ~/start-smartlock.sh

echo "Starting Smart Lock System..."

# Start backend
cd ~/Documents/lockey/backend
php spark serve --host=0.0.0.0 --port=8080 &
BACKEND_PID=$!

# Wait for backend
sleep 5

# Test backend
if curl -s http://localhost:8080/api/auth/login > /dev/null; then
    echo "✅ Backend started"
else
    echo "❌ Backend failed"
    exit 1
fi

# Start tunnel (try in order of preference)
if command -v ngrok &> /dev/null; then
    echo "Using ngrok..."
    ngrok http 8080
elif command -v lt &> /dev/null; then
    echo "Using localtunnel..."
    lt --port 8080
else
    echo "No tunnel service available"
    wait $BACKEND_PID
fi
```

```bash
chmod +x ~/start-smartlock.sh
./start-smartlock.sh
```

## Monitoring Commands

```bash
# Check backend status
curl -s http://localhost:8080/api/auth/login || echo "Backend down"

# Check processes
ps aux | grep -E "(php|ngrok|lt)"

# Check logs
tail -f ~/Documents/lockey/backend/writable/logs/log-$(date +%Y-%m-%d).log

# Check system resources
htop
df -h
```

## Support Information

**Test Credentials:**
- Admin: `admin` / `admin123`
- Manager: `manager` / `manager123`
- User: `user` / `user123`

**API Base URL:** `https://your-tunnel-url.com/api`

**Hardware:** 6 ESP32 smart locks with battery monitoring

**Database:** PostgreSQL with JSONB for flexible data storage

---

**Last Updated:** November 9, 2025  
**System Status:** Operational with tunnel access  
**Known Issues:** Token refresh endpoint needs fixing
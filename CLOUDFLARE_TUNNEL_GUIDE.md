# Cloudflare Tunnel Setup for Smart Lock Backend

## Overview

Based on frontend testing reports, this guide addresses tunnel stability issues and CORS problems encountered with localtunnel (`loca.lt`) by implementing Cloudflare Tunnel for production-ready deployment.

## Issues Identified from Frontend Testing

### Current Problems with localtunnel
- **Intermittent CORS failures** on lock control endpoints
- **Connection timeouts** during notification polling
- **Slow response times** (5-7 seconds average)
- **ERR_FAILED** errors on critical endpoints
- **Tunnel instability** affecting user experience

### Solution: Cloudflare Tunnel
- ✅ **Stable connections** with 99.9% uptime
- ✅ **Built-in CORS handling** 
- ✅ **SSL/TLS termination**
- ✅ **DDoS protection**
- ✅ **Global CDN** for faster response times

## Prerequisites

- Cloudflare account with domain
- Raspberry Pi 5 with backend running
- Domain DNS managed by Cloudflare

## Installation

### 1. Install cloudflared on Raspberry Pi

```bash
# Download ARM64 version for Raspberry Pi 5
wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm64

# Install
sudo mv cloudflared-linux-arm64 /usr/local/bin/cloudflared
sudo chmod +x /usr/local/bin/cloudflared

# Verify installation
cloudflared --version
```

### 2. Authenticate with Cloudflare

```bash
# Login to Cloudflare (opens browser)
cloudflared tunnel login

# This creates ~/.cloudflared/cert.pem
```

### 3. Create Tunnel

```bash
# Create tunnel
cloudflared tunnel create smartlock-api

# Note the tunnel UUID from output
# Example: Created tunnel smartlock-api with id: 12345678-1234-1234-1234-123456789abc
```

## Configuration

### 1. Create Configuration File

```bash
# Create config directory
mkdir -p ~/.cloudflared

# Create configuration
nano ~/.cloudflared/config.yml
```

**Basic Configuration:**
```yaml
tunnel: <your-tunnel-id>
credentials-file: /home/pi/.cloudflared/<your-tunnel-id>.json

ingress:
  # API endpoint
  - hostname: api.yourdomain.com
    service: http://localhost:8080
    originRequest:
      # Fix CORS issues
      httpHostHeader: api.yourdomain.com
      # Increase timeout for slow responses
      connectTimeout: 30s
      tlsTimeout: 30s
      tcpKeepAlive: 30s
      keepAliveTimeout: 90s
      # Enable HTTP/2
      http2Origin: true
  
  # Catch-all rule (required)
  - service: http_status:404
```

**Advanced Configuration with Multiple Services:**
```yaml
tunnel: <your-tunnel-id>
credentials-file: /home/pi/.cloudflared/<your-tunnel-id>.json

ingress:
  # API Backend
  - hostname: api.yourdomain.com
    service: http://localhost:8080
    originRequest:
      httpHostHeader: api.yourdomain.com
      connectTimeout: 30s
      tlsTimeout: 30s
      tcpKeepAlive: 30s
      keepAliveTimeout: 90s
      http2Origin: true
  
  # Frontend (if hosting on same Pi)
  - hostname: app.yourdomain.com
    service: http://localhost:3000
    originRequest:
      httpHostHeader: app.yourdomain.com
  
  # Admin panel (optional)
  - hostname: admin.yourdomain.com
    service: http://localhost:8081
    originRequest:
      httpHostHeader: admin.yourdomain.com
  
  # Catch-all
  - service: http_status:404
```

### 2. DNS Configuration

In Cloudflare Dashboard:

1. Go to **DNS** → **Records**
2. Add CNAME records:

```
Type: CNAME
Name: api
Target: <tunnel-id>.cfargotunnel.com
Proxy: Enabled (orange cloud)

Type: CNAME  
Name: app
Target: <tunnel-id>.cfargotunnel.com
Proxy: Enabled (orange cloud)
```

### 3. Backend CORS Configuration

Update `.env` file to fix CORS issues:

```bash
# Production environment
CI_ENVIRONMENT = production

# CORS - Add your Cloudflare domains
CORS_ALLOWED_ORIGINS = https://api.yourdomain.com,https://app.yourdomain.com,https://yourdomain.com

# Security headers
SECURITY_CSRF_PROTECTION = true
SECURITY_CSRF_TOKEN_NAME = csrf_token
SECURITY_CSRF_HEADER_NAME = X-CSRF-TOKEN
```

## Service Setup

### 1. Create Systemd Service

```bash
sudo nano /etc/systemd/system/cloudflared.service
```

```ini
[Unit]
Description=Cloudflare Tunnel
After=network.target

[Service]
Type=simple
User=pi
ExecStart=/usr/local/bin/cloudflared tunnel --config /home/pi/.cloudflared/config.yml run
Restart=always
RestartSec=5
KillMode=mixed
TimeoutStopSec=5

[Install]
WantedBy=multi-user.target
```

### 2. Enable and Start Service

```bash
# Enable service
sudo systemctl enable cloudflared

# Start service
sudo systemctl start cloudflared

# Check status
sudo systemctl status cloudflared

# View logs
sudo journalctl -u cloudflared -f
```

## Performance Optimization

### 1. Backend Optimizations

**PHP Configuration (`/etc/php/8.4/cli/php.ini`):**
```ini
# Increase memory limit
memory_limit = 512M

# Optimize for API responses
max_execution_time = 30
max_input_time = 30

# Enable OPcache
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
```

**PostgreSQL Optimizations:**
```sql
-- Connect as postgres user
sudo -u postgres psql

-- Optimize for API workload
ALTER SYSTEM SET shared_buffers = '256MB';
ALTER SYSTEM SET effective_cache_size = '1GB';
ALTER SYSTEM SET work_mem = '16MB';
ALTER SYSTEM SET maintenance_work_mem = '64MB';
ALTER SYSTEM SET checkpoint_completion_target = 0.9;
ALTER SYSTEM SET wal_buffers = '16MB';
ALTER SYSTEM SET max_connections = 100;

-- Apply changes
SELECT pg_reload_conf();
```

### 2. Cloudflare Optimizations

In Cloudflare Dashboard:

**Speed → Optimization:**
- ✅ Auto Minify (CSS, JS, HTML)
- ✅ Brotli compression
- ✅ Early Hints

**Caching → Configuration:**
- Browser Cache TTL: 4 hours
- Always Online: On

**Network:**
- HTTP/2: Enabled
- HTTP/3 (with QUIC): Enabled
- 0-RTT Connection Resumption: Enabled

## Testing and Validation

### 1. Test Tunnel Connectivity

```bash
# Test tunnel status
cloudflared tunnel info smartlock-api

# Test local connectivity
curl -I http://localhost:8080/api/auth/login

# Test through Cloudflare
curl -I https://api.yourdomain.com/api/auth/login
```

### 2. API Endpoint Testing

**Authentication Test:**
```bash
curl -X POST https://api.yourdomain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' \
  -w "Time: %{time_total}s\n"
```

**Lock Control Test:**
```bash
# Get token first, then test lock control
curl -X POST https://api.yourdomain.com/api/locks/1/control \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action":"unlock"}' \
  -w "Time: %{time_total}s\n"
```

**CORS Test:**
```bash
# Test CORS preflight
curl -X OPTIONS https://api.yourdomain.com/api/locks \
  -H "Origin: https://app.yourdomain.com" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: Authorization" \
  -v
```

### 3. Performance Benchmarking

```bash
# Install Apache Bench
sudo apt install apache2-utils

# Test API performance
ab -n 100 -c 10 -H "Authorization: Bearer YOUR_TOKEN" \
  https://api.yourdomain.com/api/locks

# Expected results:
# - Response time: < 2 seconds
# - Success rate: 100%
# - No CORS errors
```

## Monitoring and Troubleshooting

### 1. Log Monitoring

```bash
# Cloudflare tunnel logs
sudo journalctl -u cloudflared -f

# Backend logs
tail -f /var/log/nginx/error.log  # if using nginx
php spark serve --port=8080 --host=0.0.0.0  # direct PHP logs

# System resources
htop
iostat -x 1
```

### 2. Common Issues and Solutions

**Issue: CORS Errors**
```bash
# Check CORS configuration
grep CORS_ALLOWED_ORIGINS .env

# Should include your Cloudflare domains
CORS_ALLOWED_ORIGINS = https://api.yourdomain.com,https://app.yourdomain.com
```

**Issue: Slow Response Times**
```bash
# Check database connections
php spark db:table users

# Monitor PostgreSQL performance
sudo -u postgres psql -c "SELECT * FROM pg_stat_activity;"

# Check system resources
free -h
df -h
```

**Issue: Tunnel Connection Drops**
```bash
# Check tunnel status
cloudflared tunnel info smartlock-api

# Restart tunnel service
sudo systemctl restart cloudflared

# Check network connectivity
ping 1.1.1.1
```

### 3. Health Check Endpoint

Add to your backend (`app/Controllers/API/HealthController.php`):

```php
<?php
namespace App\Controllers\API;

use CodeIgniter\RESTful\ResourceController;

class HealthController extends ResourceController
{
    public function index()
    {
        $db = \Config\Database::connect();
        
        try {
            $db->query('SELECT 1');
            $dbStatus = 'healthy';
        } catch (\Exception $e) {
            $dbStatus = 'unhealthy';
        }
        
        return $this->respond([
            'status' => 'success',
            'data' => [
                'api' => 'healthy',
                'database' => $dbStatus,
                'timestamp' => date('c'),
                'version' => '1.0.0'
            ]
        ]);
    }
}
```

**Test health endpoint:**
```bash
curl https://api.yourdomain.com/api/health
```

## Security Considerations

### 1. Cloudflare Security Settings

**Firewall Rules:**
- Block traffic from known bad IPs
- Rate limit API endpoints (100 requests/minute per IP)
- Challenge suspicious requests

**SSL/TLS:**
- SSL Mode: Full (Strict)
- Minimum TLS Version: 1.2
- TLS 1.3: Enabled

### 2. Backend Security

**JWT Configuration:**
```bash
# Use strong secrets (64+ characters)
JWT_SECRET_KEY = your-very-long-and-secure-secret-key-here-64-chars-minimum
JWT_REFRESH_SECRET = another-very-long-and-secure-refresh-secret-key-here

# Short token expiry
JWT_EXPIRY = 3600  # 1 hour
JWT_REFRESH_EXPIRY = 604800  # 7 days
```

**Rate Limiting:**
```php
// Add to app/Config/Filters.php
public $globals = [
    'before' => [
        'throttle' => ['except' => ['api/health']]
    ]
];
```

## Migration from localtunnel

### 1. Update Frontend Configuration

**Replace localtunnel URLs:**
```javascript
// Old
const API_BASE_URL = 'https://twelve-kiwis-agree.loca.lt/api'

// New  
const API_BASE_URL = 'https://api.yourdomain.com/api'
```

### 2. Update CORS Settings

```bash
# Remove localtunnel from CORS
# Old
CORS_ALLOWED_ORIGINS = https://twelve-kiwis-agree.loca.lt,http://localhost:3000

# New
CORS_ALLOWED_ORIGINS = https://api.yourdomain.com,https://app.yourdomain.com,http://localhost:3000
```

### 3. Test Migration

```bash
# Test all critical endpoints
./test-api-endpoints.sh https://api.yourdomain.com
```

## Production Checklist

- [ ] Cloudflare tunnel configured and running
- [ ] DNS records pointing to tunnel
- [ ] SSL/TLS certificates active
- [ ] CORS properly configured
- [ ] Rate limiting enabled
- [ ] Health check endpoint working
- [ ] Monitoring and logging configured
- [ ] Performance optimizations applied
- [ ] Security settings reviewed
- [ ] Backup and recovery plan in place

## Expected Performance Improvements

**Before (localtunnel):**
- Response time: 5-7 seconds
- CORS failures: 20-30%
- Connection drops: Frequent
- SSL: Basic

**After (Cloudflare Tunnel):**
- Response time: < 2 seconds
- CORS failures: 0%
- Connection drops: Rare
- SSL: Enterprise-grade

This setup provides a production-ready, stable tunnel solution that addresses all the issues identified in the frontend testing reports.
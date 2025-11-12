# Raspberry Pi 5 Deployment Guide

## Prerequisites Installation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.3+
sudo apt install php8.3 php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip -y

# Install PostgreSQL
sudo apt install postgresql postgresql-contrib -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## Database Setup

```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE smartlocks;
CREATE USER smartlock_user WITH PASSWORD 'SmartLock2024!';
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO smartlock_user;
GRANT ALL PRIVILEGES ON SCHEMA public TO smartlock_user;
\q
```

## Application Deployment

```bash
# Deploy to web directory
cd /var/www/html
sudo git clone <your-repo> smartlock-api
cd smartlock-api/backend

# Set permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 .

# Install dependencies
composer install

# Setup environment
cp .env.example .env
nano .env
```

## Environment Configuration

Edit `.env` for production:

```bash
CI_ENVIRONMENT = production

# Database
database.default.hostname = localhost
database.default.database = smartlocks
database.default.username = smartlock_user
database.default.password = SmartLock2024!

# CORS for Cloudflare domain
CORS_ALLOWED_ORIGINS = https://yourdomain.com,https://api.yourdomain.com

# Strong JWT secrets (generate 64+ character strings)
JWT_SECRET_KEY = your-64-character-secret-key
JWT_REFRESH_SECRET = your-64-character-refresh-secret
```

## Database Migration

```bash
# Run migrations
php spark migrate

# Seed initial data
php spark db:seed UserSeeder
php spark db:seed LockSeeder
php spark db:seed NotificationSeeder
```

## Cloudflare Tunnel Setup

### Install cloudflared

```bash
# Download for ARM64 (Pi 5)
wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm64
sudo mv cloudflared-linux-arm64 /usr/local/bin/cloudflared
sudo chmod +x /usr/local/bin/cloudflared
```

### Configure Tunnel

```bash
# Login to Cloudflare
cloudflared tunnel login

# Create tunnel
cloudflared tunnel create smartlock-api

# Note the tunnel ID from output
```

### Create Configuration

```bash
# Create config directory
mkdir -p ~/.cloudflared

# Create config file
nano ~/.cloudflared/config.yml
```

**config.yml content:**
```yaml
tunnel: 

credentials-file: /home/pi/.cloudflared/<your-tunnel-id>.json

ingress:
  - hostname: api.yourdomain.com
    service: http://localhost:8080
  - service: http_status:404
```

### DNS Configuration

In Cloudflare dashboard:
1. Go to DNS settings
2. Add CNAME record: `api` → `1eb3687a-e0eb-44f5-8767-c75e27aa0edf.cfargotunnel.com`

## Service Setup

### PHP Service

Create systemd service for PHP:

```bash
sudo nano /etc/systemd/system/smartlock-api.service
```

```ini
[Unit]
Description=Smart Lock API
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/smartlock-api/backend
ExecStart=/usr/bin/php spark serve --host=0.0.0.0 --port=8080
Restart=always

[Install]
WantedBy=multi-user.target
```

### Cloudflare Tunnel Service

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
ExecStart=/usr/local/bin/cloudflared tunnel run smartlock-api
Restart=always

[Install]
WantedBy=multi-user.target
```

## Start Services

```bash
# Enable and start services
sudo systemctl enable smartlock-api
sudo systemctl enable cloudflared

sudo systemctl start smartlock-api
sudo systemctl start cloudflared

# Check status
sudo systemctl status smartlock-api
sudo systemctl status cloudflared
```

## Testing

```bash
# Test local API
curl http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Test through Cloudflare tunnel
curl https://api.yourdomain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

## Security Notes

- Change default passwords in UserSeeder before deployment
- Use strong JWT secrets (64+ characters)
- Enable HTTPS only in production
- Configure proper CORS origins
- Monitor logs: `sudo journalctl -u smartlock-api -f`

## Troubleshooting

```bash
# Check PHP service logs
sudo journalctl -u smartlock-api -f

# Check tunnel logs
sudo journalctl -u cloudflared -f

# Check tunnel status
cloudflared tunnel info smartlock-api

# Test database connection
php spark db:table users
```
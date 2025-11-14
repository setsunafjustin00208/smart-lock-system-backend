#!/bin/bash

echo "=== Lockey Smart Lock System - Production Deployment ==="
echo ""

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    echo "⚠️  Do not run as root. Run as the lockey user."
    exit 1
fi

# Verify we're in the right directory
if [ ! -f "spark" ]; then
    echo "❌ Error: Not in the backend directory. Please cd to the backend folder."
    exit 1
fi

echo "🔧 Setting up production environment..."

# Backup existing .env if it exists
if [ -f ".env" ]; then
    cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    echo "✅ Backed up existing .env file"
fi

# Create production .env
cat > .env << 'EOF'
CI_ENVIRONMENT = production
app.baseURL = 'https://lockey.ngrok.io/'
app.appTimezone = 'UTC'

database.default.hostname = localhost
database.default.database = smartlocks
database.default.username = smartlock_user
database.default.password = SmartLock2024!
database.default.DBDriver = Postgre
database.default.port = 5432

JWT_SECRET_KEY = $(openssl rand -base64 64 | tr -d '\n')
COMMAND_SECRET_KEY = $(openssl rand -base64 32 | tr -d '\n')

# CORS Configuration - Production domains only
CORS_ALLOWED_ORIGINS = https://lockey.netlify.app,https://lockey.ngrok.io

# Hardware Integration
WEBSOCKET_ENABLED = true
WEBSOCKET_PORT = 3000
DEVICE_OFFLINE_TIMEOUT = 300

# ngrok Production
NGROK_TUNNEL_URL = https://lockey.ngrok.io

# Security
SESSION_EXPIRE_ON_CLOSE = true
COOKIE_SECURE = true
COOKIE_HTTPONLY = true

# Logging
logger.threshold = 2
EOF

echo "✅ Created production .env file"

# Run migrations
echo "🗄️  Running database migrations..."
php spark migrate

# Run production seeder
echo "👥 Creating production users..."
php spark db:seed ProductionUserSeeder

# Set proper permissions
echo "🔒 Setting file permissions..."
chmod 600 .env
chmod -R 755 writable/
chmod -R 755 public/

# Clear cache
echo "🧹 Clearing cache..."
php spark cache:clear

echo ""
echo "🎉 Production deployment complete!"
echo ""
echo "=== Default Users Created ==="
echo "Admin:    admin / LockeyAdmin2024!"
echo "Security: security / LockeySecure2024!"
echo "Operator: operator / LockeyOp2024!"
echo ""
echo "⚠️  CRITICAL SECURITY STEPS:"
echo "1. Change all default passwords immediately"
echo "2. Update JWT secrets in .env if needed"
echo "3. Configure proper CORS origins"
echo "4. Set up SSL certificates"
echo "5. Configure firewall rules"
echo ""
echo "🚀 Start services with: ./start_lockey.sh"
echo "📊 Monitor with: tmux attach -t lockey"
echo ""
echo "System is ready for production use!"

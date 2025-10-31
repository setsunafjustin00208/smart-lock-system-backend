#!/bin/bash
# Smart Lock Backend - Automated Installation Script
# Supports: WSL, Ubuntu, Debian, Raspberry Pi OS

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
    exit 1
}

# Detect system
detect_system() {
    if grep -q Microsoft /proc/version; then
        SYSTEM="WSL"
    elif grep -q "Raspberry Pi" /proc/cpuinfo; then
        SYSTEM="RASPBERRY_PI"
    elif [ -f /etc/os-release ]; then
        . /etc/os-release
        SYSTEM="$ID"
    else
        SYSTEM="UNKNOWN"
    fi
    
    log "Detected system: $SYSTEM"
}

# Check if running as root
check_root() {
    if [ "$EUID" -eq 0 ]; then
        error "Please run this script as a regular user, not root"
    fi
}

# Install PostgreSQL
install_postgresql() {
    log "Installing PostgreSQL..."
    
    # Update package list
    sudo apt update
    
    # Install PostgreSQL and dependencies
    sudo apt install -y postgresql postgresql-contrib postgresql-client php8.4-pgsql php8.4-curl php8.4-intl php8.4-mbstring php8.4-xml php8.4-zip
    
    # Start PostgreSQL service
    if [ "$SYSTEM" = "WSL" ]; then
        sudo service postgresql start
    else
        sudo systemctl start postgresql
        sudo systemctl enable postgresql
    fi
    
    log "PostgreSQL installed successfully"
}

# Setup database
setup_database() {
    log "Setting up database..."
    
    # Create database and user
    sudo -u postgres psql << 'EOF'
DROP DATABASE IF EXISTS smartlocks;
CREATE DATABASE smartlocks;
DROP USER IF EXISTS smartlock_user;
CREATE USER smartlock_user WITH PASSWORD 'SmartLock2024!';
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO smartlock_user;
ALTER USER smartlock_user CREATEDB;
\c smartlocks
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
GRANT ALL ON SCHEMA public TO smartlock_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO smartlock_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO smartlock_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO smartlock_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO smartlock_user;
\q
EOF
    
    log "Database setup completed"
}

# Optimize for Raspberry Pi
optimize_raspberry_pi() {
    if [ "$SYSTEM" = "RASPBERRY_PI" ]; then
        log "Optimizing PostgreSQL for Raspberry Pi..."
        
        sudo -u postgres psql << 'EOF'
ALTER SYSTEM SET shared_buffers = '256MB';
ALTER SYSTEM SET effective_cache_size = '2GB';
ALTER SYSTEM SET maintenance_work_mem = '128MB';
ALTER SYSTEM SET work_mem = '8MB';
ALTER SYSTEM SET checkpoint_completion_target = 0.9;
ALTER SYSTEM SET wal_buffers = '16MB';
ALTER SYSTEM SET max_connections = 50;
SELECT pg_reload_conf();
\q
EOF
        
        sudo systemctl restart postgresql
        log "Raspberry Pi optimizations applied"
    fi
}

# Install Composer if not present
install_composer() {
    if ! command -v composer &> /dev/null; then
        log "Installing Composer..."
        
        EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)"
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
        
        if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then
            error "Invalid Composer installer signature"
        fi
        
        php composer-setup.php --install-dir=/usr/local/bin --filename=composer
        rm composer-setup.php
        
        log "Composer installed successfully"
    else
        log "Composer already installed"
    fi
}

# Setup backend
setup_backend() {
    log "Setting up Smart Lock Backend..."
    
    # Navigate to backend directory
    BACKEND_DIR="$(dirname "$0")/../"
    cd "$BACKEND_DIR"
    
    # Install PHP dependencies
    if [ -f "composer.json" ]; then
        log "Installing PHP dependencies..."
        composer install --optimize-autoloader
    else
        error "composer.json not found. Are you in the correct directory?"
    fi
    
    # Create .env file
    log "Creating environment configuration..."
    cat > .env << 'EOF'
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'
app.appTimezone = 'UTC'

database.default.hostname = localhost
database.default.database = smartlocks
database.default.username = smartlock_user
database.default.password = SmartLock2024!
database.default.DBDriver = Postgre
database.default.port = 5432

JWT_SECRET_KEY = SmartLockJWTSecret2024VeryLongAndSecureKey
COMMAND_SECRET_KEY = ESP32CommandSignatureSecret2024ForHardware

logger.threshold = 4
EOF
    
    # Run migrations
    log "Running database migrations..."
    php spark migrate --all
    
    # Seed test data
    log "Seeding test data..."
    php spark db:seed UserSeeder
    php spark db:seed LockSeeder
    
    log "Backend setup completed"
}

# Test installation
test_installation() {
    log "Testing installation..."
    
    # Start server in background
    php spark serve --port=8080 &
    SERVER_PID=$!
    
    # Wait for server to start
    sleep 5
    
    # Test API endpoint
    RESPONSE=$(curl -s -X POST http://localhost:8080/api/auth/login \
        -H "Content-Type: application/json" \
        -d '{"username":"admin","password":"admin123"}' || echo "FAILED")
    
    # Stop server
    kill $SERVER_PID 2>/dev/null || true
    
    if echo "$RESPONSE" | grep -q "success"; then
        log "✅ API test successful!"
        return 0
    else
        warn "❌ API test failed"
        echo "Response: $RESPONSE"
        return 1
    fi
}

# Setup WSL auto-start
setup_wsl_autostart() {
    if [ "$SYSTEM" = "WSL" ]; then
        log "Setting up WSL auto-start..."
        
        # Add PostgreSQL auto-start to bashrc
        if ! grep -q "postgresql start" ~/.bashrc; then
            echo "" >> ~/.bashrc
            echo "# Auto-start PostgreSQL for Smart Lock Backend" >> ~/.bashrc
            echo "if ! pgrep -x postgres > /dev/null; then" >> ~/.bashrc
            echo "    sudo service postgresql start > /dev/null 2>&1" >> ~/.bashrc
            echo "fi" >> ~/.bashrc
        fi
        
        log "WSL auto-start configured"
    fi
}

# Main installation function
main() {
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║              Smart Lock Backend Installer                    ║"
    echo "║                                                              ║"
    echo "║  This script will install and configure:                    ║"
    echo "║  • PostgreSQL Database                                       ║"
    echo "║  • PHP Dependencies                                          ║"
    echo "║  • CodeIgniter 4 Backend                                     ║"
    echo "║  • Test Data & API Endpoints                                 ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    
    # Checks
    check_root
    detect_system
    
    # Confirm installation
    read -p "Continue with installation? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log "Installation cancelled"
        exit 0
    fi
    
    # Installation steps
    log "Starting installation process..."
    
    install_postgresql
    setup_database
    optimize_raspberry_pi
    install_composer
    setup_backend
    setup_wsl_autostart
    
    # Test installation
    if test_installation; then
        echo -e "${GREEN}"
        echo "╔══════════════════════════════════════════════════════════════╗"
        echo "║                   🎉 INSTALLATION COMPLETE! 🎉               ║"
        echo "║                                                              ║"
        echo "║  Your Smart Lock Backend is ready!                          ║"
        echo "║                                                              ║"
        echo "║  🚀 Start server:                                            ║"
        echo "║     cd backend && php spark serve --port=8080               ║"
        echo "║                                                              ║"
        echo "║  🧪 Test credentials:                                        ║"
        echo "║     admin / admin123                                         ║"
        echo "║     user / user123                                           ║"
        echo "║                                                              ║"
        echo "║  📡 API Base URL:                                            ║"
        echo "║     http://localhost:8080/api                                ║"
        echo "║                                                              ║"
        echo "║  🔗 WebSocket URL:                                           ║"
        echo "║     ws://localhost:3000                                      ║"
        echo "╚══════════════════════════════════════════════════════════════╝"
        echo -e "${NC}"
    else
        error "Installation completed but API test failed. Please check the logs above."
    fi
}

# Run main function
main "$@"

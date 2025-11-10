# PostgreSQL Installation - WSL & Raspberry Pi

Automated installation guide for WSL (Windows Subsystem for Linux) and Raspberry Pi 5 with Linux distributions.

## 🐧 **WSL (Windows Subsystem for Linux)**

### **Automated Installation Script**

```bash
#!/bin/bash
# PostgreSQL Auto-Install for WSL
echo "🚀 Installing PostgreSQL on WSL..."

# Update system
sudo apt update && sudo apt upgrade -y

# Install PostgreSQL and dependencies
sudo apt install -y postgresql postgresql-contrib postgresql-client

# Start PostgreSQL service
sudo service postgresql start

# Enable auto-start (for systemd-enabled WSL)
if command -v systemctl &> /dev/null; then
    sudo systemctl enable postgresql
fi

# Setup database
sudo -u postgres psql << EOF
CREATE DATABASE smartlocks;
CREATE USER postgres WITH PASSWORD 'postgres';
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO postgres;
ALTER USER postgres CREATEDB;
\c smartlocks
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
\q
EOF

echo "✅ PostgreSQL installed successfully!"
echo "📊 Database: smartlocks"
echo "👤 User: postgres"
echo "🔑 Password: postgres"
```

### **Quick Install Commands**

```bash
# One-liner installation
curl -fsSL https://raw.githubusercontent.com/your-repo/install-postgres-wsl.sh | bash

# Or manual steps:
sudo apt update
sudo apt install -y postgresql postgresql-contrib
sudo service postgresql start
sudo -u postgres createdb smartlocks
```

### **WSL-Specific Configuration**

```bash
# Auto-start PostgreSQL on WSL boot
echo "sudo service postgresql start" >> ~/.bashrc

# Or create a startup script
cat > ~/start-postgres.sh << 'EOF'
#!/bin/bash
if ! pgrep -x "postgres" > /dev/null; then
    sudo service postgresql start
    echo "PostgreSQL started"
fi
EOF

chmod +x ~/start-postgres.sh
```

---

## 🍓 **Raspberry Pi 5 Installation**

### **Raspbian/Raspberry Pi OS**

```bash
#!/bin/bash
# PostgreSQL Auto-Install for Raspberry Pi 5
echo "🍓 Installing PostgreSQL on Raspberry Pi 5..."

# Update system
sudo apt update && sudo apt upgrade -y

# Install PostgreSQL (optimized for ARM64)
sudo apt install -y postgresql postgresql-contrib postgresql-client

# Start and enable service
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Optimize for Raspberry Pi (optional)
sudo -u postgres psql << 'EOF'
-- Performance tuning for Raspberry Pi
ALTER SYSTEM SET shared_buffers = '128MB';
ALTER SYSTEM SET effective_cache_size = '1GB';
ALTER SYSTEM SET maintenance_work_mem = '64MB';
ALTER SYSTEM SET checkpoint_completion_target = 0.9;
ALTER SYSTEM SET wal_buffers = '16MB';
SELECT pg_reload_conf();
EOF

# Setup database
sudo -u postgres psql << 'EOF'
CREATE DATABASE smartlocks;
CREATE USER smartlock_user WITH PASSWORD 'RaspberryPi2024!';
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO smartlock_user;
ALTER USER smartlock_user CREATEDB;
\c smartlocks
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
\q
EOF

echo "✅ PostgreSQL installed on Raspberry Pi!"
echo "🔧 Optimized for ARM64 architecture"
```

### **Ubuntu Server on Raspberry Pi**

```bash
# Ubuntu 22.04+ on Raspberry Pi
sudo apt update
sudo apt install -y postgresql postgresql-contrib

# Same setup as above
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

---

## 🔄 **Automated Testing Setup**

### **Complete Test Environment Script**

```bash
#!/bin/bash
# complete-setup.sh - Full environment setup

set -e  # Exit on any error

echo "🚀 Setting up Smart Lock Backend Test Environment..."

# 1. Install PostgreSQL
if ! command -v psql &> /dev/null; then
    echo "📦 Installing PostgreSQL..."
    sudo apt update
    sudo apt install -y postgresql postgresql-contrib php8.4-pgsql
    sudo service postgresql start
fi

# 2. Setup database
echo "🗄️ Setting up database..."
sudo -u postgres psql << 'EOF'
DROP DATABASE IF EXISTS smartlocks;
CREATE DATABASE smartlocks;
DROP USER IF EXISTS smartlock_user;
CREATE USER smartlock_user WITH PASSWORD 'test123';
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO smartlock_user;
ALTER USER smartlock_user CREATEDB;
\c smartlocks
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
\q
EOF

# 3. Configure backend
echo "⚙️ Configuring backend..."
cd "$(dirname "$0")/../../"

# Create .env file
cat > .env << 'EOF'
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'
database.default.hostname = localhost
database.default.database = smartlocks
database.default.username = smartlock_user
database.default.password = test123
database.default.DBDriver = Postgre
database.default.port = 5432
JWT_SECRET_KEY = TestJWTSecret2024ForAutomatedTesting
COMMAND_SECRET_KEY = TestCommandSecret2024ForHardware
logger.threshold = 4
EOF

# 4. Install PHP dependencies
if [ -f "composer.json" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# 5. Run migrations and seeders
echo "🗃️ Setting up database schema..."
php spark migrate --all
php spark db:seed UserSeeder
php spark db:seed LockSeeder

# 6. Test API
echo "🧪 Testing API endpoints..."
php spark serve --port=8080 &
SERVER_PID=$!
sleep 3

# Test login
RESPONSE=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}')

if echo "$RESPONSE" | grep -q "success"; then
    echo "✅ API test successful!"
    echo "🎉 Environment ready for testing!"
else
    echo "❌ API test failed"
    echo "Response: $RESPONSE"
fi

# Stop test server
kill $SERVER_PID 2>/dev/null || true

echo ""
echo "🚀 Setup complete! Run these commands to start:"
echo "   cd backend"
echo "   php spark serve --port=8080"
echo ""
echo "🧪 Test credentials:"
echo "   admin / admin123"
echo "   user / user123"
```

---

## 🤖 **CI/CD Integration**

### **GitHub Actions Workflow**

```yaml
# .github/workflows/test.yml
name: Smart Lock Backend Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_DB: smartlocks
          POSTGRES_USER: test_user
          POSTGRES_PASSWORD: test_pass
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432

    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.4'
        extensions: pgsql, pdo_pgsql, curl, json
    
    - name: Install dependencies
      run: composer install
      
    - name: Setup environment
      run: |
        cp .env.example .env
        sed -i 's/database.default.password = .*/database.default.password = test_pass/' .env
        
    - name: Run migrations
      run: php spark migrate --all
      
    - name: Seed database
      run: |
        php spark db:seed UserSeeder
        php spark db:seed LockSeeder
        
    - name: Run tests
      run: |
        php spark serve --port=8080 &
        sleep 3
        curl -f http://localhost:8080/api/auth/login \
          -H "Content-Type: application/json" \
          -d '{"username":"admin","password":"admin123"}'
```

---

## 📊 **Performance Optimization**

### **Raspberry Pi Specific Tuning**

```bash
# PostgreSQL config for Raspberry Pi 5 (8GB RAM)
sudo -u postgres psql << 'EOF'
-- Memory settings
ALTER SYSTEM SET shared_buffers = '256MB';
ALTER SYSTEM SET effective_cache_size = '2GB';
ALTER SYSTEM SET maintenance_work_mem = '128MB';
ALTER SYSTEM SET work_mem = '8MB';

-- Checkpoint settings
ALTER SYSTEM SET checkpoint_completion_target = 0.9;
ALTER SYSTEM SET wal_buffers = '16MB';
ALTER SYSTEM SET default_statistics_target = 100;

-- Connection settings
ALTER SYSTEM SET max_connections = 50;

-- Apply changes
SELECT pg_reload_conf();
\q
EOF

# Restart PostgreSQL
sudo systemctl restart postgresql
```

### **WSL Performance Tips**

```bash
# WSL-specific optimizations
echo "# PostgreSQL WSL optimizations" | sudo tee -a /etc/postgresql/*/main/postgresql.conf
echo "fsync = off" | sudo tee -a /etc/postgresql/*/main/postgresql.conf
echo "synchronous_commit = off" | sudo tee -a /etc/postgresql/*/main/postgresql.conf

# Restart service
sudo service postgresql restart
```

---

## 🔧 **Quick Commands**

### **Installation**
```bash
# WSL
curl -fsSL https://raw.githubusercontent.com/your-repo/wsl-install.sh | bash

# Raspberry Pi
curl -fsSL https://raw.githubusercontent.com/your-repo/rpi-install.sh | bash

# Complete setup
curl -fsSL https://raw.githubusercontent.com/your-repo/complete-setup.sh | bash
```

### **Management**
```bash
# Start PostgreSQL
sudo service postgresql start          # WSL
sudo systemctl start postgresql       # Raspberry Pi

# Check status
sudo service postgresql status         # WSL
sudo systemctl status postgresql      # Raspberry Pi

# Connect to database
psql -h localhost -U smartlock_user -d smartlocks

# Reset database
sudo -u postgres dropdb smartlocks
sudo -u postgres createdb smartlocks
```

### **Testing**
```bash
# Quick API test
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Health check
curl http://localhost:8080/api/locks \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🚀 **Production Deployment**

### **Raspberry Pi Production Setup**

```bash
#!/bin/bash
# production-deploy.sh

# Install with production settings
sudo apt install -y postgresql postgresql-contrib nginx php8.4-fpm php8.4-pgsql

# Secure PostgreSQL
sudo -u postgres psql << 'EOF'
CREATE DATABASE smartlocks_prod;
CREATE USER smartlock_prod WITH PASSWORD 'CHANGE_THIS_PASSWORD';
GRANT ALL PRIVILEGES ON DATABASE smartlocks_prod TO smartlock_prod;
\q
EOF

# Configure Nginx
sudo tee /etc/nginx/sites-available/smartlock << 'EOF'
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/smartlock/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
EOF

sudo ln -s /etc/nginx/sites-available/smartlock /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

**Ready for automated testing and production deployment!** 🎉

# 🚀 Smart Lock Backend - One-Click Installation

Automated installation script for WSL, Ubuntu, Debian, and Raspberry Pi OS.

## ⚡ Quick Install

```bash
# Navigate to console directory
cd backend/console

# Run the installer
./install.sh
```

## 🎯 What It Does

The script automatically:

1. **🔍 Detects your system** (WSL, Raspberry Pi, Ubuntu, etc.)
2. **📦 Installs PostgreSQL** with optimizations
3. **🗄️ Creates database** and user accounts
4. **⚙️ Configures backend** environment
5. **🧪 Seeds test data** (users and locks)
6. **✅ Tests API endpoints** to verify installation

## 📋 Requirements

- **Linux-based system** (WSL, Ubuntu, Debian, Raspberry Pi OS)
- **Internet connection** for package downloads
- **sudo privileges** for system packages

## 🎮 Usage

### **Standard Installation**
```bash
./install.sh
```

### **Check Installation Status**
```bash
# Test API manually
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

### **Start Backend Server**
```bash
cd ../../..  # Navigate to backend root
php spark serve --port=8080
```

## 🧪 Test Credentials

After installation, use these credentials:

| Username | Password | Role |
|----------|----------|------|
| admin | admin123 | Admin |
| manager | manager123 | Manager |
| user | user123 | User |
| guest | guest123 | Guest |

## 🔧 Manual Steps (If Needed)

If the automated script fails, run these commands manually:

```bash
# Install PostgreSQL
sudo apt update
sudo apt install -y postgresql postgresql-contrib php8.4-pgsql

# Start service
sudo systemctl start postgresql  # or sudo service postgresql start for WSL

# Create database
sudo -u postgres createdb smartlocks

# Install Composer (if needed)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Setup backend
cd ../  # Navigate to backend root
composer install
cp .env.example .env
# Edit .env with database credentials
php spark migrate
php spark db:seed UserSeeder
```

## 🍓 Raspberry Pi Specific

The script includes optimizations for Raspberry Pi:

- **Memory tuning** for 8GB RAM models
- **Performance settings** for ARM64 architecture
- **Connection limits** appropriate for Pi hardware

## 🐧 WSL Specific

For WSL users, the script:

- **Auto-starts PostgreSQL** on WSL boot
- **Configures service management** for WSL environment
- **Sets up development optimizations**

## 🚨 Troubleshooting

### **Permission Denied**
```bash
chmod +x install.sh
```

### **PostgreSQL Connection Failed**
```bash
sudo systemctl status postgresql
sudo systemctl restart postgresql
```

### **Composer Not Found**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### **API Test Failed**
```bash
# Check if server is running
ps aux | grep "php spark serve"

# Check database connection
psql -h localhost -U smartlock_user -d smartlocks
```

## 📊 System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| RAM | 1GB | 2GB+ |
| Storage | 2GB | 5GB+ |
| PHP | 8.1+ | 8.4+ |
| PostgreSQL | 13+ | 15+ |

## 🎉 Success Indicators

After successful installation, you should see:

```
╔══════════════════════════════════════════════════════════════╗
║                   🎉 INSTALLATION COMPLETE! 🎉               ║
║                                                              ║
║  Your Smart Lock Backend is ready!                          ║
║                                                              ║
║  🚀 Start server:                                            ║
║     cd backend && php spark serve --port=8080               ║
║                                                              ║
║  🧪 Test credentials:                                        ║
║     admin / admin123                                         ║
║     user / user123                                           ║
║                                                              ║
║  📡 API Base URL:                                            ║
║     http://localhost:8080/api                                ║
╚══════════════════════════════════════════════════════════════╝
```

**Ready for Vue.js frontend development!** 🚀

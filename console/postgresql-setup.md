# PostgreSQL Installation Guide

Complete setup instructions for PostgreSQL database required by the Smart Lock Backend API.

## 🖥️ **Windows Installation**

### **Method 1: Official Installer (Recommended)**

1. **Download PostgreSQL:**
   - Visit: https://www.postgresql.org/download/windows/
   - Download PostgreSQL 15+ installer
   - Run the `.exe` file as Administrator

2. **Installation Steps:**
   ```
   ✅ Select Components: PostgreSQL Server, pgAdmin 4, Command Line Tools
   ✅ Data Directory: C:\Program Files\PostgreSQL\15\data
   ✅ Password: Set a strong password (remember this!)
   ✅ Port: 5432 (default)
   ✅ Locale: Default locale
   ```

3. **Verify Installation:**
   ```cmd
   # Open Command Prompt as Administrator
   psql --version
   
   # Should show: psql (PostgreSQL) 15.x
   ```

### **Method 2: Chocolatey (Advanced Users)**

```powershell
# Install Chocolatey first (if not installed)
Set-ExecutionPolicy Bypass -Scope Process -Force
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))

# Install PostgreSQL
choco install postgresql --params '/Password:postgres'
```

---

## 🐧 **Linux/WSL Installation**

### **Ubuntu/Debian:**

```bash
# Update package list
sudo apt update

# Install PostgreSQL
sudo apt install postgresql postgresql-contrib

# Start PostgreSQL service
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Verify installation
sudo -u postgres psql --version
```

### **CentOS/RHEL/Fedora:**

```bash
# Install PostgreSQL
sudo dnf install postgresql postgresql-server postgresql-contrib

# Initialize database
sudo postgresql-setup --initdb

# Start and enable service
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

---

## 🍎 **macOS Installation**

### **Method 1: Homebrew (Recommended)**

```bash
# Install Homebrew (if not installed)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Install PostgreSQL
brew install postgresql@15

# Start PostgreSQL service
brew services start postgresql@15

# Verify installation
psql --version
```

### **Method 2: Official Installer**

1. Download from: https://www.postgresql.org/download/macos/
2. Run the installer package
3. Follow installation wizard

---

## 🗄️ **Database Setup**

### **Step 1: Access PostgreSQL**

**Windows:**
```cmd
# Using psql command line
psql -U postgres -h localhost

# Or use pgAdmin 4 (GUI)
# Start Menu → pgAdmin 4
```

**Linux/WSL:**
```bash
# Switch to postgres user
sudo -u postgres psql
```

**macOS:**
```bash
# Direct access
psql postgres
```

### **Step 2: Create Database and User**

```sql
-- Create database
CREATE DATABASE smartlocks;

-- Create user (if using different credentials)
CREATE USER smartlock_user WITH PASSWORD 'your_secure_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO smartlock_user;

-- Enable UUID extension
\c smartlocks
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Exit
\q
```

### **Step 3: Test Connection**

```bash
# Test connection
psql -h localhost -U postgres -d smartlocks

# Should connect successfully
# Type \q to exit
```

---

## ⚙️ **Configuration**

### **Update Backend .env File**

```bash
cd /path/to/backend
cp .env.example .env
```

Edit `.env` file:
```ini
# Database Configuration
database.default.hostname = localhost
database.default.database = smartlocks
database.default.username = postgres
database.default.password = your_password_here
database.default.DBDriver = Postgre
database.default.port = 5432
```

### **Test Backend Connection**

```bash
# Run migrations
php spark migrate

# Seed test data
php spark db:seed UserSeeder
php spark db:seed LockSeeder

# Start server
php spark serve --port=8080
```

---

## 🐳 **Docker Alternative (Optional)**

If you prefer Docker:

```bash
# Create docker-compose.yml
version: '3.8'
services:
  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: smartlocks
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: postgres
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

volumes:
  postgres_data:
```

```bash
# Start PostgreSQL container
docker-compose up -d

# Connect to database
docker exec -it <container_name> psql -U postgres -d smartlocks
```

---

## 🔧 **Troubleshooting**

### **Common Issues:**

#### **Connection Refused**
```bash
# Check if PostgreSQL is running
sudo systemctl status postgresql  # Linux
brew services list | grep postgresql  # macOS
```

#### **Authentication Failed**
```bash
# Reset postgres password (Linux/WSL)
sudo -u postgres psql
ALTER USER postgres PASSWORD 'new_password';
```

#### **Port Already in Use**
```bash
# Check what's using port 5432
netstat -tulpn | grep 5432  # Linux
lsof -i :5432  # macOS
netstat -ano | findstr :5432  # Windows
```

#### **Permission Denied**
```sql
-- Grant all privileges
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO postgres;
GRANT ALL ON SCHEMA public TO postgres;
```

### **Verification Commands**

```bash
# Check PostgreSQL version
psql --version

# Check service status
sudo systemctl status postgresql  # Linux
brew services list | grep postgresql  # macOS

# Test connection
psql -h localhost -U postgres -d smartlocks -c "SELECT version();"
```

---

## ✅ **Final Verification**

After installation, verify everything works:

```bash
# 1. Connect to database
psql -h localhost -U postgres -d smartlocks

# 2. Run this SQL command
SELECT version();

# 3. Should show PostgreSQL version info
# 4. Exit with \q

# 5. Test backend connection
cd backend
php spark migrate
php spark db:seed UserSeeder

# 6. Start server
php spark serve --port=8080

# 7. Test API
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

If you see a successful login response, **PostgreSQL is working perfectly!** 🎉

---

## 📞 **Need Help?**

- **PostgreSQL Documentation:** https://www.postgresql.org/docs/
- **pgAdmin Documentation:** https://www.pgadmin.org/docs/
- **Common Issues:** Check the troubleshooting section above

**Installation complete!** Your Smart Lock Backend is ready for testing. 🚀

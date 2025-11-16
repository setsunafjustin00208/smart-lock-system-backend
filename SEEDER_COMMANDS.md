# Database Seeder Commands

## Available Seeders

### 1. Production Seeder (Clean Production Setup)
```bash
php spark db:seed ProductionUserSeeder
```
**What it does:**
- 🧹 **Cleans all test/development data**
- 👥 **Creates 3 production users** with secure passwords
- 🔒 **Sets up proper permissions** for all locks
- ⚠️ **Forces password change** on first login

**Created Users:**
```
admin     / LockeyAdmin2024!   (Admin role)
security  / LockeySecure2024!  (Manager role)
operator  / LockeyOp2024!      (User role)
```

### 2. Development User Seeder
```bash
php spark db:seed UserSeeder
```
**What it does:**
- 👥 **Creates development users** with simple passwords
- 🔓 **No password change required**
- 🧪 **For testing and development only**

**Created Users:**
```
admin    / admin123    (Admin role)
manager  / manager123  (Manager role)
user     / user123     (User role)
guest    / guest123    (Guest role)
```

### 3. Lock Seeder (Sample Locks)
```bash
php spark db:seed LockSeeder
```
**What it does:**
- 🚪 **Creates 6 sample locks** with different configurations
- 🔧 **Sets up hardware IDs** for ESP32 devices
- ⚙️ **Configures default settings** for each lock

**Created Locks:**
```
Main Entrance     (ESP32_MAIN_001)
Conference Room A (ESP32_CONF_A01)
Server Room       (ESP32_SERVER_001)
Warehouse Gate    (ESP32_WH_GATE_001)
Storage Room      (ESP32_STORAGE_001)
Test Lock         (ESP32_TEST_001)
```

### 4. Notification Seeder (Sample Notifications)
```bash
php spark db:seed NotificationSeeder
```
**What it does:**
- 📢 **Creates sample notifications** for testing
- 🔔 **Different notification types** (alerts, status, actions)
- 👤 **Assigns to different users**

## Combined Commands

### Full Development Setup
```bash
# Run all development seeders
php spark db:seed UserSeeder
php spark db:seed LockSeeder  
php spark db:seed NotificationSeeder
```

### Production Deployment
```bash
# Clean setup for production (recommended)
php spark db:seed ProductionUserSeeder
```

### Reset Everything
```bash
# Drop all tables and recreate
php spark migrate:refresh

# Then run desired seeders
php spark db:seed ProductionUserSeeder
```

## Seeder Details

### ProductionUserSeeder Features
- ✅ **Cleanup Function** - Removes all test data before creating production users
- ✅ **Security First** - Strong passwords with special characters
- ✅ **Role-based Permissions** - Automatic permission assignment
- ✅ **Duplicate Prevention** - Won't create users if they already exist
- ✅ **Lock Permissions** - Sets up proper access control for all locks

### Data Cleanup (ProductionUserSeeder)
**Removes:**
- Test locks (names containing "Test", "ESP32", "Auto-registered")
- Test hardware (hardware_id containing "TEST")
- Test users (all except admin, security, operator)
- Old command queue entries
- Old activity logs (keeps last 100)
- Old notifications (keeps last 50)
- Related permissions and references

### Security Notes
- **Production passwords** are complex and must be changed on first login
- **Development passwords** are simple for easy testing
- **All passwords** use Argon2ID hashing for maximum security
- **Role permissions** are automatically configured based on user role

## Usage Examples

### New Production Deployment
```bash
# 1. Run migrations
php spark migrate

# 2. Set up production users (includes cleanup)
php spark db:seed ProductionUserSeeder

# 3. Start services
./start_lockey.sh
```

### Development Environment
```bash
# 1. Run migrations
php spark migrate

# 2. Set up development data
php spark db:seed UserSeeder
php spark db:seed LockSeeder
php spark db:seed NotificationSeeder

# 3. Start development server
php spark serve --port=8080
```

### Clean Production Reset
```bash
# 1. Reset database completely
php spark migrate:refresh

# 2. Set up clean production environment
php spark db:seed ProductionUserSeeder

# 3. Deploy
./start_lockey.sh
```

## Troubleshooting

### If Seeder Fails
```bash
# Check database connection
php spark migrate:status

# Check for syntax errors
php -l app/Database/Seeds/ProductionUserSeeder.php

# Run with verbose output
php spark db:seed ProductionUserSeeder -v
```

### If Users Already Exist
- ProductionUserSeeder will skip existing users
- Use `migrate:refresh` to start completely fresh
- Or manually delete users from database first

### Permission Issues
```bash
# Make sure database user has proper permissions
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO smartlock_user;
GRANT ALL PRIVILEGES ON SCHEMA public TO smartlock_user;
```

---

**Choose the appropriate seeder based on your environment:**
- **Production:** Use `ProductionUserSeeder` only
- **Development:** Use `UserSeeder` + `LockSeeder` + `NotificationSeeder`
- **Testing:** Use any combination as needed

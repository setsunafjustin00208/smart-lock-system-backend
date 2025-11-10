# Smart Lock Backend API

CodeIgniter 4 backend for ESP32-based smart lock system with JWT authentication and real-time notifications.

## 🚀 Quick Start

### Prerequisites
- PHP 8.3+
- PostgreSQL 13+
- Composer

### Installation

1. **Install dependencies:**
```bash
composer install
```

2. **Setup environment:**
```bash
cp .env.example .env
# Edit .env with your database credentials and CORS settings
```

3. **Create database:**
```sql
CREATE DATABASE smartlocks;
CREATE USER smartlock_user WITH PASSWORD 'SmartLock2024!';
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO smartlock_user;
GRANT ALL PRIVILEGES ON SCHEMA public TO smartlock_user;
```

4. **Run migrations:**
```bash
php spark migrate
```

5. **Seed data:**
```bash
php spark db:seed UserSeeder
php spark db:seed LockSeeder
php spark db:seed NotificationSeeder
```

6. **Start server:**
```bash
php spark serve --port=8080
```

## 🔑 API Endpoints

### Authentication
- `POST /api/auth/login` - Login user
- `POST /api/auth/logout` - Logout user  
- `POST /api/auth/refresh` - Refresh token
- `PUT /api/auth/profile` - Update user profile
- `PUT /api/auth/password` - Change password
- `GET /api/auth/notifications` - Get notification settings
- `PUT /api/auth/notifications` - Update notification settings

### Users (Admin only)
- `GET /api/users` - List users
- `POST /api/users` - Create user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

### Locks
- `GET /api/locks` - List locks
- `GET /api/locks/{id}` - Get lock details
- `POST /api/locks/{id}/control` - Control lock (lock/unlock/status)
- `GET /api/locks/status` - Get online/offline status for all locks

### Notifications
- `GET /api/notifications` - List notifications
- `PUT /api/notifications/{id}/read` - Mark notification as read
- `PUT /api/notifications/read-all` - Mark all notifications as read
- `DELETE /api/notifications/{id}` - Delete notification

## 🧪 Test Credentials

```
Admin:   admin / admin123
Manager: manager / manager123  
User:    user / user123
Guest:   guest / guest123
```

## 📡 Real-time Updates

The system uses polling for real-time updates instead of WebSocket:

```javascript
// Frontend polling strategy
setInterval(async () => {
  await fetchLocks()           // Every 3 seconds
}, 3000)

setInterval(async () => {
  await fetchNotifications()   // Every 5 seconds
}, 5000)

setInterval(async () => {
  await fetchLockStatus()      // Every 30 seconds
}, 30000)
```

## 🔒 Security Features

- JWT authentication with refresh tokens (1-hour expiry)
- Argon2ID password hashing
- Role-based access control (Admin, Manager, User, Guest)
- Rate limiting (5 failed attempts = 30min lockout)
- CORS configuration (environment-based)
- Input validation and sanitization
- Activity logging for all actions

## 🏗️ Architecture

- **Framework:** CodeIgniter 4
- **Database:** PostgreSQL with JSONB
- **Authentication:** JWT tokens
- **Real-time:** Polling-based updates
- **Security:** Role-based permissions

## 📊 Database Schema

```sql
-- Users with JSONB auth data
users (id, uuid, username, email, auth_data, profile_data)

-- Locks with JSONB config/status
locks (id, uuid, name, hardware_id, config_data, status_data, is_online)

-- User permissions per lock
user_lock_permissions (user_id, lock_id, permissions)

-- Activity logging
activity_logs (user_id, lock_id, action, details, created_at)

-- Notifications system
notifications (id, user_id, type, title, message, lock_id, lock_name, is_read, created_at)
```

## 🔧 Hardware Integration

Hardware devices communicate via HTTP REST API:

**Lock Control:**
```json
{
  "action": "unlock",
  "lock_id": 1,
  "hardware_id": "ESP32_MAIN_001",
  "timestamp": 1640995200
}
```

**Status Response:**
```json
{
  "status": "success",
  "data": {
    "success": true,
    "message": "Unlock command sent successfully",
    "hardware_response": {
      "status": "unlocked",
      "timestamp": "2025-11-02T03:44:00Z"
    }
  }
}
```

## 🌐 CORS Configuration

CORS settings are environment-based via `.env`:

```bash
# Development - multiple origins
CORS_ALLOWED_ORIGINS = http://localhost:3000,http://localhost:5173,http://localhost:8081

# Production - specific domains
CORS_ALLOWED_ORIGINS = https://yourdomain.com,https://app.yourdomain.com

# Development - all origins (not recommended for production)
CORS_ALLOWED_ORIGINS = *
```

## 🔔 Notification System

The system supports various notification types:

- **lock_status** - Lock/unlock events
- **status_alert** - Online/offline status changes
- **system_alert** - System-wide notifications
- **user_action** - User activity notifications

## 🚀 Production Deployment

1. Set `CI_ENVIRONMENT = production` in .env
2. Use strong JWT secrets (64+ characters)
3. Enable HTTPS
4. Configure CORS for specific domains
5. Set up PostgreSQL connection pooling
6. Configure proper logging levels
7. Set up monitoring and health checks

## 🧪 Testing

```bash
# Test authentication
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Test lock control
curl -X POST http://localhost:8080/api/locks/1/control \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action":"unlock"}'

# Test notifications
curl -X GET http://localhost:8080/api/notifications \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test lock status
curl -X GET http://localhost:8080/api/locks/status \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

**Built with CodeIgniter 4 + PostgreSQL + Real-time Notifications**

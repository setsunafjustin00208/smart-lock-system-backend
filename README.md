# Smart Lock Backend API

CodeIgniter 4 backend for ESP32-based smart lock system with JWT authentication and WebSocket communication.

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
# Edit .env with your database credentials
```

3. **Create database:**
```sql
CREATE DATABASE smartlocks;
CREATE USER postgres WITH PASSWORD 'postgres';
GRANT ALL PRIVILEGES ON DATABASE smartlocks TO postgres;
```

4. **Run migrations:**
```bash
php spark migrate
```

5. **Seed data:**
```bash
php spark db:seed UserSeeder
php spark db:seed LockSeeder
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

### Users (Admin only)
- `GET /api/users` - List users
- `POST /api/users` - Create user

### Locks
- `GET /api/locks` - List locks
- `GET /api/locks/{id}` - Get lock details
- `POST /api/locks/{id}/control` - Control lock

## 🧪 Test Credentials

```
Admin:   admin / admin123
Manager: manager / manager123  
User:    user / user123
Guest:   guest / guest123
```

## 📡 WebSocket Integration

ESP32 devices connect via WebSocket for real-time communication:

```javascript
// ESP32 connection
ws.send(JSON.stringify({
  type: 'hardware_register',
  hardware_id: 'ESP32_MAIN_001'
}));

// Send status update
ws.send(JSON.stringify({
  type: 'lock_status_update',
  hardware_id: 'ESP32_MAIN_001',
  status: {
    is_locked: true,
    battery_level: 85
  }
}));
```

## 🔒 Security Features

- JWT authentication with refresh tokens
- Argon2ID password hashing
- HMAC-SHA256 command signing
- AES-256 payload encryption
- Rate limiting (5 failed attempts = 30min lockout)

## 🏗️ Architecture

- **Framework:** CodeIgniter 4
- **Database:** PostgreSQL with JSONB
- **Authentication:** JWT tokens
- **Real-time:** WebSocket (Ratchet)
- **Security:** Hardware command signing

## 📊 Database Schema

```sql
-- Users with JSONB auth data
users (id, uuid, username, email, auth_data, profile_data)

-- Locks with JSONB config/status
locks (id, uuid, name, hardware_id, config_data, status_data)

-- User permissions per lock
user_lock_permissions (user_id, lock_id, permissions)

-- Activity logging
activity_logs (user_id, lock_id, action, details)
```

## 🔧 ESP32 Integration

Hardware devices communicate via:
1. **WebSocket** (primary) - Real-time bidirectional
2. **HTTP REST** (fallback) - Direct API calls

Command structure:
```json
{
  "command": "unlock",
  "lock_id": 1,
  "hardware_id": "ESP32_MAIN_001",
  "timestamp": 1640995200,
  "signature": "hmac_sha256_signature"
}
```

## 🚀 Production Deployment

1. Set `CI_ENVIRONMENT = production` in .env
2. Use strong JWT secrets
3. Enable HTTPS
4. Configure PostgreSQL connection pooling
5. Set up Redis for sessions (optional)

---

**Built with CodeIgniter 4 + PostgreSQL + ESP32 Integration**

# ReWear Backend - Laravel Authentication System

## Project Overview

**ReWear** is a sustainable fashion marketplace that connects sellers, buyers, delivery drivers, and charities to facilitate affordable clothing access while reducing textile waste.

This repository contains the Laravel backend API with a complete authentication system matching the NestJS reference implementation.

---

## Phase 1: Foundation & Setup ✅ COMPLETED

### What Was Built

#### 1. Database Migrations
- ✅ `email_verifications` table - OTP verification system
- ✅ `refresh_tokens` table - Token management
- ✅ Enhanced `users` table - Auth columns added

#### 2. Models
- ✅ `User` model with JWT implementation
- ✅ `RefreshToken` model with token management
- ✅ `EmailVerification` model with OTP handling
- ✅ All relationships defined
- ✅ Scopes and helper methods

#### 3. Configuration Files
- ✅ `config/jwt.php` - JWT configuration
- ✅ `config/auth.php` - Auth guards and providers
- ✅ `config/mail.php` - Email configuration
- ✅ `.env.example` - Environment template

#### 4. Project Structure
```
rewear-backend/
├── app/
│   ├── Models/
│   │   ├── User.php                    ✅
│   │   ├── RefreshToken.php            ✅
│   │   └── EmailVerification.php       ✅
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   └── Services/
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_email_verifications_table.php    ✅
│       ├── 2024_01_01_000002_create_refresh_tokens_table.php         ✅
│       └── 2024_01_01_000003_add_auth_columns_to_users_table.php     ✅
├── config/
│   ├── jwt.php           ✅
│   ├── auth.php          ✅
│   └── mail.php          ✅
├── composer.json         ✅
└── .env.example          ✅
```

---

## Authentication Architecture

### Token Strategy: JWT + Refresh Tokens

Matches the NestJS module exactly:

**Access Token (JWT)**
- Type: JSON Web Token (stateless)
- Lifespan: 1 hour (configurable)
- Storage: Client-side only
- Verification: No database query
- Use: All API requests

**Refresh Token**
- Type: Random string (database-backed)
- Lifespan: 30 days (configurable)
- Storage: Database + Client
- Verification: Database query
- Use: Get new access token

### Authentication Flow

```
Registration:
1. User submits email, password, phone, full_name
2. System generates 6-digit OTP
3. OTP sent to email (expires in 5 min)
4. User verifies OTP
5. Account created with hashed password
6. JWT access + refresh tokens returned

Login:
1. User submits email + password
2. System validates credentials
3. Check email_verified_at
4. Check account not locked
5. JWT access + refresh tokens returned

Token Refresh:
1. Client sends refresh token
2. System validates token (DB query)
3. Generates new JWT access token
4. Optionally rotates refresh token
5. Returns new access token

Logout:
1. Client sends refresh token
2. System revokes token (soft delete)
3. JWT expires naturally

Logout All Devices:
1. System revokes all user's refresh tokens
2. All JWTs expire naturally
```

---

## Database Schema

### New Tables

#### email_verifications
```sql
id              BIGSERIAL PRIMARY KEY
email           VARCHAR(255) indexed
code            VARCHAR(6)
expires_at      TIMESTAMP indexed
attempts        INTEGER (max 5)
verified_at     TIMESTAMP nullable
created_at      TIMESTAMP
```

#### refresh_tokens
```sql
id              BIGSERIAL PRIMARY KEY
user_id         BIGINT FK -> users(id)
token           VARCHAR(255) UNIQUE
expires_at      TIMESTAMP indexed
device_name     VARCHAR(255) nullable
ip_address      VARCHAR(45) nullable
user_agent      TEXT nullable
last_used_at    TIMESTAMP nullable
revoked_at      TIMESTAMP nullable indexed
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### Modified Tables

#### users (additions)
```sql
password            VARCHAR(255) nullable
email_verified_at   TIMESTAMP nullable
last_login_at       TIMESTAMP nullable
login_attempts      INTEGER default 0
locked_until        TIMESTAMP nullable
```

---

## Environment Setup

### Required Environment Variables

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=rewear_db
DB_USERNAME=postgres
DB_PASSWORD=root

# JWT
JWT_SECRET=your-secret-key-here
JWT_EXPIRES_IN=3600
REFRESH_TOKEN_EXPIRES_DAYS=30

# Email (Hostinger SMTP)
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@nazerapps.com
MAIL_PASSWORD=QWE@2025arc
MAIL_ENCRYPTION=ssl
```

---

## Installation Steps

### 1. Copy Environment File
```bash
cp .env.example .env
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Generate Application Key
```bash
php artisan key:generate
```

### 4. Generate JWT Secret
```bash
php artisan jwt:secret
```

### 5. Create Database
```sql
CREATE DATABASE rewear_db;
```

### 6. Run Migrations
```bash
php artisan migrate
```

### 7. Start Development Server
```bash
php artisan serve
```

---

## Next Steps: Phase 2

### Phase 2: User Registration with OTP

**Endpoints to Build:**
```
POST /api/auth/register-code  - Send OTP to email
POST /api/auth/register       - Verify OTP & create account
POST /api/auth/resend-code    - Resend expired OTP
```

**What We'll Create:**
1. **Controllers**
   - `AuthController.php` - Registration endpoints

2. **Requests (Validation)**
   - `RegisterCodeRequest.php` - Email validation
   - `RegisterRequest.php` - Full registration validation
   - `ResendCodeRequest.php` - Resend validation

3. **Services**
   - `AuthService.php` - Business logic
   - `EmailVerificationService.php` - OTP management
   - `TokenService.php` - JWT + Refresh token generation

4. **Mail**
   - `VerificationCodeMail.php` - OTP email template

5. **Middleware**
   - Rate limiting for OTP requests

**Features:**
- ✅ Input validation (email, password, phone, name)
- ✅ Check email doesn't exist
- ✅ Generate 6-digit OTP
- ✅ Send email with OTP template
- ✅ OTP expiration (5 minutes)
- ✅ Rate limiting (5 codes per 15 min)
- ✅ Verify OTP and create account
- ✅ Generate JWT access + refresh tokens
- ✅ Return tokens + user data

**Timeline:** 1-2 days

---

## Security Features

### Implemented
- ✅ Password hashing (bcrypt)
- ✅ JWT token signing
- ✅ OTP expiration (5 min)
- ✅ OTP attempt limiting (5 max)
- ✅ Account lockout (5 failed logins → 15 min lock)
- ✅ Refresh token expiration
- ✅ Refresh token revocation
- ✅ Multi-device session management

### To Implement (Next Phases)
- ⏳ Rate limiting on registration
- ⏳ Rate limiting on login
- ⏳ IP-based rate limiting
- ⏳ Email verification enforcement
- ⏳ CORS configuration
- ⏳ API key validation (admin routes)

---

## Package Dependencies

### Core
- `laravel/framework` ^11.0 - Framework
- `tymon/jwt-auth` ^2.1 - JWT authentication
- `laravel/sanctum` ^4.0 - Token management (backup)

### Development
- `phpunit/phpunit` ^11.0 - Testing
- `laravel/pint` ^1.13 - Code formatting

---

## API Documentation (Coming in Phase 7)

### Planned Endpoints

**Authentication**
- POST /api/auth/register-code
- POST /api/auth/register
- POST /api/auth/resend-code
- POST /api/auth/login
- POST /api/auth/refresh-token
- POST /api/auth/logout
- POST /api/auth/logout-all
- POST /api/auth/validate
- GET /api/auth/me

**Profile**
- PUT /api/auth/profile
- PUT /api/auth/password

**Admin**
- POST /api/admin/charity/create

---

## Testing Strategy (Phase 7)

### Unit Tests
- EmailVerification model tests
- RefreshToken model tests
- User model tests
- Service layer tests

### Feature Tests
- Registration flow
- Login flow
- Token refresh flow
- Logout flow
- Rate limiting
- Email sending

### Integration Tests
- Complete auth workflows
- Multi-device scenarios
- Security tests

**Target Coverage:** >80%

---

## Known Issues / TODO

- [ ] Need to create actual Laravel application structure (artisan commands)
- [ ] Install composer dependencies
- [ ] Set up proper Laravel routing
- [ ] Create mail views/templates
- [ ] Add logging configuration
- [ ] Set up exception handling
- [ ] Create database seeders
- [ ] Add API versioning

---

## Development Timeline

| Phase | Description | Status | Duration |
|-------|-------------|--------|----------|
| 1 | Foundation & Setup | ✅ DONE | 1 day |
| 2 | Registration + OTP | 🔄 NEXT | 1-2 days |
| 3 | Login + Logout | ⏳ | 1 day |
| 4 | Token Management | ⏳ | 1 day |
| 5 | Profile Management | ⏳ | 1 day |
| 6 | Admin Features | ⏳ | 0.5 day |
| 7 | Testing + Docs | ⏳ | 1-2 days |

**Total Estimated:** 6.5-9 days (Week 1-2 of project)

---

## Project Status

### Phase 1: Foundation ✅ COMPLETED

**What's Done:**
- ✅ Database migrations created
- ✅ Models with relationships
- ✅ JWT configuration
- ✅ Auth configuration
- ✅ Mail configuration
- ✅ Environment template
- ✅ Project structure

**What's Next:**
Phase 2 starts immediately - Building the registration endpoints with OTP verification system.

---

## Support & Contact

- **Project:** ReWear - Computer Science Final Project
- **Timeline:** 2 months
- **Tech Stack:** Laravel 11 + PostgreSQL + JWT
- **Target:** Academic Presentation

---

## License

MIT License - Academic Project

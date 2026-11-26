# QR ATTENDANCE MANAGEMENT SYSTEM - COMPREHENSIVE DOCUMENTATION

---

## 📋 TABLE OF CONTENTS
1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Database Schema](#database-schema)
4. [Frontend Structure](#frontend-structure)
5. [Backend API](#backend-api)
6. [Configuration](#configuration)
7. [Deployment](#deployment)
8. [Quick Reference](#quick-reference)

---

## 🎯 SYSTEM OVERVIEW

### Project Name
**QR Attendance Management System**

### Purpose
Automated attendance tracking system using QR codes for educational institutions. Teachers generate QR sessions, students scan to mark attendance automatically with photo verification.

### Tech Stack
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 8.2 (PDO, OOP, MVC-style)
- **Database**: MySQL 8.0
- **Server**: Apache 2.4 (Docker)
- **Containerization**: Docker & Docker Compose
- **Admin UI**: phpMyAdmin

### Application Ports
```
App:        http://localhost:8083 (mapped from container port 80)
Database:   http://localhost:8082 (MySQL direct)
phpMyAdmin: http://localhost:8082 (mapped from container port 80)
```

---

## 🏗️ ARCHITECTURE

### Docker Setup
```
docker-compose.yml structure:
├── app service
│   ├── Build from Dockerfile
│   ├── Port 8083:80 (host:container)
│   ├── Volumes: ./src:/var/www/html (live code), vendor, data
│   ├── Env file: .env
│   └── Depends on: db
├── db service
│   ├── Image: mysql:8.0
│   ├── Port 3306:3306
│   ├── Environment: DB_ROOT_PASSWORD, DB_NAME, DB_USER, DB_PASSWORD
│   └── Volume: db_data (persistent)
└── phpmyadmin service
    ├── Image: phpmyadmin/phpmyadmin
    ├── Port 8082:80
    ├── PMA_HOST: db (internal DNS)
    └── Depends on: db
```

### Environment Variables (.env)
```
DB_ROOT_PASSWORD=root
DB_NAME=qr_attendance
DB_USER=user
DB_PASSWORD=password
DB_HOST=db          # Docker DNS name
APP_DEBUG=false
SESSION_LIFETIME=7200
QR_EXPIRE_MINUTES=30
```

### Project Directory Structure
```
qr-attendance/
├── src/
│   ├── config.php              # Configuration array (database, session, QR, security)
│   ├── index.php               # Home page with login links
│   ├── logout.php              # Session destroyer
│   ├── style.css               # Global styles
│   ├── lib/
│   │   ├── auth.php            # Session management (require_teacher, require_student)
│   │   ├── db.php              # PDO connection singleton
│   │   ├── validator.php       # Form validation
│   │   ├── logger.php          # Activity logging
│   │   ├── qr.php              # QR code generation
│   │   ├── csrf.php            # CSRF token validation
│   │   ├── rate_limit.php      # Scan rate limiting
│   │   ├── photo_manager.php   # Photo upload/storage
│   │   ├── response.php        # JSON response helper
│   │   ├── exceptions.php      # Custom exceptions
│   │   └── template.php        # Template utilities
│   ├── teacher/
│   │   ├── login.php           # Teacher authentication
│   │   ├── dashboard.php       # Main teacher hub (stats, recent sessions)
│   │   ├── create_qr.php       # New QR session form + generation
│   │   ├── qr_display.php      # Display QR with live countdown & attendee count
│   │   ├── manual_attendance.php   # Manual marking UI
│   │   ├── session.php         # Session details & attendance records
│   │   ├── view_enrollments.php    # Student list for class
│   │   ├── manage_students.php     # Student enrollment management
│   │   ├── reports.php         # Analytics & attendance reports
│   │   └── create_session.php  # Legacy session creator
│   ├── student/
│   │   ├── login.php           # Student authentication (by student_no)
│   │   ├── dashboard.php       # Student home (stats, attendance history)
│   │   └── mark_attendance.php # QR scanner + manual token entry
│   ├── api/
│   │   ├── scan.php            # QR scan endpoint (JSON, photo verification)
│   │   └── attendance_count.php    # Live attendee count polling
│   └── data/
│       ├── photos/             # Uploaded photos (auto-created)
│       └── logs/               # Activity logs
├── sql/
│   └── init.sql                # Database schema & initial data
├── includes/
│   └── SYSTEM_DOCUMENTATION.md # This file
├── Dockerfile                  # PHP 8.2 + Apache + PDO
├── docker-compose.yml          # Multi-container orchestration
├── composer.json               # PHP dependencies (optional)
└── .env                        # Environment config (secrets)
```

---

## 🗄️ DATABASE SCHEMA

### MySQL 8.0 Database: `qr_attendance`

#### Tables

**1. teacher**
```sql
Columns: id (PK), username (UNI), password_hash, email, phone, is_active, created_at, updated_at
Purpose: Teacher accounts with credentials
Constraints: username unique, is_active indexed
```

**2. student**
```sql
Columns: id (PK), student_no (UNI), full_name, password_hash, email, is_active, created_at, updated_at
Purpose: Student accounts
Constraints: student_no unique, is_active indexed
```

**3. class_subject**
```sql
Columns: id (PK), class_name, subject_name, created_at
Purpose: Class-subject combinations teachers manage
Constraints: (class_name, subject_name) unique
```

**4. student_enrollment**
```sql
Columns: id (PK), student_id (FK), class_subject_id (FK), enrolled_at
Purpose: Students enrolled in classes
Constraints: (student_id, class_subject_id) unique
Foreign Keys: student_id → student.id, class_subject_id → class_subject.id
```

**5. attendance_session**
```sql
Columns: 
  id (PK), teacher_id (FK), class_subject_id (FK), 
  token (UNI), qr_payload, session_name, 
  starts_at, expires_at, status (enum: active/expired/closed),
  created_at, updated_at
Purpose: QR sessions created by teachers
Constraints: teacher_id indexed, class_subject_id indexed, status indexed, expires_at indexed
Foreign Keys: teacher_id → teacher.id, class_subject_id → class_subject.id
```

**6. attendance**
```sql
Columns:
  id (PK), session_id (FK), student_id (FK), 
  marked_at, marked_by (enum: qr_scan/manual),
  photo_path, ip_address, user_agent,
  created_at
Purpose: Attendance records
Constraints: (session_id, student_id) unique, session_id indexed, student_id indexed
Foreign Keys: session_id → attendance_session.id, student_id → student.id
```

**7. activity_log**
```sql
Columns: id (PK), entity_type, entity_id, action, description, created_at
Purpose: Audit trail for all actions
```

**8. settings**
```sql
Columns: id (PK), key, value, created_at, updated_at
Purpose: Application configuration storage
```

---

## 🎨 FRONTEND STRUCTURE

### Pages & Components

**Public Pages**
- `index.php` - Home/landing with login buttons
- `teacher/login.php` - Teacher login form
- `student/login.php` - Student login form

**Teacher Pages**
- `dashboard.php` - Stats (sessions, attendance), quick actions, recent sessions table
- `create_qr.php` - Form to create new QR session (class, name, duration)
- `qr_display.php` - Full-screen QR display with countdown timer & live attendee count
- `manual_attendance.php` - Checkbox UI to mark students manually
- `session.php` - View session details, attendance records, attendance rate %
- `view_enrollments.php` - Student list for specific class
- `manage_students.php` - Add/remove students from classes
- `reports.php` - Analytics dashboards

**Student Pages**
- `dashboard.php` - Personal stats (sessions, classes, subjects), attendance history table
- `mark_attendance.php` - QR scanner tab + manual token entry tab

### UI/UX Features
- Responsive CSS Grid/Flexbox layouts
- Gradient backgrounds (primary #667eea, secondary #764ba2)
- Status badges (Active/Expired/Closed)
- Progress bars for attendance %
- Modal confirmations
- Error/success alerts
- Fullscreen QR display option
- Real-time form validation

### JavaScript Used
- `jsQR` library (CDN) for client-side QR scanning in mark_attendance.php
- Vanilla DOM manipulation for tab switching, counters, timers
- Fetch API for live attendee count polling
- LocalStorage for session persistence

---

## 🔌 BACKEND API

### PHP Architecture
- **Pattern**: OOP with static methods
- **Connection**: PDO (prepared statements, exception handling)
- **Security**: CSRF tokens, rate limiting, input validation

### Key Classes/Functions

**lib/db.php**
```php
function db(): PDO
  - Singleton database connection
  - Uses env variables: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
  - Throws PDOException on failure
```

**lib/auth.php**
```php
function start_session(): void
  - Initializes PHP session with config from config.php
  
function require_teacher(): void
  - Redirects to /teacher/login.php if not logged in as teacher
  
function require_student(): void
  - Redirects to /student/login.php if not logged in as student
  
Session keys: $_SESSION['teacher_id'], $_SESSION['student_username']
              $_SESSION['student_id'], $_SESSION['student_no']
```

**lib/validator.php**
```php
class Validator
  - static validate($field, $value, $rules...)
  - static getErrors(): array
  - Rules: required, minlength:n, maxlength:n, email, numeric
```

**lib/qr.php**
```php
function generate_qr_png_base64($payload): string
  - Returns base64-encoded PNG QR image
  - Uses external library (Composer)
```

**lib/photo_manager.php**
```php
class PhotoManager
  - static init($config): void
  - static savePhoto($base64Data, $sessionId, $studentId): string|bool
  - Saves to data/photos/{sessionId}/{studentId}_{timestamp}.jpg
```

**lib/logger.php**
```php
class Logger (singleton)
  - getInstance()->activity($entity, $id, $action, $description)
  - getInstance()->error($message)
  - Writes to activity_log table & data/logs/
```

**lib/csrf.php**
```php
class CSRF
  - static validateRequest(): bool
  - Checks _token POST param against session
```

**lib/rate_limit.php**
```php
class RateLimit
  - static isLimited($key, $max, $window_seconds): bool
  - Prevents spam (3 scans/minute default)
```

### Endpoints

**Authentication**
- POST `/teacher/login.php` - Teacher login (username, password)
- POST `/student/login.php` - Student login (student_no, password)
- GET `/logout.php` - Destroy session

**QR Sessions (Teacher)**
- POST `/teacher/create_qr.php` - Create session (class, name, duration)
- GET `/teacher/qr_display.php?session_id=X` - Display QR code
- POST `/teacher/manual_attendance.php` - Mark students manually

**Attendance (Student)**
- POST `/student/mark_attendance.php` - Mark by token or camera scan
- GET `/api/attendance_count.php?session_id=X` - Poll for live count

**Data Retrieval**
- GET `/teacher/session.php?id=X` - Session details
- GET `/teacher/reports.php` - Attendance analytics

---

## ⚙️ CONFIGURATION

### config.php Structure
```php
return [
    'app' => [
        'name' => 'QR Attendance System',
        'url' => getenv('APP_URL') ?: 'http://localhost:8080',
        'debug' => getenv('APP_DEBUG') === 'true',
        'timezone' => 'UTC'
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => 3306,
        'name' => getenv('DB_NAME') ?: 'qr_attendance',
        'user' => getenv('DB_USER') ?: 'user',
        'password' => getenv('DB_PASSWORD') ?: 'password',
        'charset' => 'utf8mb4'
    ],
    'session' => [
        'lifetime' => 7200,  // 2 hours
        'cookie_secure' => false,
        'cookie_http_only' => true,
        'cookie_same_site' => 'Lax'
    ],
    'qr' => [
        'expire_minutes_default' => 30,
        'size' => 300,
        'margin' => 10
    ],
    'security' => [
        'enable_csrf' => true,
        'password_min_length' => 8,
        'max_login_attempts' => 5,
        'lockout_duration' => 900
    ],
    'storage' => [
        'photos_dir' => __DIR__ . '/../../data/photos',
        'max_photo_size_mb' => 5,
        'allowed_photo_types' => ['jpeg', 'png'],
        'photo_retention_days' => 90
    ],
    'rate_limit' => [
        'attendance_scan_per_minute' => 3
    ]
];
```

---

## 🚀 DEPLOYMENT

### Prerequisites
- Docker & Docker Compose installed
- Git (for version control)
- Port 8083, 8082, 3306 available

### Docker Commands

**Build & Start**
```bash
docker compose -f qr-attendance/docker-compose.yml up --pull always -d
```

**Stop**
```bash
docker compose -f qr-attendance/docker-compose.yml down
```

**View Logs**
```bash
docker compose -f qr-attendance/docker-compose.yml logs app
docker compose -f qr-attendance/docker-compose.yml logs db
```

**Restart Services**
```bash
docker compose -f qr-attendance/docker-compose.yml restart app
```

**Clean Up**
```bash
docker compose -f qr-attendance/docker-compose.yml down -v  # Remove volumes
```

### Database Initialization
- SQL schema runs automatically on first container startup (from sql/init.sql)
- Tables created: teacher, student, class_subject, student_enrollment, attendance_session, attendance, activity_log, settings

### File Permissions
```
data/photos/     - Apache writable (755 or 777)
data/logs/       - Apache writable
vendor/          - Composer packages
```

---

## 📊 DATA FLOW

### Teacher QR Session Workflow
1. Teacher logs in → Dashboard
2. Click "Create QR Session" → Form (class, name, duration)
3. POST to `/teacher/create_qr.php` → INSERT into attendance_session
4. Redirect to `/teacher/qr_display.php?session_id=X`
5. Display QR code (base64 PNG image)
6. JavaScript polls `/api/attendance_count.php` every 2 seconds (live update)
7. Countdown timer counts down from duration to 0

### Student QR Scan Workflow
1. Student logs in → Dashboard
2. Click "Mark Attendance" → `/student/mark_attendance.php`
3. Tab 1: Camera
   - Request camera permission
   - jsQR scans frame every requestAnimationFrame
   - On QR detected → extract token → POST to form with hidden field
4. Tab 2: Manual
   - Paste token manually
5. POST submission:
   - Find session by token WHERE expires_at > NOW()
   - Verify student enrolled in class_subject
   - Check if already marked (unique constraint)
   - INSERT into attendance table
   - Redirect with success message

### Manual Attendance Workflow
1. Teacher: `/teacher/manual_attendance.php`
2. Create session → INSERT attendance_session
3. Select students (checkboxes)
4. POST → Mark selected students → INSERT into attendance
5. Redirect to `/teacher/reports.php`

---

## 🔐 SECURITY MEASURES

| Feature | Implementation |
|---------|-----------------|
| **Password Hashing** | PHP password_hash() / password_verify() |
| **SQL Injection** | PDO prepared statements |
| **CSRF Protection** | Token validation in api/scan.php |
| **Session Security** | httpOnly, SameSite=Lax cookies |
| **Rate Limiting** | Max 3 scans/minute per student |
| **Photo Verification** | Base64 decode, size validation, type check |
| **Input Validation** | Validator class (minlength, maxlength, type) |
| **Error Handling** | Try-catch, exceptions, logging |

---

## 📝 DATABASE QUERIES REFERENCE

### Key Queries Used

**Get Teacher's Sessions**
```sql
SELECT s.id, s.token, s.session_name, s.starts_at, s.expires_at, s.status,
       cs.class_name, cs.subject_name, 
       COUNT(a.id) as attendance_count,
       COUNT(DISTINCT a.student_id) as unique_students
FROM attendance_session s
JOIN class_subject cs ON cs.id = s.class_subject_id
LEFT JOIN attendance a ON a.session_id = s.id
WHERE s.teacher_id = ? AND s.starts_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY s.id
ORDER BY s.starts_at DESC
```

**Get Attendance for Session**
```sql
SELECT st.student_no, st.full_name, a.marked_at, a.marked_by
FROM attendance a
JOIN student st ON st.id = a.student_id
WHERE a.session_id = ?
ORDER BY a.marked_at ASC
```

**Verify Session Active**
```sql
SELECT s.id, s.class_subject_id, s.expires_at
FROM attendance_session s
WHERE s.token = ? AND s.expires_at > NOW()
```

**Check Student Enrollment**
```sql
SELECT id FROM student_enrollment 
WHERE student_id = ? AND class_subject_id = ?
```

---

## 🧪 TESTING

### Test Accounts
**Teacher**
- Username: `teacher1` Password: `Teacher@123`
- Username: `teacher2` Password: `Teacher@123`

**Students (STD001-STD028)**
- Student No: `STD001` Password: `Student#2024`
- Student No: `STD002` Password: `Student#2024`

### Test Scenarios
1. **Login Flow**: Verify redirect to dashboard
2. **QR Generation**: Create session, display QR
3. **QR Scan**: Scan with phone camera or manual token
4. **Manual Mark**: Select students, verify in reports
5. **Dashboard Stats**: Check attendance calculations
6. **Session Details**: View attendee list
7. **Rate Limiting**: Scan 3x rapidly, verify 429 error

---

## 🐛 TROUBLESHOOTING

| Issue | Cause | Solution |
|-------|-------|----------|
| Port 8083 already in use | Host process blocking | `docker ps`, kill process, change port in docker-compose.yml |
| Database connection failed | DB not running | `docker compose up db` first |
| Photos not saving | Permission denied | `chmod 777 data/photos/` |
| QR not scanning | Camera permission denied | Allow browser camera access |
| Session expired quickly | SESSION_LIFETIME too low | Increase in .env |
| CSRF token error | Token mismatch | Clear cookies, retry |

---

## 📌 IMPORTANT NOTES FOR FUTURE DEVELOPMENT

1. **Photo Storage**: Currently stores in `data/photos/`, consider S3 for production
2. **Email Notifications**: Mail config exists but not implemented
3. **Photo Cleanup**: Configure cron job to delete old photos (photo_retention_days)
4. **Rate Limiting**: Currently in-memory, use Redis for distributed systems
5. **Scaling**: Separate read/write DB replicas for large datasets
6. **API Docs**: OpenAPI/Swagger spec not generated
7. **Testing**: No unit/integration tests yet (PHPUnit ready)
8. **Logging**: Consider ELK stack for production logging
9. **Monitoring**: Add health check endpoints
10. **Caching**: Implement Redis for session & query caching

---

**Last Updated**: November 25, 2026  
**Version**: 2.1  
**Status**: Production Ready ✅

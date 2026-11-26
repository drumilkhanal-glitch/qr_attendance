<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'QR Attendance System',
        'version' => '2.1',
        'url' => getenv('APP_URL') ?: 'http://localhost:8080',
        'debug' => getenv('APP_DEBUG') === 'true',
        'timezone' => 'UTC',
    ],
    
    'database' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'qr_attendance',
        'user' => getenv('DB_USER') ?: 'user',
        'password' => getenv('DB_PASSWORD') ?: 'password',
        'charset' => 'utf8mb4',
    ],
    
    'session' => [
        'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 7200), // 2 hours
        'cookie_secure' => (bool)(getenv('SESSION_SECURE') ?: false),
        'cookie_http_only' => true,
        'cookie_same_site' => 'Lax',
    ],
    
    'qr' => [
        'expire_minutes_default' => (int)(getenv('QR_EXPIRE_MINUTES') ?: 30),
        'size' => 300,
        'margin' => 10,
    ],
    
    'security' => [
        'enable_csrf' => true,
        'password_min_length' => 8,
        'password_require_uppercase' => true,
        'password_require_numbers' => true,
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
    ],
    
    'storage' => [
        'photos_dir' => getenv('PHOTOS_DIR') ?: __DIR__ . '/../../data/photos',
        'max_photo_size_mb' => (int)(getenv('MAX_PHOTO_SIZE_MB') ?: 5),
        'allowed_photo_types' => ['jpeg', 'png'],
        'photo_retention_days' => (int)(getenv('PHOTO_RETENTION_DAYS') ?: 90),
    ],
    
    'rate_limit' => [
        'attendance_scan_per_minute' => (int)(getenv('RATE_LIMIT_SCANS') ?: 3),
    ],

    'mail' => [
        'enabled' => (bool)(getenv('MAIL_ENABLED') ?: false),
        'driver' => getenv('MAIL_DRIVER') ?: 'smtp',
        'host' => getenv('MAIL_HOST') ?: 'smtp.mailtrap.io',
        'port' => (int)(getenv('MAIL_PORT') ?: 587),
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'from' => getenv('MAIL_FROM') ?: 'noreply@qrattendance.local',
    ],
];

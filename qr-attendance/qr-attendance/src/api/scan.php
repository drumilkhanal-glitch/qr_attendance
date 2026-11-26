<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/logger.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/rate_limit.php';
require_once __DIR__ . '/../lib/photo_manager.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    require_student();

    // Validate CSRF token
    if (!CSRF::validateRequest()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        exit;
    }

    $studentId = (int)$_SESSION['student_id'];
    $config = require __DIR__ . '/../config.php';

    // Rate limiting: max 3 scans per minute per student
    $rateLimitKey = 'scan_' . $studentId;
    $maxScansPerMinute = $config['rate_limit']['attendance_scan_per_minute'] ?? 3;
    
    if (RateLimit::isLimited($rateLimitKey, $maxScansPerMinute, 60)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many scan attempts. Please wait before trying again.',
            'remaining' => 0
        ]);
        exit;
    }

    $payload = trim($_POST['qr_payload'] ?? '');
    $photoData = $_POST['photo_data'] ?? '';

    if (!$payload) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'QR payload is required']);
        exit;
    }

    if (!str_starts_with($payload, "SESSION_TOKEN:")) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid QR payload format']);
        exit;
    }

    if (!$photoData) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Photo capture is required']);
        exit;
    }

    $token = substr($payload, strlen("SESSION_TOKEN:"));
    $pdo = db();

    // Verify session and retrieve session details
    $stmt = $pdo->prepare("
        SELECT s.id, s.class_subject_id, s.expires_at
        FROM attendance_session s
        WHERE s.token = ? AND s.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session not found or expired']);
        exit;
    }

    // Check if student is enrolled in this class
    $stmt = $pdo->prepare("
        SELECT id FROM student_enrollment 
        WHERE student_id = ? AND class_subject_id = ?
    ");
    $stmt->execute([$studentId, $session['class_subject_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this class']);
        exit;
    }

    // Check if already marked
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE session_id = ? AND student_id = ?");
    $stmt->execute([$session['id'], $studentId]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'You have already marked attendance for this session']);
        exit;
    }

    // Initialize PhotoManager with config
    PhotoManager::init($config['storage']);

    // Save photo securely
    $photoPath = PhotoManager::savePhoto($photoData, (int)$session['id'], $studentId);
    
    if (!$photoPath) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid photo format or file too large']);
        exit;
    }

    // Mark attendance (store path only, not blob)
    $insert = $pdo->prepare("
        INSERT INTO attendance (session_id, student_id, marked_at, marked_by, photo_path, ip_address, user_agent)
        VALUES (?, ?, NOW(), 'qr_scan', ?, ?, ?)
    ");
    $insert->execute([
        $session['id'], 
        $studentId, 
        $photoPath,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);

    Logger::getInstance()->activity('student', $studentId, 'attendance_marked', 
        "QR attendance marked with photo for session {$session['id']}");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Attendance marked successfully with photo verification',
        'data' => [
            'session_id' => $session['id'],
            'marked_at' => date('Y-m-d H:i:s'),
            'remaining_attempts' => RateLimit::getRemaining($rateLimitKey, $maxScansPerMinute, 60)
        ]
    ]);

} catch (Exception $e) {
    Logger::getInstance()->error('Attendance marking error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

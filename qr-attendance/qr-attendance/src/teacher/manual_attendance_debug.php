<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_teacher();
require_once __DIR__ . '/../lib/db.php';

$teacherId = (int)$_SESSION['teacher_id'];
$pdo = db();

$message = '';
$messageType = '';
$debug_info = '';

// Get all students
$stmt = $pdo->query("SELECT id, student_no, full_name FROM student WHERE is_active = TRUE ORDER BY student_no");
$allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// DEBUGGING: Log session state
$debug_info .= "Current Session ID in \$_SESSION: " . ($_SESSION['current_session_id'] ?? 'NOT SET') . "\n";

// Handle form submission for manual attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $debug_info .= "POST Action: $action\n";
    
    if ($action === 'create_manual_session') {
        $classSubjectId = (int)($_POST['class_subject_id'] ?? 0);
        $debug_info .= "Creating session for class_subject_id: $classSubjectId\n";
        
        if ($classSubjectId > 0) {
            $token = bin2hex(random_bytes(32));
            $qrPayload = "SESSION_TOKEN:" . $token;
            
            $stmt = $pdo->prepare("
                INSERT INTO attendance_session (teacher_id, class_subject_id, token, qr_payload, starts_at, expires_at)
                VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ");
            $stmt->execute([
                $teacherId,
                $classSubjectId,
                $token,
                $qrPayload
            ]);
            
            $newSessionId = (int)$pdo->lastInsertId();
            $_SESSION['current_session_id'] = $newSessionId;
            $debug_info .= "Session created with ID: $newSessionId\n";
            $debug_info .= "Set in \$_SESSION['current_session_id']: " . $_SESSION['current_session_id'] . "\n";
            
            $message = "Session created (ID: $newSessionId). Select students to mark attendance.";
            $messageType = 'success';
        }
    } elseif ($action === 'mark_attendance') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $studentIds = $_POST['student_ids'] ?? [];
        $debug_info .= "Marking attendance for session $sessionId with " . count($studentIds) . " students\n";
        
        if ($sessionId > 0 && !empty($studentIds)) {
            $count = 0;
            foreach ($studentIds as $studentId) {
                $studentId = (int)$studentId;
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (session_id, student_id, marked_at, marked_by)
                        VALUES (?, ?, NOW(), 'manual')
                    ");
                    $stmt->execute([$sessionId, $studentId]);
                    $count++;
                } catch (PDOException $e) {
                    $debug_info .= "Failed to mark student $studentId: " . $e->getMessage() . "\n";
                }
            }
            $message = "Marked attendance for $count student(s).";
            $messageType = 'success';
            $debug_info .= "Successfully marked: $count students\n";
        }
    } elseif ($action === 'end_session') {
        unset($_SESSION['current_session_id']);
        $message = 'Session ended.';
        $messageType = 'info';
        $debug_info .= "Session ended. Cleared \$_SESSION['current_session_id']\n";
    }
}

// Get current session if exists
$currentSessionId = $_SESSION['current_session_id'] ?? null;
$currentSession = null;
$sessionAttendees = [];
$enrolledStudents = [];

$debug_info .= "\nAfter POST processing:\n";
$debug_info .= "currentSessionId from \$_SESSION: " . ($currentSessionId ?? 'NULL') . "\n";

if ($currentSessionId) {
    $stmt = $pdo->prepare("
        SELECT s.*, cs.class_name, cs.subject_name, 
               (SELECT COUNT(*) FROM attendance WHERE session_id = s.id) as total_marked
        FROM attendance_session s
        JOIN class_subject cs ON cs.id = s.class_subject_id
        WHERE s.id = ? AND s.teacher_id = ?
    ");
    $stmt->execute([$currentSessionId, $teacherId]);
    $currentSession = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentSession) {
        $debug_info .= "Found active session: " . $currentSession['id'] . "\n";
        
        // Get already marked students
        $stmt = $pdo->prepare("
            SELECT student_id FROM attendance WHERE session_id = ?
        ");
        $stmt->execute([$currentSessionId]);
        $sessionAttendees = array_map(fn($row) => $row['student_id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        $debug_info .= "Already marked students: " . count($sessionAttendees) . "\n";
        
        // Get students enrolled in this class
        $stmt = $pdo->prepare("
            SELECT s.id, s.student_no, s.full_name
            FROM student s
            JOIN student_enrollment se ON se.student_id = s.id
            WHERE se.class_subject_id = ? AND s.is_active = TRUE
            ORDER BY s.student_no
        ");
        $stmt->execute([$currentSession['class_subject_id']]);
        $enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug_info .= "Enrolled students in class: " . count($enrolledStudents) . "\n";
    } else {
        $debug_info .= "No active session found with ID $currentSessionId\n";
    }
}

// Get class subjects
$stmt = $pdo->query("SELECT id, class_name, subject_name FROM class_subject ORDER BY class_name, subject_name");
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manual Attendance - DEBUG</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .debug-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
            color: #333;
            white-space: pre-wrap;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="debug-box">
        🔍 DEBUG INFO:
        <?= htmlspecialchars($debug_info) ?>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px;">
            <h2>Current Session Status</h2>
            <p><strong>Session Active:</strong> <?= $currentSession ? 'YES' : 'NO' ?></p>
            <?php if ($currentSession): ?>
                <p><strong>Session ID:</strong> <?= $currentSession['id'] ?></p>
                <p><strong>Class:</strong> <?= $currentSession['class_name'] ?></p>
                <p><strong>Subject:</strong> <?= $currentSession['subject_name'] ?></p>
                <p><strong>Total Marked:</strong> <?= $currentSession['total_marked'] ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

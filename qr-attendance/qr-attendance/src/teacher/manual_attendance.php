<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_teacher();
require_once __DIR__ . '/../lib/db.php';

$teacherId = (int)$_SESSION['teacher_id'];
$pdo = db();

// Get all students
$stmt = $pdo->query("SELECT id, student_no, full_name FROM student WHERE is_active = TRUE ORDER BY student_no");
$allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$messageType = '';

// Handle form submission for manual attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create_manual_session') {
        $classSubjectId = (int)($_POST['class_subject_id'] ?? 0);
        
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
            
            $_SESSION['current_session_id'] = (int)$pdo->lastInsertId();
            $message = '✅ Session created. Select students to mark attendance.';
            $messageType = 'success';
        }
    } elseif ($action === 'mark_attendance') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $studentIds = $_POST['student_ids'] ?? [];
        
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
                    // Already marked, skip
                }
            }
            $message = "✅ Marked attendance for $count student(s).";
            $messageType = 'success';
        }
    } elseif ($action === 'end_session') {
        unset($_SESSION['current_session_id']);
        $message = '✅ Session ended.';
        $messageType = 'info';
    }
}

// Get current session if exists
$currentSessionId = $_SESSION['current_session_id'] ?? null;
$currentSession = null;
$sessionAttendees = [];
$enrolledStudents = [];

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
        // Get already marked students
        $stmt = $pdo->prepare("
            SELECT student_id FROM attendance WHERE session_id = ?
        ");
        $stmt->execute([$currentSessionId]);
        $sessionAttendees = array_map(fn($row) => $row['student_id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        
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
    <title>Manual Attendance</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container { max-width: 900px; margin: 0 auto; }

        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 { font-size: 24px; color: #333; }
        .header a { color: #667eea; text-decoration: none; font-weight: 600; }

        .message {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .message.show { display: block; }
        .message-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .message-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .message-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 8px rgba(102, 126, 234, 0.2); }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
            font-size: 14px;
        }

        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }

        .btn-danger { background: #d32f2f; color: white; }
        .btn-danger:hover { background: #b71c1c; }

        .session-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: white;
        }

        .session-info h3 { margin-bottom: 10px; }
        .session-info p { margin: 5px 0; opacity: 0.9; }

        .session-status {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            margin-top: 8px;
        }

        .student-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 600px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .student-item {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            display: flex;
            align-items: center;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .student-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .student-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            cursor: pointer;
        }

        .student-info {
            flex: 1;
        }

        .student-info strong {
            display: block;
            color: #333;
            margin-bottom: 3px;
        }

        .student-info small {
            color: #999;
            display: block;
        }

        .student-item.marked {
            background: #d4edda;
            border-color: #28a745;
        }

        .student-item.marked input[type="checkbox"] {
            accent-color: #28a745;
        }

        .student-item.marked::after {
            content: '✓';
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
        }

        .select-all-box {
            background: #f8f9ff;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 2px solid #e0e0e0;
        }

        .select-all-box input { margin-right: 8px; }
        .select-all-box label { display: inline; margin: 0; }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-group .btn { flex: 1; }

        .summary {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-top: 15px;
            border: 2px solid #28a745;
        }

        .summary strong {
            color: #2e7d32;
            font-size: 18px;
            display: block;
        }

        .summary p { color: #388e3c; margin-top: 5px; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .student-count {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #1976d2;
            font-weight: 600;
            color: #1976d2;
        }

        @media (max-width: 600px) {
            .header { flex-direction: column; gap: 10px; }
            .student-item { padding: 10px; }
            .student-info small { font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Manual Attendance</h1>
            <a href="/teacher/dashboard.php">← Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="message show message-<?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$currentSession): ?>
            <!-- Create Session -->
            <div class="card">
                <h2 style="margin-bottom: 20px;">Start New Session</h2>
                <form method="post">
                    <input type="hidden" name="action" value="create_manual_session">
                    
                    <div class="form-group">
                        <label for="class_subject_id">Select Class/Subject</label>
                        <select name="class_subject_id" id="class_subject_id" required>
                            <option value="">-- Choose --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= (int)$s['id'] ?>">
                                    <?= htmlspecialchars($s['class_name'] . ' • ' . $s['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">✓ Start Session</button>
                </form>
            </div>

        <?php else: ?>
            <!-- Session Active -->
            <div class="session-info">
                <h3>✓ Active Session</h3>
                <p><strong>📚 Class:</strong> <?= htmlspecialchars($currentSession['class_name']) ?></p>
                <p><strong>📖 Subject:</strong> <?= htmlspecialchars($currentSession['subject_name']) ?></p>
                <p><strong>📊 Total Marked:</strong> <?= (int)$currentSession['total_marked'] ?></p>
                <div class="session-status">🟢 Session ID: <?= (int)$currentSession['id'] ?></div>
            </div>

            <!-- Mark Attendance -->
            <div class="card">
                <h2 style="margin-bottom: 20px;">Select Students (<?= count($enrolledStudents) ?> Enrolled)</h2>

                <div class="student-count">
                    📚 <?= count($enrolledStudents) ?> Students in this class
                </div>

                <div class="select-all-box">
                    <input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAll(this)">
                    <label for="select-all-checkbox"><strong>Select All / Deselect All</strong></label>
                </div>

                <form method="post" id="attendance-form">
                    <input type="hidden" name="action" value="mark_attendance">
                    <input type="hidden" name="session_id" value="<?= (int)$currentSessionId ?>">

                    <?php if (empty($enrolledStudents)): ?>
                        <div class="empty-state">
                            <p>No students enrolled in this class yet.</p>
                            <p><a href="/teacher/manage_students.php" style="color: #667eea; text-decoration: none;">Add students →</a></p>
                        </div>
                    <?php else: ?>
                        <div class="student-list">
                            <?php foreach ($enrolledStudents as $student): ?>
                                <?php $isMarked = in_array($student['id'], $sessionAttendees); ?>
                                <div class="student-item <?= $isMarked ? 'marked' : '' ?>">
                                    <input
                                        type="checkbox"
                                        name="student_ids[]"
                                        value="<?= (int)$student['id'] ?>"
                                        class="student-checkbox"
                                        <?= $isMarked ? 'checked disabled' : '' ?>>
                                    <div class="student-info">
                                        <strong><?= htmlspecialchars($student['student_no']) ?></strong>
                                        <small><?= htmlspecialchars($student['full_name']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">✓ Mark Selected</button>
                            <form method="post" style="flex: 1;">
                                <input type="hidden" name="action" value="end_session">
                                <button type="submit" class="btn btn-danger" style="width: 100%;">✕ End Session</button>
                            </form>
                        </div>

                        <div class="summary">
                            <strong id="marked-count">0</strong>
                            <p>out of <?= count($enrolledStudents) ?> students selected</p>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.student-checkbox:not(:disabled)');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateMarkedCount();
        }

        function updateMarkedCount() {
            const checked = document.querySelectorAll('.student-checkbox:checked').length;
            document.getElementById('marked-count').textContent = checked;
        }

        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', updateMarkedCount);
        });

        updateMarkedCount();
    </script>
</body>
</html>

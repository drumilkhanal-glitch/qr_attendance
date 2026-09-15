<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/template.php';

require_student();

$studentId = (int)$_SESSION['student_id'];
$pdo = db();

// Get student info
$stmt = $pdo->prepare("SELECT student_no, full_name FROM student WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get attendance records
$stmt = $pdo->prepare("
    SELECT
        a.marked_at,
        cs.class_name,
        cs.subject_name,
        s.starts_at
    FROM attendance a
    JOIN attendance_session s ON s.id = a.session_id
    JOIN class_subject cs ON cs.id = s.class_subject_id
    WHERE a.student_id = ?
    ORDER BY a.marked_at DESC
    LIMIT 50
");
$stmt->execute([$studentId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$totalAttendance = count($records);
$uniqueSubjects = count(array_unique(array_column($records, 'subject_name')));
$uniqueClasses = count(array_unique(array_column($records, 'class_name')));

$content = <<<HTML
<div class="page-title">Welcome, {$student['full_name']}! 👋</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="number">$totalAttendance</div>
        <div class="label">Total Sessions</div>
    </div>
    <div class="stat-card">
        <div class="number">$uniqueClasses</div>
        <div class="label">Classes</div>
    </div>
    <div class="stat-card">
        <div class="number">$uniqueSubjects</div>
        <div class="label">Subjects</div>
    </div>
</div>

<div class="card">
    <h2 style="display: flex; justify-content: space-between; align-items: center;">
        <span>Mark Attendance</span>
        <a href="/student/mark_attendance.php" class="btn btn-success">Scan QR or Enter Manually</a>
    </h2>
    <p style="color: var(--text-muted); margin-top: 10px;">Click the button to mark attendance using a QR code scan or manual entry.</p>
</div>

<div class="card">
    <h2>📊 Attendance History</h2>
HTML;

if (empty($records)) {
    $content .= '<p style="color: var(--text-muted); text-align: center; padding: 40px;">No attendance records yet. Start by marking your attendance.</p>';
} else {
    $content .= <<<'HTML'
    <table>
        <thead>
            <tr>
                <th>Class</th>
                <th>Subject</th>
                <th>Date</th>
                <th>Time Marked</th>
            </tr>
        </thead>
        <tbody>
HTML;
    
    foreach ($records as $r) {
        $marked_date = date('M d, Y', strtotime($r['marked_at']));
        $marked_time = date('h:i A', strtotime($r['marked_at']));
        $content .= <<<HTML
            <tr>
                <td><strong>{$r['class_name']}</strong></td>
                <td>{$r['subject_name']}</td>
                <td>$marked_date</td>
                <td>$marked_time</td>
            </tr>
HTML;
    }
    
    $content .= <<<'HTML'
        </tbody>
    </table>
HTML;
}

$content .= '</div>';

echo render_page('Student Dashboard', $content, 'student', true);

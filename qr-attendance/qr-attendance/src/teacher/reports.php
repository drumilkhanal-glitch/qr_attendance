<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_teacher();

$teacherId = (int)$_SESSION['teacher_id'];
$pdo = db();

// Check if coming from manual attendance marking
$markedCount = (int)($_GET['marked'] ?? 0);
$successMessage = '';
if ($markedCount > 0) {
    $successMessage = "✓ Successfully marked attendance for $markedCount student(s)! Scroll down to see the updated report.";
}

// Get all sessions with attendance counts
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.starts_at,
        s.expires_at,
        cs.class_name,
        cs.subject_name,
        (SELECT COUNT(*) FROM attendance a WHERE a.session_id = s.id) AS attendee_count,
        (SELECT COUNT(DISTINCT student_id) FROM student_enrollment se WHERE se.class_subject_id = cs.id) AS total_students
    FROM attendance_session s
    JOIN class_subject cs ON cs.id = s.class_subject_id
    WHERE s.teacher_id = ?
    ORDER BY s.starts_at DESC
");
$stmt->execute([$teacherId]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            color: #333;
        }

        .header a {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .header a:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            margin-top: 8px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            padding: 25px;
            overflow-x: auto;
        }

        .card h2 {
            margin-bottom: 20px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        table th {
            padding: 14px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }

        table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e0e0e0;
        }

        table tbody tr:hover {
            background: #f8f9ff;
        }

        table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .class-subject {
            font-weight: 600;
            color: #667eea;
        }

        .timestamp {
            font-size: 13px;
            color: #666;
        }

        .attendance-count {
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        .btn-view {
            background: #667eea;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view:hover {
            background: #5568d3;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .empty-state a {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: 0.3s;
        }

        .empty-state a:hover {
            background: #5568d3;
        }

        .badge-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            table {
                font-size: 13px;
            }

            table th, table td {
                padding: 10px;
            }

            .card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1> Attendance Reports</h1>
            <a href="/teacher/dashboard.php">← Back to Dashboard</a>
        </div>

        <?php if ($successMessage): ?>
            <div style="background: #d4edda; border: 2px solid #28a745; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= count($sessions) ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?= array_reduce($sessions, fn($carry, $s) => $carry + $s['attendee_count'], 0) ?>
                </div>
                <div class="stat-label">Total Attendees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?= count(array_filter($sessions, fn($s) => strtotime($s['expires_at']) > time())) ?>
                </div>
                <div class="stat-label">Active Sessions</div>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="card">
            <h2>All Attendance Sessions</h2>

            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>No sessions created yet</p>
                    <a href="/teacher/create_session.php">Create First Session</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Class & Subject</th>
                            <th>Started At</th>
                            <th>Expires At</th>
                            <th>Attendees</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): 
                            $isExpired = strtotime($session['expires_at']) < time();
                            $attendanceRate = $session['total_students'] > 0 
                                ? round(($session['attendee_count'] / $session['total_students']) * 100) 
                                : 0;
                        ?>
                            <tr>
                                <td>
                                    <span class="class-subject">
                                        <?= htmlspecialchars($session['class_name'] . ' • ' . $session['subject_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="timestamp">
                                        <?= date('M d, Y h:i A', strtotime($session['starts_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="timestamp">
                                        <?= date('M d, Y h:i A', strtotime($session['expires_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="attendance-count">
                                        <?= (int)$session['attendee_count'] ?>/<?= (int)$session['total_students'] ?> (<?= $attendanceRate ?>%)
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?= $isExpired ? 'status-expired' : 'status-active' ?>">
                                        <?= $isExpired ? '❌ Expired' : '🟢 Active' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/teacher/session.php?id=<?= (int)$session['id'] ?>" class="btn-view">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

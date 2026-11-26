<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_teacher();

$teacherId = (int)$_SESSION['teacher_id'];
$pdo = db();

// Get teacher info
$stmt = $pdo->prepare("SELECT id, username FROM teacher WHERE id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Get sessions managed by this teacher
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.token,
        s.session_name,
        s.starts_at,
        s.expires_at,
        s.status,
        cs.class_name,
        cs.subject_name,
        COUNT(a.id) as attendance_count,
        COUNT(DISTINCT a.student_id) as unique_students
    FROM attendance_session s
    JOIN class_subject cs ON cs.id = s.class_subject_id
    LEFT JOIN attendance a ON a.session_id = s.id
    WHERE s.teacher_id = ?
    GROUP BY s.id
    ORDER BY s.starts_at DESC
    LIMIT 20
");
$stmt->execute([$teacherId]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$totalSessions = count($sessions);
$totalAttendanceMarked = array_sum(array_column($sessions, 'attendance_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --light: #f8f9ff;
            --gray-500: #6b7280;
            --gray-900: #111827;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--gray-900);
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .navbar-user {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .navbar-user a {
            color: white;
            background: #d32f2f;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
        }

        .navbar-user a:hover {
            background: #b71c1c;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-title {
            font-size: 2rem;
            color: white;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary);
        }

        .stat-card .label {
            color: var(--gray-500);
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            background: #4caf50;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
        }

        .btn:hover {
            background: #45a049;
        }

        .btn-block {
            width: 100%;
            display: block;
            padding: 1rem;
        }

        .btn-secondary {
            background: #2196F3;
        }

        .btn-secondary:hover {
            background: #0b7dda;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        table thead {
            background: #f5f5f5;
        }

        table th, table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table tr:hover {
            background: #f9f9f9;
        }

        table th {
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray-500);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-buttons a {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            margin: 0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .card h2 {
                flex-direction: column;
                align-items: flex-start;
            }

            table {
                font-size: 0.9rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons a {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">QR Attendance</div>
        <div class="navbar-user">
            <span><?= htmlspecialchars($teacher['username']) ?></span>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title">Welcome, <?= htmlspecialchars($teacher['username']) ?>!</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= $totalSessions ?></div>
                <div class="label">Sessions Created</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $totalAttendanceMarked ?></div>
                <div class="label">Total Attendance Marked</div>
            </div>
        </div>

        <div class="card">
            <h2>
                <span>Start New Session</span>
                <a href="/teacher/create_qr.php" class="btn btn-secondary">Create QR Session</a>
            </h2>
            <p>Create a new QR code session for students to scan and mark their attendance.</p>
        </div>

        <div class="card">
            <h2>Quick Actions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="/teacher/session.php" class="btn btn-secondary" style="text-align: center;">View Sessions</a>
                <a href="/teacher/manual_attendance.php" class="btn btn-secondary" style="text-align: center;">Mark Attendance</a>
                <a href="/teacher/manage_students.php" class="btn btn-secondary" style="text-align: center;">Manage Students</a>
                <a href="/teacher/reports.php" class="btn btn-secondary" style="text-align: center;">View Reports</a>
            </div>
        </div>

        <div class="card">
            <h2>Recent Sessions</h2>
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <p>No sessions created yet. Start by creating a new session.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Session Name</th>
                            <th>Date & Time</th>
                            <th>Expires At</th>
                            <th>Status</th>
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($session['class_name']) ?></strong></td>
                                <td><?= htmlspecialchars($session['subject_name']) ?></td>
                                <td><code style="background: #f5f5f5; padding: 0.25rem 0.5rem; border-radius: 0.25rem;"><?= htmlspecialchars($session['session_name'] ?? 'N/A') ?></code></td>
                                <td><?= date('M d, Y h:i A', strtotime($session['starts_at'])) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($session['expires_at'])) ?></td>
                                <td>
                                    <?php 
                                        $statusColors = ['active' => '#4caf50', 'expired' => '#ff9800', 'closed' => '#f44336'];
                                        $color = $statusColors[$session['status']] ?? '#999';
                                    ?>
                                    <span style="background: <?= $color ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.85rem; font-weight: 600;"><?= ucfirst($session['status']) ?></span>
                                </td>
                                <td><?= $session['attendance_count'] ?> / <?= $session['unique_students'] ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="/teacher/view_enrollments.php?session_id=<?= $session['id'] ?>" class="btn" style="background: #2196F3;">View</a>
                                    </div>
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

<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_teacher();
require_once __DIR__ . '/../lib/db.php';

$teacherId = (int)$_SESSION['teacher_id'];
$sessionId = (int)($_GET['id'] ?? 0);

if ($sessionId <= 0) {
    http_response_code(400);
    die("Invalid session ID");
}

$pdo = db();

// Get session details - scoped to teacher for security
$stmt = $pdo->prepare("
    SELECT s.*, cs.class_name, cs.subject_name
    FROM attendance_session s
    JOIN class_subject cs ON cs.id = s.class_subject_id
    WHERE s.id = ? AND s.teacher_id = ?
    LIMIT 1
");
$stmt->execute([$sessionId, $teacherId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    http_response_code(404);
    die("Session not found");
}

// Get attendance records
$stmt = $pdo->prepare("
    SELECT st.student_no, st.full_name, a.marked_at
    FROM attendance a
    JOIN student st ON st.id = a.student_id
    WHERE a.session_id = ?
    ORDER BY a.marked_at ASC
");
$stmt->execute([$sessionId]);
$attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate session status
$now = new DateTimeImmutable('now');
$expiresAt = new DateTimeImmutable($session['expires_at']);
$startsAt = new DateTimeImmutable($session['starts_at']);
$isActive = $now <= $expiresAt;
$attendanceRate = !empty($attendees) ? count($attendees) : 0;

// Get total enrolled students for this class
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT student_id) as total_enrolled
    FROM student_enrollment
    WHERE class_subject_id = ?
");
$stmt->execute([$session['class_subject_id']]);
$classStats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalEnrolled = $classStats['total_enrolled'] ?? 0;
$attendancePercentage = $totalEnrolled > 0 ? round(($attendanceRate / $totalEnrolled) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Details - Attendance Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #06b6d4;
            --light: #f8f9ff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
            color: var(--gray-900);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            box-shadow: var(--shadow-md);
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 100;
            margin-bottom: 2rem;
            border-radius: 0.75rem;
            margin: 0 0 2rem 0;
        }

        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .navbar-links {
            display: flex;
            gap: 1rem;
        }

        .navbar-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .navbar-links a:hover {
            color: var(--secondary);
        }

        .page-header {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .session-subtitle {
            color: var(--gray-500);
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            transition: all 0.2s;
            border-left: 4px solid var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-500);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .session-info {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .session-info h2 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            color: var(--gray-900);
        }

        .info-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-500);
        }

        .info-value {
            font-size: 1.1rem;
            color: var(--gray-900);
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.85rem;
            width: fit-content;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-expired {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .attendees-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .attendees-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .attendees-header h2 {
            font-size: 1.25rem;
            color: var(--gray-900);
        }

        .attendees-count {
            background: var(--light);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        table tbody tr {
            transition: background-color 0.2s;
        }

        table tbody tr:hover {
            background: var(--light);
        }

        table tbody tr:nth-child(even) {
            background: var(--gray-50);
        }

        .student-no {
            color: var(--primary);
            font-weight: 700;
        }

        .student-name {
            font-weight: 600;
            color: var(--gray-900);
        }

        .marked-time {
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-500);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                grid-template-columns: 1fr;
            }

            .attendees-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            table {
                font-size: 0.85rem;
            }

            table th,
            table td {
                padding: 0.75rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">Attendance Management</div>
            <div class="navbar-links">
                <a href="/teacher/reports.php">Back to Reports</a>
                <a href="/teacher/dashboard.php">Dashboard</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title"><?= htmlspecialchars($session['class_name'] . ' - ' . $session['subject_name']) ?></h1>
            <p class="session-subtitle">Session Details & Attendance Records</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"></div>
                <div class="stat-number"><?= $attendanceRate ?></div>
                <div class="stat-label">Students Marked</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"></div>
                <div class="stat-number"><?= $attendancePercentage ?>%</div>
                <div class="stat-label">Attendance Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏱</div>
                <div class="stat-number"><?= $totalEnrolled ?></div>
                <div class="stat-label">Total Enrolled</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <?= $isActive ? '🟢' : '🔴' ?>
                </div>
                <div class="stat-number"><?= $isActive ? 'Active' : 'Closed' ?></div>
                <div class="stat-label">Session Status</div>
            </div>
        </div>

        <!-- Session Information -->
        <div class="session-info">
            <h2>Session Information</h2>

            <div class="info-row">
                <div class="info-item">
                    <span class="info-label">Session Status</span>
                    <div>
                        <span class="status-badge <?= $isActive ? 'status-active' : 'status-expired' ?>">
                            <?= $isActive ? '✓ Active' : '✕ Expired' ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <span class="info-label">Started At</span>
                    <span class="info-value"><?= date('M d, Y • h:i A', strtotime($session['starts_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Expires At</span>
                    <span class="info-value"><?= date('M d, Y • h:i A', strtotime($session['expires_at'])) ?></span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-item">
                    <span class="info-label">Attendance Rate</span>
                    <div>
                        <span class="info-value"><?= $attendanceRate ?> of <?= $totalEnrolled ?> students (<?= $attendancePercentage ?>%)</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $attendancePercentage ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="info-item">
                    <span class="info-label">Session ID</span>
                    <span class="info-value"><?= (int)$session['id'] ?></span>
                </div>
            </div>
        </div>

        <!-- Attendees Section -->
        <div class="attendees-section">
            <div class="attendees-header">
                <h2>Attendance Records</h2>
                <span class="attendees-count"><?= count($attendees) ?> Marked</span>
            </div>

            <?php if (empty($attendees)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <p>No students have marked attendance yet</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 20%;">Student Number</th>
                                <th style="width: 40%;">Student Name</th>
                                <th style="width: 40%;">Marked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $idx => $attendee): ?>
                                <tr>
                                    <td>
                                        <span class="student-no"><?= htmlspecialchars($attendee['student_no']) ?></span>
                                    </td>
                                    <td>
                                        <span class="student-name"><?= htmlspecialchars($attendee['full_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="marked-time"><?= date('M d, Y • h:i A', strtotime($attendee['marked_at'])) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="/teacher/reports.php" class="btn btn-primary">Back to Reports</a>
                <a href="/teacher/dashboard.php" class="btn btn-secondary">Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

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
        a.marked_by,
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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

        .card p {
            color: var(--gray-500);
            margin-bottom: 1rem;
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
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">QR Attendance</div>
        <div class="navbar-user">
            <span><?= htmlspecialchars($student['full_name']) ?></span>
            <a href="/logout.php">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title">Welcome, <?= htmlspecialchars($student['full_name']) ?>!</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= $totalAttendance ?></div>
                <div class="label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $uniqueClasses ?></div>
                <div class="label">Classes</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $uniqueSubjects ?></div>
                <div class="label">Subjects</div>
            </div>
        </div>

        <div class="card">
            <h2>
                <span>Mark Attendance</span>
                <a href="/student/mark_attendance.php" class="btn">Scan QR</a>
            </h2>
            <p>Click the button to mark your attendance using a QR code scan.</p>
        </div>

        <div class="card">
            <h2>Attendance History</h2>
            <?php if (empty($records)): ?>
                <div class="empty-state">
                    <p>No attendance records yet. Start by scanning a QR code.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['class_name']) ?></strong></td>
                                <td><?= htmlspecialchars($r['subject_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($r['marked_at'])) ?></td>
                                <td><?= date('h:i A', strtotime($r['marked_at'])) ?></td>
                                <td>
                                    <?php if ($r['marked_by'] === 'qr_scan'): ?>
                                        <span style="color: #667eea; font-weight: 600;">QR Scan</span>
                                    <?php else: ?>
                                        <span style="color: #666; font-weight: 600;">Manual</span>
                                    <?php endif; ?>
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

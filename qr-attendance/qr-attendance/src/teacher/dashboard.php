<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_teacher();
require_once __DIR__ . '/../lib/db.php';

$teacherId = (int)$_SESSION['teacher_id'];
$pdo = db();

// Get teacher info
$stmt = $pdo->prepare("SELECT username FROM teacher WHERE id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all classes
$stmt = $pdo->query("SELECT id, class_name, subject_name FROM class_subject ORDER BY class_name, subject_name");
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats - Fixed: Use table-specific column names
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT s.id) as total_sessions,
        COUNT(DISTINCT a.student_id) as total_students_marked
    FROM attendance_session s
    LEFT JOIN attendance a ON a.session_id = s.id
    WHERE s.teacher_id = ?
");
$stmt->execute([$teacherId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
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
            --accent: #f093fb;
            --success: #00d084;
            --danger: #ff3c87;
            --warning: #ffa502;
            --info: #00d4ff;
            --light: #f8f9ff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--gray-900);
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
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: var(--gray-900);
            display: block;
        }

        .user-role {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .logout-btn {
            background: linear-gradient(135deg, var(--danger), #ff5c9f);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 60, 135, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .page-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            transition: all 0.2s ease;
            border-left: 4px solid var(--primary);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            opacity: 0.1;
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2xl);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray-500);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: -1;
        }

        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
            border-color: var(--primary);
        }

        .action-card:hover::before {
            opacity: 0.05;
        }

        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .action-card h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .action-card p {
            color: var(--gray-500);
            font-size: 0.95rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .action-btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateX(4px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .qr-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-top: 2rem;
        }

        .qr-section h2 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .qr-section > p {
            color: var(--gray-500);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
            font-size: 0.95rem;
        }

        .form-group select,
        .form-group input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">Attendance Management System</div>
            <div class="navbar-user">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($teacher['username'] ?? 'Teacher') ?></span>
                    <span class="user-role">Teacher</span>
                </div>
                <a href="/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back! Manage your classes and attendance</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">[Sessions]</div>
                <div class="stat-number"><?= (int)($stats['total_sessions'] ?? 0) ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">[Classes]</div>
                <div class="stat-number"><?= count($subjects) ?></div>
                <div class="stat-label">Classes Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">[Marked]</div>
                <div class="stat-number"><?= (int)($stats['total_students_marked'] ?? 0) ?></div>
                <div class="stat-label">Students Marked</div>
            </div>
        </div>

        <!-- Action Cards -->
        <div class="action-buttons">
            <a href="/teacher/manual_attendance.php" class="action-card">
                <span class="action-icon">[Attendance]</span>
                <h2>Manual Attendance</h2>
                <p>Mark attendance by selecting students manually from your classes</p>
                <button class="action-btn">Start</button>
            </a>

            <a href="/teacher/manage_students.php" class="action-card">
                <span class="action-icon">[Students]</span>
                <h2>Manage Students</h2>
                <p>Add or remove students from your classes and manage enrollments</p>
                <button class="action-btn">Manage</button>
            </a>

            <a href="/teacher/reports.php" class="action-card">
                <span class="action-icon">[Reports]</span>
                <h2>Attendance Reports</h2>
                <p>View detailed attendance records and statistics for all sessions</p>
                <button class="action-btn">View</button>
            </a>

            <a href="/teacher/view_enrollments.php" class="action-card">
                <span class="action-icon">[Enrollments]</span>
                <h2>View Enrollments</h2>
                <p>See all student enrollments in a comprehensive table view</p>
                <button class="action-btn">Browse</button>
            </a>
        </div>

        <!-- QR Generation Section -->
        <div class="qr-section">
            <h2>Generate QR Session</h2>
            <p>Create a QR code for students to scan and mark attendance with photo verification</p>

            <form method="get" action="/teacher/create_session.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="class_subject_id">Select Class/Subject</label>
                        <select name="class_subject_id" id="class_subject_id" required>
                            <option value="">-- Choose Class --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= (int)$s['id'] ?>">
                                    <?= htmlspecialchars($s['class_name'] . ' - ' . $s['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="expire_minutes">Session Duration</label>
                        <select name="expire_minutes" id="expire_minutes">
                            <option value="5">5 minutes</option>
                            <option value="10">10 minutes</option>
                            <option value="15">15 minutes</option>
                            <option value="30" selected>30 minutes</option>
                            <option value="60">1 hour</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-submit">GENERATE QR CODE</button>
            </form>
        </div>
    </div>
</body>
</html>

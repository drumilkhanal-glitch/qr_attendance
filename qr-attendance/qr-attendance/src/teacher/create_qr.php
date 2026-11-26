<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_teacher();
require_once __DIR__ . '/../lib/db.php';

$teacherId = (int)$_SESSION['teacher_id'];
$pdo = db();
$error = '';
$success = '';

// Get teacher's classes
$stmt = $pdo->prepare("
    SELECT DISTINCT cs.id, cs.class_name, cs.subject_name 
    FROM class_subject cs
    WHERE cs.id IN (
        SELECT DISTINCT class_subject_id FROM attendance_session WHERE teacher_id = ?
    )
    ORDER BY cs.class_name, cs.subject_name
");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classSubjectId = (int)($_POST['class_subject_id'] ?? 0);
    $sessionName = trim($_POST['session_name'] ?? '');
    $durationMinutes = (int)($_POST['duration_minutes'] ?? 10);

    if ($classSubjectId <= 0) {
        $error = 'Please select a class.';
    } elseif (empty($sessionName)) {
        $error = 'Please enter a session name.';
    } elseif ($durationMinutes < 1 || $durationMinutes > 480) {
        $error = 'Duration must be between 1 and 480 minutes.';
    } else {
        try {
            // Generate unique token and QR payload
            $token = bin2hex(random_bytes(32));
            $qrPayload = $token;

            $startsAt = new DateTime('now', new DateTimeZone('UTC'));
            $expiresAt = (clone $startsAt)->add(new DateInterval("PT{$durationMinutes}M"));

            $stmt = $pdo->prepare("
                INSERT INTO attendance_session (teacher_id, class_subject_id, token, qr_payload, session_name, starts_at, expires_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $teacherId,
                $classSubjectId,
                $token,
                $qrPayload,
                $sessionName,
                $startsAt->format('Y-m-d H:i:s'),
                $expiresAt->format('Y-m-d H:i:s')
            ]);

            $sessionId = $pdo->lastInsertId();
            $success = "QR Session created! Redirecting to display...";
            
            // Redirect to QR display
            header("Location: /teacher/qr_display.php?session_id=$sessionId");
            exit;
        } catch (Exception $e) {
            $error = 'Failed to create session: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create QR Session - Attendance Management</title>
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
            --success: #10b981;
            --danger: #dc2626;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            color: var(--gray-900);
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
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
            color: var(--primary);
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
        }

        .navbar-links a:hover {
            color: var(--secondary);
        }

        .container {
            width: 100%;
            max-width: 500px;
            margin-top: 80px;
        }

        .card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
        }

        .card-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .card-title {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .card-subtitle {
            color: var(--gray-500);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-900);
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-error {
            background: #fee2e2;
            color: #7f1d1d;
            border-left: 4px solid var(--danger);
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .btn {
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
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
            background: #e5e7eb;
            color: var(--gray-900);
            margin-top: 1rem;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .helper-text {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .container {
                margin-top: 100px;
            }

            .card {
                padding: 1.5rem;
            }

            .card-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">QR Attendance</div>
            <div class="navbar-links">
                <a href="/teacher/dashboard.php">Dashboard</a>
                <a href="/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Create QR Session</h1>
                <p class="card-subtitle">Generate a new QR code for students to scan</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="class_subject_id" class="form-label">Select Class</label>
                    <select name="class_subject_id" id="class_subject_id" class="form-control" required>
                        <option value="">-- Choose a class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= (int)$class['id'] ?>">
                                <?= htmlspecialchars($class['class_name'] . ' - ' . $class['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="helper-text">Select the class for this attendance session</div>
                </div>

                <div class="form-group">
                    <label for="session_name" class="form-label">Session Name</label>
                    <input 
                        type="text" 
                        name="session_name" 
                        id="session_name" 
                        class="form-control" 
                        placeholder="e.g., Lecture - Topic 1"
                        maxlength="255"
                        required>
                    <div class="helper-text">Give this session a descriptive name</div>
                </div>

                <div class="form-group">
                    <label for="duration_minutes" class="form-label">Duration (Minutes)</label>
                    <input 
                        type="number" 
                        name="duration_minutes" 
                        id="duration_minutes" 
                        class="form-control" 
                        value="10"
                        min="1"
                        max="480"
                        required>
                    <div class="helper-text">How long should this QR code remain valid? (1-480 minutes)</div>
                </div>

                <button type="submit" class="btn btn-primary">Generate & Display QR Code</button>
                <a href="/teacher/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </form>
        </div>
    </div>
</body>
</html>

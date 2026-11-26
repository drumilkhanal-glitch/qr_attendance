<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_student();
require_once __DIR__ . '/../lib/db.php';

$studentId = (int)$_SESSION['student_id'];
$pdo = db();
$error = '';
$success = '';
$scannedData = '';

// Get student info
$stmt = $pdo->prepare("SELECT student_no, full_name FROM student WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');

    if (empty($token)) {
        $error = 'Please enter or scan a QR code token.';
    } else {
        try {
            // Find the session by token
            $stmt = $pdo->prepare("
                SELECT s.id, s.teacher_id, s.starts_at, s.expires_at, s.status, cs.class_name, cs.subject_name
                FROM attendance_session s
                JOIN class_subject cs ON cs.id = s.class_subject_id
                WHERE s.token = ? AND s.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                $error = 'Invalid or expired QR code. Please try again.';
            } else {
                // Check if already marked
                $stmt = $pdo->prepare("
                    SELECT id FROM attendance 
                    WHERE session_id = ? AND student_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$session['id'], $studentId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $error = 'You have already marked attendance for this session.';
                } else {
                    // Record attendance
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (session_id, student_id, marked_by)
                        VALUES (?, ?, 'qr_scan')
                    ");
                    $stmt->execute([$session['id'], $studentId]);

                    $success = "Attendance marked successfully for {$session['class_name']} - {$session['subject_name']}!";
                    $scannedData = $token;
                }
            }
        } catch (Exception $e) {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Code - Attendance</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.js" crossorigin="anonymous"></script>
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
            max-width: 600px;
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

        .student-info {
            background: var(--light);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }

        .student-info p {
            margin: 0.25rem 0;
            color: var(--gray-900);
            font-weight: 500;
        }

        .student-label {
            color: var(--gray-500);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .tab-btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: var(--gray-500);
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .camera-container {
            background: #000;
            border-radius: 0.75rem;
            overflow: hidden;
            margin-bottom: 1.5rem;
            aspect-ratio: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
        }

        #video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            -webkit-transform: scaleX(-1);
            transform: scaleX(-1);
        }

        #canvas {
            display: none;
        }

        .camera-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
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
            font-family: monospace;
            transition: border-color 0.2s;
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
            margin-bottom: 0.5rem;
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
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #991b1b;
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

            .tabs {
                gap: 0;
            }

            .tab-btn {
                padding: 0.5rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">QR Attendance</div>
            <div class="navbar-links">
                <a href="/student/dashboard.php">Dashboard</a>
                <a href="/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Mark Attendance</h1>
                <p class="card-subtitle">Scan QR code or enter token manually</p>
            </div>

            <div class="student-info">
                <p><span class="student-label">Student ID:</span> <?= htmlspecialchars($student['student_no']) ?></p>
                <p><span class="student-label">Name:</span> <?= htmlspecialchars($student['full_name']) ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="tabs">
                <button type="button" class="tab-btn active" onclick="switchTab('camera')">Camera</button>
                <button type="button" class="tab-btn" onclick="switchTab('manual')">Manual Entry</button>
            </div>

            <!-- Camera Tab -->
            <div id="camera" class="tab-content active">
                <div class="camera-info">
                    📷 Point your camera at the QR code. It will scan automatically.
                </div>
                <div class="camera-container" id="camera-status">
                    <video id="video" autoplay playsinline muted style="display: none;"></video>
                    <canvas id="canvas"></canvas>
                    <div style="color: white; text-align: center;">
                        <p style="font-size: 2rem; margin-bottom: 1rem;">🎥</p>
                        <p>Click "Start Camera" to begin scanning</p>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" id="start-btn" onclick="startCamera()">Start Camera</button>
                <button type="button" class="btn btn-danger" id="stop-btn" onclick="stopCamera()" style="display: none;">Stop Camera</button>
            </div>

            <!-- Manual Entry Tab -->
            <div id="manual" class="tab-content">
                <form method="POST">
                    <div class="form-group">
                        <label for="token" class="form-label">QR Token</label>
                        <input 
                            type="text" 
                            name="token" 
                            id="token" 
                            class="form-control" 
                            placeholder="Paste QR token here"
                            maxlength="64">
                        <div class="helper-text">Enter the QR code token manually</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Mark Attendance</button>
                </form>
            </div>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                <a href="/student/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        let stream = null;

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');

            if (tabName === 'camera') {
                startCamera();
            } else {
                stopCamera();
            }
        }

        function startCamera() {
            if (stream) return;

            const video = document.getElementById('video');
            const startBtn = document.getElementById('start-btn');
            const stopBtn = document.getElementById('stop-btn');
            const statusDiv = document.getElementById('camera-status');

            navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            }).then(s => {
                stream = s;
                video.srcObject = stream;
                video.style.display = 'block';
                statusDiv.querySelectorAll('div').forEach(d => d.style.display = 'none');
                startBtn.style.display = 'none';
                stopBtn.style.display = 'block';
                video.onloadedmetadata = () => {
                    scanQRCode();
                };
            }).catch(err => {
                alert('Camera access denied: ' + err.message + '\n\nPlease allow camera access in browser settings.');
                console.error('Camera error:', err);
            });
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            const video = document.getElementById('video');
            const startBtn = document.getElementById('start-btn');
            const stopBtn = document.getElementById('stop-btn');
            const statusDiv = document.getElementById('camera-status');
            
            video.style.display = 'none';
            statusDiv.querySelectorAll('div').forEach(d => d.style.display = '');
            startBtn.style.display = 'block';
            stopBtn.style.display = 'none';
        }

        function scanQRCode() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const ctx = canvas.getContext('2d');
            let scanning = true;
            let lastScan = 0;

            const scan = () => {
                if (!stream || !scanning) return;

                // Skip if video isn't ready or dimensions are 0
                if (video.readyState !== video.HAVE_ENOUGH_DATA || video.videoWidth === 0 || video.videoHeight === 0) {
                    requestAnimationFrame(scan);
                    return;
                }

                // Throttle scans to every 100ms for performance
                const now = Date.now();
                if (now - lastScan < 100) {
                    requestAnimationFrame(scan);
                    return;
                }
                lastScan = now;

                try {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    
                    // Draw video frame
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    // Get image data only if canvas has valid dimensions
                    if (canvas.width > 0 && canvas.height > 0) {
                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const code = jsQR(imageData.data, canvas.width, canvas.height);

                        if (code && code.data) {
                            scanning = false;
                            stopCamera();
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `<input type="hidden" name="token" value="${code.data}">`;
                            document.body.appendChild(form);
                            form.submit();
                            return;
                        }
                    }
                } catch (e) {
                    console.error('QR scan error:', e);
                }
                
                requestAnimationFrame(scan);
            };

            scan();
        }
    </script>
</body>
</html>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_teacher();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/qr.php';

$teacherId = (int)$_SESSION['teacher_id'];

$classSubjectId = (int)($_GET['class_subject_id'] ?? 0);
$expire = (int)($_GET['expire_minutes'] ?? 10); // Default 10 minutes

if ($classSubjectId <= 0) die("Invalid class_subject_id");

$token = bin2hex(random_bytes(32));
$qrPayload = "SESSION_TOKEN:" . $token;

$pdo = db();

// Use UTC for consistency
$now = new DateTime('now', new DateTimeZone('UTC'));
$expiresAt = clone $now;
$expiresAt->modify("+{$expire} minutes");

// Insert session using UTC timestamps
$stmt = $pdo->prepare("
  INSERT INTO attendance_session (teacher_id, class_subject_id, token, qr_payload, starts_at, expires_at)
  VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([
  $teacherId,
  $classSubjectId,
  $token,
  $qrPayload,
  $now->format('Y-m-d H:i:s'),
  $expiresAt->format('Y-m-d H:i:s')
]);

$sessionId = (int)$pdo->lastInsertId();

// Get class/subject info and enrolled students count
$stmt = $pdo->prepare("
  SELECT cs.class_name, cs.subject_name, COUNT(se.id) as enrolled_count
  FROM class_subject cs
  LEFT JOIN student_enrollment se ON se.class_subject_id = cs.id
  WHERE cs.id = ?
  GROUP BY cs.id
");
$stmt->execute([$classSubjectId]);
$classInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// Generate QR image
$qrPngBase64 = generate_qr_png_base64($qrPayload);

// Get remaining seconds from database (most accurate)
$stmt = $pdo->prepare("
  SELECT UNIX_TIMESTAMP(expires_at) - UNIX_TIMESTAMP(NOW()) as remaining_seconds
  FROM attendance_session WHERE id = ?
");
$stmt->execute([$sessionId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$remainingSeconds = max(0, (int)$result['remaining_seconds']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QR Session Generated</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .container {
      background: white;
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      max-width: 500px;
      width: 100%;
    }

    h2 {
      color: #333;
      margin-bottom: 20px;
      text-align: center;
    }

    .info {
      background: #e3f2fd;
      padding: 15px;
      border-left: 4px solid #1976d2;
      margin-bottom: 20px;
      border-radius: 4px;
    }

    .info p {
      margin: 10px 0;
      color: #555;
    }

    .info strong { color: #1976d2; }
    
    .qr-display {
      text-align: center;
      padding: 20px;
      background: #f5f5f5;
      border-radius: 8px;
      margin: 20px 0;
    }

    .qr-display img {
      max-width: 100%;
      width: 300px;
      height: 300px;
      border: 2px solid #ddd;
      border-radius: 8px;
    }

    .qr-label {
      font-size: 12px;
      color: #999;
      margin-top: 10px;
    }

    .timer {
      text-align: center;
      padding: 15px;
      background: #fff3cd;
      border-radius: 8px;
      margin: 20px 0;
    }

    .timer strong {
      font-size: 18px;
      color: #856404;
      display: block;
    }

    .timer p {
      color: #856404;
      font-size: 14px;
      margin-top: 5px;
    }

    .nav {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }

    .btn {
      flex: 1;
      padding: 12px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: bold;
      text-decoration: none;
      text-align: center;
      transition: 0.3s;
    }

    .btn-primary {
      background: #1976d2;
      color: white;
    }

    .btn-primary:hover {
      background: #1565c0;
    }

    .btn-secondary {
      background: #757575;
      color: white;
    }

    .btn-secondary:hover {
      background: #616161;
    }

    .status {
      background: #d4edda;
      color: #155724;
      padding: 12px;
      border-radius: 6px;
      text-align: center;
      margin-bottom: 15px;
    }

    .students-info {
      background: #e8f5e9;
      padding: 12px;
      border-radius: 6px;
      text-align: center;
      margin-bottom: 15px;
    }

    .students-info strong {
      color: #2e7d32;
      font-size: 16px;
    }

    @media (max-width: 480px) {
      .container {
        padding: 25px;
      }

      .nav {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <h2>✓ QR Session Created</h2>

  <div class="status">
    🟢 Session Active - Ready for Scanning
  </div>

  <div class="info">
    <p><strong>Session ID:</strong> <?= $sessionId ?></p>
    <p><strong>Class:</strong> <?= htmlspecialchars($classInfo['class_name'] ?? 'N/A') ?></p>
    <p><strong>Subject:</strong> <?= htmlspecialchars($classInfo['subject_name'] ?? 'N/A') ?></p>
    <p><strong>Duration:</strong> <?= $expire ?> minutes</p>
  </div>

  <div class="students-info">
    <strong><?= (int)$classInfo['enrolled_count'] ?> Students Enrolled</strong>
    <p>in this class</p>
  </div>

  <div class="qr-display">
    <img src="data:image/png;base64,<?= $qrPngBase64 ?>" alt="QR Code">
    <div class="qr-label">Students scan this QR code on their phones</div>
  </div>

  <div class="timer">
    <p>Expires in:</p>
    <strong id="countdown"><?= $remainingSeconds ?>s</strong>
  </div>

  <div class="nav">
    <a href="/teacher/dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    <a href="/teacher/reports.php" class="btn btn-secondary">View Reports</a>
  </div>
</div>

<script>
function updateCountdown() {
  let remainingSeconds = <?= $remainingSeconds ?>;
  
  const updateDisplay = () => {
    if (remainingSeconds <= 0) {
      document.getElementById('countdown').textContent = 'Expired';
      document.getElementById('countdown').parentElement.parentElement.style.background = '#f8d7da';
    } else {
      const minutes = Math.floor(remainingSeconds / 60);
      const seconds = remainingSeconds % 60;
      const timeStr = minutes > 0 
        ? `${minutes}m ${seconds < 10 ? '0' : ''}${seconds}s` 
        : `${seconds}s`;
      document.getElementById('countdown').textContent = timeStr;
      remainingSeconds--;
      setTimeout(updateDisplay, 1000);
    }
  };
  
  updateDisplay();
}

updateCountdown();
</script>
</body>
</html>

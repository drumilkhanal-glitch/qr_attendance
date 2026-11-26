<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_teacher();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/qr.php';

$teacherId = (int)$_SESSION['teacher_id'];
$sessionId = (int)($_GET['session_id'] ?? 0);

if ($sessionId <= 0) {
    die("Invalid session ID");
}

$pdo = db();

// Verify teacher owns this session
$stmt = $pdo->prepare("
    SELECT s.*, cs.class_name, cs.subject_name
    FROM attendance_session s
    JOIN class_subject cs ON cs.id = s.class_subject_id
    WHERE s.id = ? AND s.teacher_id = ?
");
$stmt->execute([$sessionId, $teacherId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die("Session not found or access denied");
}

// Get current attendees
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM attendance WHERE session_id = ?
");
$stmt->execute([$sessionId]);
$attendeeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$qrPngBase64 = generate_qr_png_base64($session['qr_payload']);

// Calculate remaining time in seconds using MySQL
$stmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(expires_at) - UNIX_TIMESTAMP(NOW()) as remaining_seconds FROM attendance_session WHERE id = ?");
$stmt->execute([$sessionId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$remainingSeconds = max(0, (int)$result['remaining_seconds']);
$isActive = $remainingSeconds > 0;

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR Display - <?= htmlspecialchars($session['class_name']) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 
      font-family: Arial, sans-serif; 
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
      border-radius: 12px; 
      box-shadow: 0 10px 50px rgba(0,0,0,0.3);
      max-width: 600px;
      width: 100%;
      text-align: center;
    }
    
    h1 { color: #333; margin-bottom: 10px; }
    .class-info { color: #666; margin-bottom: 30px; font-size: 16px; }
    
    .qr-display { 
      background: white; 
      padding: 30px; 
      border-radius: 10px; 
      margin: 30px 0;
    }
    .qr-display img { 
      max-width: 100%; 
      width: 300px;
      height: 300px;
      border: 3px solid #333;
      border-radius: 10px;
    }
    
    .status { 
      padding: 20px; 
      border-radius: 8px; 
      margin: 20px 0; 
      font-weight: bold;
    }
    .status.active { background: #d4edda; color: #155724; }
    .status.expired { background: #f8d7da; color: #721c24; }
    
    .countdown { 
      font-size: 28px; 
      color: #d32f2f; 
      margin: 20px 0;
      font-weight: bold;
    }
    
    .attendee-count {
      padding: 15px;
      background: #e3f2fd;
      border-radius: 8px;
      margin: 20px 0;
    }
    .attendee-count .number { 
      font-size: 32px; 
      color: #1976d2; 
      font-weight: bold;
    }
    
    .controls { 
      display: flex; 
      gap: 10px; 
      margin-top: 30px;
      flex-wrap: wrap;
    }
    .btn { 
      flex: 1; 
      min-width: 150px;
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
    .btn-primary { background: #0066cc; color: white; }
    .btn-primary:hover { background: #0052a3; }
    .btn-secondary { background: #666; color: white; }
    .btn-secondary:hover { background: #555; }
    
    .fullscreen-btn { 
      position: fixed; 
      top: 20px; 
      right: 20px; 
      z-index: 100;
    }
  </style>
</head>
<body>

<button class="btn btn-primary fullscreen-btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>

<div class="container" id="qr-container">
  <h1>Scan QR Code</h1>
  <div class="class-info">
    <p><strong><?= htmlspecialchars($session['class_name']) ?></strong></p>
    <p><?= htmlspecialchars($session['subject_name']) ?></p>
  </div>

  <div class="qr-display">
    <img src="data:image/png;base64,<?= $qrPngBase64 ?>" alt="QR Code">
  </div>

  <div class="status <?= $isActive ? 'active' : 'expired' ?>">
    <?= $isActive ? '🟢 Session Active' : '🔴 Session Expired' ?>
  </div>

  <?php if ($isActive): ?>
    <div class="countdown">
      Expires in: <span id="countdown"><?= $remainingSeconds ?>s</span>
    </div>
  <?php endif; ?>

  <div class="attendee-count">
    <p>Students Scanned:</p>
    <div class="number" id="attendee-count"><?= (int)$attendeeCount ?></div>
  </div>

  <div class="controls">
    <a href="/teacher/manual_attendance.php" class="btn btn-secondary">Back to Manual</a>
    <a href="/teacher/reports.php" class="btn btn-secondary">View Reports</a>
  </div>
</div>

<script>
function toggleFullscreen() {
  const elem = document.getElementById('qr-container');
  if (!document.fullscreenElement) {
    elem.requestFullscreen().catch(err => alert('Could not go fullscreen: ' + err.message));
  } else {
    document.exitFullscreen();
  }
}

function updateCountdown() {
  let remainingSeconds = <?= $remainingSeconds ?>;
  
  const interval = setInterval(() => {
    if (remainingSeconds <= 0) {
      document.getElementById('countdown').textContent = 'Expired';
      clearInterval(interval);
    } else {
      const minutes = Math.floor(remainingSeconds / 60);
      const seconds = remainingSeconds % 60;
      document.getElementById('countdown').textContent = minutes + 'm ' + seconds + 's';
      remainingSeconds--;
    }
  }, 1000);
}

function updateAttendeeCount() {
  fetch('/api/attendance_count.php?session_id=<?= (int)$sessionId ?>')
    .then(r => r.json())
    .then(data => {
      document.getElementById('attendee-count').textContent = data.count;
      setTimeout(updateAttendeeCount, 2000);
    })
    .catch(err => console.error('Error updating count:', err));
}

<?php if ($isActive): ?>
updateCountdown();
updateAttendeeCount();
<?php endif; ?>
</script>
</body>
</html>

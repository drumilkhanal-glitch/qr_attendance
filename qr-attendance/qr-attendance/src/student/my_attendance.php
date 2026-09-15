<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_student();
require_once __DIR__ . '/../lib/db.php';

$studentId = (int)$_SESSION['student_id'];

$pdo = db();
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
");
$stmt->execute([$studentId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>My Attendance</title></head>
<body>
<h2>My Attendance History</h2>

<p><a href="/student/mark_attendance.php">Mark New Attendance</a></p>

<?php if (empty($records)): ?>
  <p>No attendance records yet.</p>
<?php else: ?>
<table border="1" cellpadding="6" cellspacing="0">
  <thead>
    <tr>
      <th>Class</th>
      <th>Subject</th>
      <th>Session Date</th>
      <th>Marked At</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($records as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['class_name']) ?></td>
      <td><?= htmlspecialchars($r['subject_name']) ?></td>
      <td><?= htmlspecialchars($r['starts_at']) ?></td>
      <td><?= htmlspecialchars($r['marked_at']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p>Total sessions attended: <?= count($records) ?></p>
<?php endif; ?>

</body>
</html>
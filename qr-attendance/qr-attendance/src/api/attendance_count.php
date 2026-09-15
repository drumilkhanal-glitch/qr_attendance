<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/db.php';

$sessionId = (int)($_GET['session_id'] ?? 0);

if ($sessionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid session ID']);
    exit;
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM attendance WHERE session_id = ?
");
$stmt->execute([$sessionId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['count' => (int)$result['count']]);

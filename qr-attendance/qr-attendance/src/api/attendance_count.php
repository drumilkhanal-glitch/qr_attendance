<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');

try {
    require_teacher();
    
    $sessionId = (int)($_GET['session_id'] ?? 0);
    
    if ($sessionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'count' => 0, 'message' => 'Invalid session ID']);
        exit;
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => (int)$result['count'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'count' => 0, 'message' => $e->getMessage()]);
}

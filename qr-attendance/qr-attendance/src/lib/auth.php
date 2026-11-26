<?php
declare(strict_types=1);

function start_session(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    // Apply session configuration before starting
    $config = require __DIR__ . '/../config.php';
    $sessionConfig = $config['session'] ?? [];
    
    session_set_cookie_params([
      'lifetime' => $sessionConfig['lifetime'] ?? 7200,
      'path' => '/',
      'domain' => '',
      'secure' => $sessionConfig['cookie_secure'] ?? false,
      'httponly' => $sessionConfig['cookie_http_only'] ?? true,
      'samesite' => $sessionConfig['cookie_same_site'] ?? 'Lax',
    ]);
    
    session_start();
  }
}

function require_teacher(): void {
  start_session();
  if (!isset($_SESSION['teacher_id'])) {
    header('Location: /teacher/login.php');
    exit;
  }
}

function require_student(): void {
  start_session();
  if (!isset($_SESSION['student_id'])) {
    header('Location: /student/login.php');
    exit;
  }
}

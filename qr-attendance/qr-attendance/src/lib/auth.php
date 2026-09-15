<?php
declare(strict_types=1);

function start_session(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
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
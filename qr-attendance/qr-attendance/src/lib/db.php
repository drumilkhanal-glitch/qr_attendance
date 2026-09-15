<?php
declare(strict_types=1);

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  try {
    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: 3306;
    $name = getenv('DB_NAME') ?: 'qr_attendance';
    $user = getenv('DB_USER') ?: 'user';
    $pass = getenv('DB_PASSWORD') ?: 'password';

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $GLOBALS['pdo'] = $pdo;
    return $pdo;
  } catch (PDOException $e) {
    die('Database Error: ' . $e->getMessage());
  }
}

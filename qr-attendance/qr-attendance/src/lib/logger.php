<?php
declare(strict_types=1);

class Logger {
    private static ?Logger $instance = null;
    private string $logDir;

    private function __construct() {
        $this->logDir = __DIR__ . '/../../logs';
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0777, true);
        }
    }

    public static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log(string $level, string $message, array $context = []): void {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0777, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logMessage = "[$timestamp] $level: $message$contextStr" . PHP_EOL;

        $logFile = $this->logDir . '/app-' . date('Y-m-d') . '.log';
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }

    public function debug(string $message, array $context = []): void {
        if (getenv('APP_DEBUG') === 'true') {
            $this->log('DEBUG', $message, $context);
        }
    }

    public function activity(string $userType, ?int $userId, string $action, string $description = ''): void {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo) return;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (user_type, user_id, action, description, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userType,
                $userId,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            $this->error('Failed to log activity', ['error' => $e->getMessage()]);
        }
    }
}

<?php
declare(strict_types=1);

class RateLimit {
    private static string $prefix = 'rate_limit_';
    
    /**
     * Check if an action is rate-limited
     * @param string $action Unique action identifier (e.g., "scan_1" for student 1)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if rate limit exceeded, false if allowed
     */
    public static function isLimited(string $action, int $maxAttempts, int $windowSeconds): bool {
        $key = self::$prefix . $action;
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'first_attempt_time' => time(),
            ];
        }
        
        $data = &$_SESSION[$key];
        $currentTime = time();
        $timeElapsed = $currentTime - $data['first_attempt_time'];
        
        // Reset if window has passed
        if ($timeElapsed > $windowSeconds) {
            $data['attempts'] = 0;
            $data['first_attempt_time'] = $currentTime;
        }
        
        $data['attempts']++;
        
        return $data['attempts'] > $maxAttempts;
    }
    
    /**
     * Get remaining attempts before rate limit
     */
    public static function getRemaining(string $action, int $maxAttempts, int $windowSeconds): int {
        $key = self::$prefix . $action;
        
        if (!isset($_SESSION[$key])) {
            return $maxAttempts;
        }
        
        $data = $_SESSION[$key];
        $currentTime = time();
        $timeElapsed = $currentTime - $data['first_attempt_time'];
        
        if ($timeElapsed > $windowSeconds) {
            return $maxAttempts;
        }
        
        return max(0, $maxAttempts - $data['attempts']);
    }
}

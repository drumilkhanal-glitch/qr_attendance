<?php
declare(strict_types=1);

class CSRF {
    private static string $tokenKey = '_csrf_token';

    public static function generateToken(): string {
        if (!isset($_SESSION[self::$tokenKey])) {
            $_SESSION[self::$tokenKey] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::$tokenKey];
    }

    public static function getToken(): string {
        return self::generateToken();
    }

    public static function getHiddenField(): string {
        return '<input type="hidden" name="' . self::$tokenKey . '" value="' . htmlspecialchars(self::getToken()) . '">';
    }

    public static function verify(string $token): bool {
        if (!isset($_SESSION[self::$tokenKey])) {
            return false;
        }

        return hash_equals($_SESSION[self::$tokenKey], $token);
    }

    public static function validateRequest(): bool {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return true;
        }

        $token = $_POST[self::$tokenKey] ?? '';
        return self::verify($token);
    }
}

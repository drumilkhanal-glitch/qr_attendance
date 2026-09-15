<?php
declare(strict_types=1);

class Response {
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): never {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success(mixed $data, string $message = 'Success', int $statusCode = 200): never {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, mixed $errors = null): never {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    public static function created(mixed $data, string $message = 'Created successfully'): never {
        self::success($data, $message, 201);
    }

    public static function noContent(): never {
        http_response_code(204);
        exit;
    }

    public static function notFound(string $message = 'Resource not found'): never {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never {
        self::error($message, 403);
    }

    public static function serverError(string $message = 'Internal server error'): never {
        self::error($message, 500);
    }
}

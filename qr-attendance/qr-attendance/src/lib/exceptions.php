<?php
declare(strict_types=1);

require_once __DIR__ . '/logger.php';

class AppException extends Exception {}
class ValidationException extends AppException {}
class AuthenticationException extends AppException {}
class AuthorizationException extends AppException {}
class NotFoundException extends AppException {}

function handleException(Throwable $exception): void {
    $logger = Logger::getInstance();
    $logger->error('Exception: ' . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'code' => $exception->getCode(),
    ]);

    $isDev = getenv('APP_DEBUG') === 'true';

    if ($exception instanceof ValidationException) {
        http_response_code(422);
        $message = $exception->getMessage();
    } elseif ($exception instanceof AuthenticationException) {
        http_response_code(401);
        $message = 'Authentication required';
    } elseif ($exception instanceof AuthorizationException) {
        http_response_code(403);
        $message = 'Access denied';
    } elseif ($exception instanceof NotFoundException) {
        http_response_code(404);
        $message = 'Resource not found';
    } else {
        http_response_code(500);
        $message = $isDev ? $exception->getMessage() : 'An error occurred';
    }

    if (php_sapi_name() === 'cli') {
        echo "Error: $message\n";
        if ($isDev) {
            echo $exception->getTraceAsString() . "\n";
        }
    } else {
        echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    exit(1);
}

set_exception_handler('handleException');
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    Logger::getInstance()->error("PHP Error: $errstr", [
        'file' => $errfile,
        'line' => $errline,
        'errno' => $errno
    ]);
    return false;
});

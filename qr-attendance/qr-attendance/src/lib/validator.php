<?php
declare(strict_types=1);

class Validator {
    private static array $errors = [];

    public static function reset(): void {
        self::$errors = [];
    }

    public static function validate(string $field, mixed $value, string ...$rules): bool {
        $isValid = true;

        foreach ($rules as $rule) {
            if (str_contains($rule, ':')) {
                [$ruleName, $ruleParam] = explode(':', $rule, 2);
            } else {
                $ruleName = $rule;
                $ruleParam = null;
            }

            $method = 'validate' . ucfirst($ruleName);

            if (!method_exists(self::class, $method)) {
                continue;
            }

            if (!self::$method($value, $ruleParam)) {
                $isValid = false;
                self::$errors[$field][] = self::getErrorMessage($field, $ruleName, $ruleParam);
            }
        }

        return $isValid;
    }

    private static function validateRequired(mixed $value): bool {
        return !empty(trim((string)$value ?? ''));
    }

    private static function validateEmail(mixed $value): bool {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function validateMinlength(mixed $value, ?string $length): bool {
        return strlen((string)$value ?? '') >= (int)($length ?? 0);
    }

    private static function validateMaxlength(mixed $value, ?string $length): bool {
        return strlen((string)$value ?? '') <= (int)($length ?? 9999);
    }

    private static function validateNumeric(mixed $value): bool {
        return is_numeric($value);
    }

    private static function validateAlphanumeric(mixed $value): bool {
        return preg_match('/^[a-zA-Z0-9_-]+$/', (string)$value) === 1;
    }

    private static function validatePhone(mixed $value): bool {
        return preg_match('/^[0-9\s\-\+\(\)]{10,}$/', (string)$value) === 1;
    }

    private static function validateUrl(mixed $value): bool {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private static function validateRegex(mixed $value, ?string $pattern): bool {
        return preg_match($pattern ?? '/.*/', (string)$value) === 1;
    }

    private static function validateConfirm(mixed $value, ?string $field): bool {
        return $value === ($_POST[$field] ?? null);
    }

    public static function getErrors(string $field = ''): array {
        if ($field) {
            return self::$errors[$field] ?? [];
        }
        return self::$errors;
    }

    public static function hasErrors(): bool {
        return !empty(self::$errors);
    }

    private static function getErrorMessage(string $field, string $rule, ?string $param): string {
        $messages = [
            'required' => "$field is required",
            'email' => "$field must be a valid email",
            'minlength' => "$field must be at least $param characters",
            'maxlength' => "$field must not exceed $param characters",
            'numeric' => "$field must be numeric",
            'alphanumeric' => "$field must contain only letters, numbers, underscore and dash",
            'phone' => "$field must be a valid phone number",
            'url' => "$field must be a valid URL",
            'regex' => "$field format is invalid",
            'confirm' => "$field does not match",
        ];

        return $messages[$rule] ?? "Validation failed for $field";
    }
}

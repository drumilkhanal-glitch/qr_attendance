<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/validator.php';
require_once __DIR__ . '/../lib/logger.php';

start_session();
$error = '';
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $usernameValue = htmlspecialchars($username);

    Validator::reset();
    Validator::validate('Username', $username, 'required', 'minlength:3', 'maxlength:50');
    Validator::validate('Password', $password, 'required', 'minlength:6');

    if (Validator::hasErrors()) {
        $errors = Validator::getErrors();
        $error = reset($errors)[0] ?? 'Validation failed';
    } else {
        try {
            // Fixed: Removed non-existent first_name and last_name columns
            $stmt = db()->prepare("SELECT id, username, password_hash FROM teacher WHERE username = ? AND is_active = TRUE LIMIT 1");
            $stmt->execute([$username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && password_verify($password, $row['password_hash'])) {
                $_SESSION['teacher_id'] = (int)$row['id'];
                $_SESSION['teacher_username'] = htmlspecialchars($row['username']);
                Logger::getInstance()->activity('teacher', $row['id'], 'login', 'Teacher login successful');
                header('Location: /teacher/dashboard.php');
                exit;
            }
            
            $error = 'Invalid username or password.';
            Logger::getInstance()->activity('teacher', null, 'login_failed', "Failed login attempt for: $username");
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
            Logger::getInstance()->error('Teacher login error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login - Attendance Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --danger: #dc3545;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        html, body {
            height: 100%;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            padding: 1rem;
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px;
        }

        .login-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: slideUp 0.4s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
        }

        .brand {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .brand-subtitle {
            font-size: 0.9rem;
            opacity: 0.95;
        }

        .login-content {
            padding: 2rem;
        }

        .alert {
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background-color: #fee;
            color: #c33;
            border-left: 4px solid #dc3545;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 600;
            color: var(--gray-900);
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 0.95rem;
            transition: border-color 0.2s;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.4rem;
            color: var(--gray-500);
            font-size: 1rem;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .submit-btn {
            width: 100%;
            padding: 0.8rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }

        .login-footer {
            padding: 1.2rem 2rem;
            background: var(--gray-100);
            text-align: center;
            border-top: 1px solid var(--gray-200);
        }

        .login-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        @media (max-width: 480px) {
            .login-header {
                padding: 1.5rem;
            }
            .login-content {
                padding: 1.5rem;
            }
            .login-footer {
                padding: 1rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="brand">Attendance Management</div>
                <div class="brand-subtitle">Teacher Portal</div>
            </div>

            <div class="login-content">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span>!</span>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="e.g., teacher1"
                            value="<?= $usernameValue ?>"
                            required
                            autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Enter password"
                                required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <span id="toggle-icon">●</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Sign In</button>
                </form>
            </div>

            <div class="login-footer">
                <a href="/">← Return to Home</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggle-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '○';
            } else {
                input.type = 'password';
                icon.textContent = '●';
            }
        }
    </script>
</body>
</html>

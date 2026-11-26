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
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Attendance System</h1>
                <p>Teacher Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Enter username"
                        value="<?= $usernameValue ?>"
                        required
                        autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter password"
                        required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>

            <div class="login-footer">
                <a href="/">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>

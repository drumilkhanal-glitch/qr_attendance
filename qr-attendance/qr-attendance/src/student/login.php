<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/validator.php';
require_once __DIR__ . '/../lib/logger.php';

start_session();
$error = '';
$studentNoValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentNo = trim($_POST['student_no'] ?? '');
    $password = $_POST['password'] ?? '';
    $studentNoValue = htmlspecialchars($studentNo);

    Validator::reset();
    Validator::validate('Student Number', $studentNo, 'required', 'minlength:3', 'maxlength:50');
    Validator::validate('Password', $password, 'required', 'minlength:6');

    if (Validator::hasErrors()) {
        $errors = Validator::getErrors();
        $error = reset($errors)[0] ?? 'Validation failed';
    } else {
        try {
            $stmt = db()->prepare("SELECT id, full_name, password_hash FROM student WHERE student_no = ? AND is_active = TRUE LIMIT 1");
            $stmt->execute([$studentNo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && password_verify($password, $row['password_hash'])) {
                $_SESSION['student_id'] = (int)$row['id'];
                $_SESSION['student_name'] = htmlspecialchars($row['full_name']);
                Logger::getInstance()->activity('student', $row['id'], 'login', 'Student login successful');
                header('Location: /student/dashboard.php');
                exit;
            }
            
            $error = 'Invalid student number or password.';
            Logger::getInstance()->activity('student', null, 'login_failed', "Failed login attempt for: $studentNo");
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
            Logger::getInstance()->error('Student login error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - Attendance Management System</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Attendance System</h1>
                <p>Student Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="student_no">Student Number</label>
                    <input 
                        type="text" 
                        id="student_no" 
                        name="student_no" 
                        placeholder="e.g., STD001"
                        value="<?= $studentNoValue ?>"
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

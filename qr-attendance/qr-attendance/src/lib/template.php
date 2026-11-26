<?php
declare(strict_types=1);

// Base template for consistent UI across all pages
function render_page($title, $content, $user_type = null, $logout = false) {
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - QR Attendance System</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <?php if ($logout): ?>
    <div class="navbar">
        <div class="navbar-brand">QR Attendance</div>
        <div class="navbar-user">
            <?php if ($user_type): ?>
                <span><?= ucfirst($user_type) ?> Dashboard</span>
                <a href="/logout.php">Logout</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <?php echo $content; ?>
    </div>

    <div class="footer">
        <p>© 2026 QR Attendance System | All Rights Reserved</p>
    </div>
</body>
</html>
    <?php
}

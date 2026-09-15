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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #764ba2;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #e9ecef;
            --text: #333;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text);
        }

        .navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
        }

        .navbar-user {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .navbar-user a {
            color: var(--text);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            transition: 0.3s;
        }

        .navbar-user a:hover {
            background: var(--light);
        }

        .btn-logout {
            background: var(--danger);
            color: white !important;
        }

        .btn-logout:hover {
            background: #c82333 !important;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .page-title {
            color: white;
            margin-bottom: 30px;
            font-size: 32px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            padding: 25px;
            margin-bottom: 25px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #17a2b8;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-block {
            width: 100%;
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text);
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: 0.3s;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(102, 126, 234, 0.2);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table thead {
            background: var(--light);
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        table tr:hover {
            background: #f0f5ff;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary);
        }

        .stat-card .label {
            color: var(--text-muted);
            margin-top: 8px;
        }

        .footer {
            margin-top: 50px;
            padding: 20px;
            text-align: center;
            color: rgba(255,255,255,0.7);
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .page-title {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if ($logout): ?>
    <div class="navbar">
        <div class="navbar-brand">📚 QR Attendance</div>
        <div class="navbar-user">
            <?php if ($user_type): ?>
                <span><?= ucfirst($user_type) ?> Dashboard</span>
                <a href="/logout.php" class="btn-logout">Logout</a>
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

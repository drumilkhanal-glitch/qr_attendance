<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/template.php';

start_session();

// Send already-logged-in users straight to their home page
if (isset($_SESSION['teacher_id'])) {
    header('Location: /teacher/dashboard.php');
    exit;
}
if (isset($_SESSION['student_id'])) {
    header('Location: /student/dashboard.php');
    exit;
}

$content = <<<'HTML'
<div style="max-width: 800px; margin: 40px auto;">
    <h1 style="text-align: center; margin-bottom: 30px;">QR Attendance System</h1>
    
    <div class="row">
        <div class="card">
            <h2>Teacher Portal</h2>
            <p style="margin: 15px 0;">
                Create QR sessions, mark attendance, manage enrollments, and view reports.
            </p>
            <ul style="margin: 15px 0; padding-left: 20px;">
                <li>Generate QR sessions</li>
                <li>Manual attendance marking</li>
                <li>View detailed reports</li>
                <li>Manage students</li>
            </ul>
            <a href="/teacher/login.php" class="btn btn-primary btn-block">Go to Teacher Portal</a>
        </div>

        <div class="card">
            <h2>Student Portal</h2>
            <p style="margin: 15px 0;">
                Scan QR codes with photo verification. View your attendance history and statistics.
            </p>
            <ul style="margin: 15px 0; padding-left: 20px;">
                <li>Scan QR codes</li>
                <li>Photo verification</li>
                <li>View attendance history</li>
                <li>Track performance</li>
            </ul>
            <a href="/student/login.php" class="btn btn-success btn-block">Go to Student Portal</a>
        </div>
    </div>

    <div class="card" style="margin-top: 30px;">
        <h3>Demo Credentials</h3>
        <div class="row">
            <div>
                <h4>Teacher Account</h4>
                <p><strong>Username:</strong> teacher1</p>
                <p><strong>Password:</strong> Teacher@123</p>
            </div>
            <div>
                <h4>Student Account</h4>
                <p><strong>Student ID:</strong> STD001</p>
                <p><strong>Password:</strong> Student#2024</p>
            </div>
        </div>
        <p style="margin-top: 15px; font-size: 12px; color: #666;">
            More student accounts: STD002 through STD028 (same password)
        </p>
    </div>
</div>
HTML;

echo render_page('Home', $content);

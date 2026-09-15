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
<div style="max-width: 900px; margin: 60px auto;">
    <div style="text-align: center; margin-bottom: 50px;">
        <h1 style="font-size: 48px; color: white; margin-bottom: 15px;">Attendance Management System</h1>
        <p style="font-size: 18px; color: rgba(255,255,255,0.9);">Professional attendance tracking using QR codes and photo verification</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 40px;">
        <div class="card">
            <h2 style="color: var(--primary); margin-bottom: 15px;">Teacher Portal</h2>
            <p style="color: var(--text-muted); margin-bottom: 20px;">Create QR sessions, mark attendance, manage enrollments, and generate comprehensive reports.</p>
            <ul style="margin: 20px 0; color: var(--text);">
                <li>✓ Generate QR sessions</li>
                <li>✓ Manual attendance marking</li>
                <li>✓ Detailed reports</li>
                <li>✓ Student management</li>
            </ul>
            <a href="/teacher/login.php" class="btn btn-primary btn-block" style="padding: 14px;">Access Teacher Portal</a>
        </div>

        <div class="card">
            <h2 style="color: var(--secondary); margin-bottom: 15px;">Student Portal</h2>
            <p style="color: var(--text-muted); margin-bottom: 20px;">Scan QR codes with photo verification. View your attendance history and performance statistics.</p>
            <ul style="margin: 20px 0; color: var(--text);">
                <li>✓ Scan QR codes</li>
                <li>✓ Photo verification</li>
                <li>✓ View attendance history</li>
                <li>✓ Track performance</li>
            </ul>
            <a href="/student/login.php" class="btn btn-success btn-block" style="padding: 14px;">Access Student Portal</a>
        </div>
    </div>

    <div class="card" style="background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%);">
        <h3 style="color: var(--primary); margin-bottom: 15px;">Demo Access Information</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4 style="color: var(--text); margin-bottom: 10px;">Teacher Account</h4>
                <p style="margin: 8px 0;"><strong>Username:</strong> teacher1</p>
                <p style="margin: 8px 0;"><strong>Password:</strong> Teacher@123</p>
            </div>
            <div>
                <h4 style="color: var(--text); margin-bottom: 10px;">Student Account</h4>
                <p style="margin: 8px 0;"><strong>Student ID:</strong> STD001</p>
                <p style="margin: 8px 0;"><strong>Password:</strong> Student#2024</p>
            </div>
        </div>
        <p style="margin-top: 15px; font-size: 14px; color: var(--text-muted);">
            Additional student accounts available: STD002 through STD028 (all use same password)
        </p>
    </div>
</div>
HTML;

echo render_page('Home', $content);

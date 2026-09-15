<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';

start_session();
session_destroy();
header('Location: /index.php');
exit;

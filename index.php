<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/core/helpers.php';

if (!is_installed()) {
    redirect('setup.php');
}

require_once APP_ROOT . '/core/auth.php';
$user = current_user();
if (!$user) {
    redirect('auth/login.php');
}
redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'teacher/dashboard.php');

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';

if (!request_is_post()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

verify_csrf();
$user = current_user();
if ($user) {
    audit_log('auth.logout', 'user', (int)$user['id'], 'Sessão encerrada pelo usuário.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)$params['secure'],
        'httponly' => (bool)$params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
redirect('auth/login.php');

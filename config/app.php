<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$defaults = [
    'name' => 'Gabarito Online',
    'base_path' => '',
    'school_name' => 'Sua Escola',
    'timezone' => 'America/Fortaleza',
    'production' => false,
    'force_https' => false,
    'session_idle_minutes' => 30,
    'session_absolute_hours' => 10,
];

$localFile = __DIR__ . '/app.local.php';
$local = is_file($localFile) ? require $localFile : [];
$GLOBALS['app_config'] = array_merge($defaults, is_array($local) ? $local : []);

date_default_timezone_set((string)$GLOBALS['app_config']['timezone']);

function gradescan_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function gradescan_is_web(): bool
{
    return PHP_SAPI !== 'cli';
}

if ((bool)$GLOBALS['app_config']['production']) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

if (gradescan_is_web() && (bool)$GLOBALS['app_config']['force_https'] && !gradescan_is_https()) {
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($host !== '') {
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}

$GLOBALS['csp_nonce'] = base64_encode(random_bytes(18));

if (gradescan_is_web() && !headers_sent()) {
    $nonce = $GLOBALS['csp_nonce'];
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self' https://fonts.gstatic.com data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' 'nonce-{$nonce}' 'wasm-unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com https://docs.opencv.org; connect-src 'self'; worker-src 'self' blob:");
    if (gradescan_is_https() && (bool)$GLOBALS['app_config']['production']) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name('gradescan_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => gradescan_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$now = time();
if (!empty($_SESSION['user_id'])) {
    $idleLimit = max(5, (int)$GLOBALS['app_config']['session_idle_minutes']) * 60;
    $absoluteLimit = max(1, (int)$GLOBALS['app_config']['session_absolute_hours']) * 3600;
    $lastActivity = (int)($_SESSION['last_activity'] ?? $now);
    $loginStarted = (int)($_SESSION['login_started_at'] ?? $now);

    if (($now - $lastActivity) > $idleLimit || ($now - $loginStarted) > $absoluteLimit) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['auth_notice'] = 'Sua sessão expirou por segurança. Entre novamente.';
    } else {
        $_SESSION['last_activity'] = $now;
        $lastRotation = (int)($_SESSION['session_rotated_at'] ?? 0);
        if (($now - $lastRotation) > 900) {
            session_regenerate_id(true);
            $_SESSION['session_rotated_at'] = $now;
        }
    }
}

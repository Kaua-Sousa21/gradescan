<?php
declare(strict_types=1);

require_once APP_ROOT . '/config/database.php';

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['app_config'][$key] ?? $default;
}

function url(string $path = ''): string
{
    $base = rtrim((string)config('base_path', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    $clean = ltrim($path, '/');
    $file = APP_ROOT . '/' . $clean;
    $version = is_file($file) ? (string)filemtime($file) : (string)time();
    return url($clean) . '?v=' . rawurlencode($version);
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
}

function request_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function format_score(float $score): string
{
    return number_format($score, 1, ',', '.');
}

function badge_class(string $status): string
{
    return match ($status) {
        'active', 'published', 'corrected' => 'badge-success',
        'draft', 'pending' => 'badge-warning',
        'inactive' => 'badge-muted',
        default => 'badge-info',
    };
}

function random_token(int $bytes = 24): string
{
    return bin2hex(random_bytes($bytes));
}

function csp_nonce(): string
{
    return (string)($GLOBALS['csp_nonce'] ?? '');
}

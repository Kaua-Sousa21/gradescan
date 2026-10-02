<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function database_config(): ?array
{
    $file = __DIR__ . '/database.local.php';
    if (!is_file($file)) {
        return null;
    }
    $cfg = require $file;
    return is_array($cfg) ? $cfg : null;
}

function is_installed(): bool
{
    return database_config() !== null;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = database_config();
    if (!$cfg) {
        throw new RuntimeException('Sistema ainda não instalado.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'] ?? '3306',
        $cfg['name']
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);

    return $pdo;
}

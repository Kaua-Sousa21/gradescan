<?php
declare(strict_types=1);

require_once APP_ROOT . '/core/helpers.php';

function client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return substr($ip, 0, 45);
}

function request_user_agent(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
}

function user_agent_hash(): string
{
    return hash('sha256', request_user_agent());
}

function audit_log(
    string $action,
    ?string $entityType = null,
    int|string|null $entityId = null,
    ?string $description = null,
    array $metadata = [],
    ?int $userId = null
): void {
    try {
        if ($userId === null && !empty($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,description,metadata,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $userId ?: null,
            substr($action, 0, 80),
            $entityType ? substr($entityType, 0, 80) : null,
            $entityId !== null ? substr((string)$entityId, 0, 80) : null,
            $description ? substr($description, 0, 500) : null,
            $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            client_ip(),
            request_user_agent(),
        ]);
    } catch (Throwable $e) {
        error_log('[Gabarito Online audit] ' . $e->getMessage());
    }
}

function login_rate_key(string $type, string $value): string
{
    return hash('sha256', $type . '|' . strtolower(trim($value)));
}

function login_rate_row(string $key): ?array
{
    $stmt = db()->prepare('SELECT attempts, UNIX_TIMESTAMP(window_started_at) AS window_started, UNIX_TIMESTAMP(locked_until) AS locked_until FROM login_rate_limits WHERE key_hash=? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function login_rate_limited(string $email, ?int &$retryAfter = null): bool
{
    $retryAfter = 0;
    $now = time();
    $keys = [
        login_rate_key('account', $email),
        login_rate_key('ip', client_ip()),
    ];
    foreach ($keys as $key) {
        $row = login_rate_row($key);
        $lockedUntil = (int)($row['locked_until'] ?? 0);
        if ($lockedUntil > $now) {
            $retryAfter = max($retryAfter, $lockedUntil - $now);
        }
    }
    return $retryAfter > 0;
}

function record_login_failure(string $email): void
{
    $entries = [
        ['key' => login_rate_key('account', $email), 'threshold' => 6],
        ['key' => login_rate_key('ip', client_ip()), 'threshold' => 50],
    ];
    $now = time();
    $windowSeconds = 900;
    $lockSeconds = 600;

    foreach ($entries as $entry) {
        $row = login_rate_row($entry['key']);
        $attempts = 1;
        $windowStarted = $now;
        if ($row && (int)$row['window_started'] > ($now - $windowSeconds)) {
            $attempts = (int)$row['attempts'] + 1;
            $windowStarted = (int)$row['window_started'];
        }
        $lockedUntil = $attempts >= $entry['threshold'] ? date('Y-m-d H:i:s', $now + $lockSeconds) : null;
        $stmt = db()->prepare('INSERT INTO login_rate_limits (key_hash,attempts,window_started_at,last_attempt_at,locked_until) VALUES (?,?,FROM_UNIXTIME(?),NOW(),?) ON DUPLICATE KEY UPDATE attempts=VALUES(attempts),window_started_at=VALUES(window_started_at),last_attempt_at=NOW(),locked_until=VALUES(locked_until)');
        $stmt->execute([$entry['key'], $attempts, $windowStarted, $lockedUntil]);
    }
}

function clear_account_login_failures(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_rate_limits WHERE key_hash=?');
    $stmt->execute([login_rate_key('account', $email)]);
}

function cleanup_old_security_rows(): void
{
    if (random_int(1, 100) !== 1) {
        return;
    }
    try {
        db()->exec("DELETE FROM login_rate_limits WHERE last_attempt_at < (NOW() - INTERVAL 2 DAY)");
    } catch (Throwable $e) {
        error_log('[Gabarito Online cleanup] ' . $e->getMessage());
    }
}

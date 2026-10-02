<?php
declare(strict_types=1);

require_once APP_ROOT . '/core/helpers.php';
require_once APP_ROOT . '/core/csrf.php';
require_once APP_ROOT . '/core/security.php';

function current_user(): ?array
{
    static $cached = false;
    static $user = null;

    if ($cached) {
        return $user;
    }
    $cached = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    if (!empty($_SESSION['ua_hash']) && !hash_equals((string)$_SESSION['ua_hash'], user_agent_hash())) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['auth_notice'] = 'Sua sessão foi encerrada por segurança. Entre novamente.';
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role, active, auth_version FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();

    if (!$row || !(int)$row['active']) {
        unset($_SESSION['user_id'], $_SESSION['auth_version']);
        return null;
    }

    if ((int)($_SESSION['auth_version'] ?? 0) !== (int)$row['auth_version']) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['auth_notice'] = 'Seu acesso foi atualizado pela administração. Entre novamente.';
        return null;
    }

    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    $user = $row;
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        redirect('auth/login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        require APP_ROOT . '/partials/403.php';
        exit;
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function is_teacher(): bool
{
    return (current_user()['role'] ?? null) === 'teacher';
}

function teacher_has_class(int $teacherId, int $classId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM class_teachers WHERE teacher_id = ? AND class_id = ? LIMIT 1');
    $stmt->execute([$teacherId, $classId]);
    return (bool)$stmt->fetchColumn();
}

function can_access_exam(array $user, array $exam): bool
{
    return $user['role'] === 'admin' || (int)$exam['teacher_id'] === (int)$user['id'];
}

function active_admin_count(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1")->fetchColumn();
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/core/helpers.php';

if (!is_installed()) {
    redirect('setup.php');
}

require_once APP_ROOT . '/core/auth.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$notice = (string)($_SESSION['auth_notice'] ?? '');
unset($_SESSION['auth_notice']);

if (request_is_post()) {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    $retryAfter = 0;
    if (login_rate_limited($email, $retryAfter)) {
        $minutes = max(1, (int)ceil($retryAfter / 60));
        $error = "Muitas tentativas de acesso. Tente novamente em cerca de {$minutes} minuto(s).";
    } else {
        $stmt = db()->prepare('SELECT id, password, active, auth_version FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        $valid = $row && (int)$row['active'] === 1 && password_verify($password, (string)$row['password']);

        if ($valid) {
            if (password_needs_rehash((string)$row['password'], PASSWORD_DEFAULT)) {
                $rehash = db()->prepare('UPDATE users SET password=? WHERE id=?');
                $rehash->execute([password_hash($password, PASSWORD_DEFAULT), (int)$row['id']]);
            }

            $update = db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?');
            $update->execute([(int)$row['id']]);
            clear_account_login_failures($email);
            cleanup_old_security_rows();

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$row['id'];
            $_SESSION['auth_version'] = (int)$row['auth_version'];
            $_SESSION['login_started_at'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['session_rotated_at'] = time();
            $_SESSION['ua_hash'] = user_agent_hash();
            audit_log('auth.login_success', 'user', (int)$row['id'], 'Login realizado com sucesso.', [], (int)$row['id']);
            redirect('index.php');
        }

        record_login_failure($email);
        audit_log('auth.login_failed', 'user', $row ? (int)$row['id'] : null, 'Tentativa de login sem sucesso.', ['email' => $email], $row ? (int)$row['id'] : null);
        usleep(random_int(250000, 450000));
        $error = 'E-mail ou senha incorretos.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0f172a">
<title>Entrar • <?= e((string)config('name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-visual">
    <div class="visual-grid"></div>
    <div class="visual-orb orb-one"></div><div class="visual-orb orb-two"></div>
    <div class="auth-brand"><span class="brand-mark">G</span><strong>Gabarito Online</strong></div>
    <div class="visual-content">
        <span class="eyebrow light">CORREÇÃO INTELIGENTE</span>
        <h1>Menos tempo corrigindo.<br><em>Mais tempo ensinando.</em></h1>
        <p>Crie provas, gere gabaritos personalizados e corrija respostas usando a câmera do celular.</p>
        <div class="visual-stats"><div><strong>01</strong><span>Crie a prova</span></div><div><strong>02</strong><span>Escaneie</span></div><div><strong>03</strong><span>Veja a nota</span></div></div>
    </div>
</div>
<main class="auth-panel">
    <div class="auth-card login-card">
        <div class="mobile-brand"><span class="brand-mark">G</span><strong>Gabarito Online</strong></div>
        <div class="auth-heading"><span class="eyebrow">BEM-VINDO</span><h2>Acesse sua conta</h2><p>Entre com o acesso criado pela administração da escola.</p></div>
        <?php if ($notice): ?><div class="alert alert-info"><?= e($notice) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-stack" autocomplete="on">
            <?= csrf_field() ?>
            <label class="field"><span>E-mail</span><input type="email" name="email" value="<?= old('email') ?>" placeholder="seuemail@escola.com" autocomplete="username" required></label>
            <label class="field"><span>Senha</span><div class="password-wrap"><input type="password" name="password" id="password" placeholder="••••••••" autocomplete="current-password" required><button type="button" class="password-toggle" data-password-toggle="#password">Ver</button></div></label>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Entrar na plataforma <span>→</span></button>
        </form>
        <div class="auth-foot"><span>Ambiente protegido</span><span>•</span><span><?= e((string)config('school_name')) ?></span></div>
    </div>
</main>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body></html>

<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/core/helpers.php';
require_once APP_ROOT . '/core/csrf.php';

$dbFile = APP_ROOT . '/config/database.local.php';
$appFile = APP_ROOT . '/config/app.local.php';
$alreadyInstalled = is_file($dbFile);
$error = '';
$success = false;

if ($alreadyInstalled) {
    http_response_code(404);
    exit('Página não encontrada.');
}

if (request_is_post() && !$alreadyInstalled) {
    verify_csrf();
    $host = trim((string)($_POST['db_host'] ?? 'localhost'));
    $port = trim((string)($_POST['db_port'] ?? '3306'));
    $name = trim((string)($_POST['db_name'] ?? ''));
    $user = trim((string)($_POST['db_user'] ?? ''));
    $pass = (string)($_POST['db_pass'] ?? '');
    $adminName = trim((string)($_POST['admin_name'] ?? ''));
    $adminEmail = strtolower(trim((string)($_POST['admin_email'] ?? '')));
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $schoolName = trim((string)($_POST['school_name'] ?? 'Sua Escola'));

    if (!$name || !$user || !$adminName || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPass) < 10) {
        $error = 'Preencha os dados obrigatórios. A senha do administrador precisa ter pelo menos 10 caracteres.';
    } else {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $sql = file_get_contents(APP_ROOT . '/database/schema.sql');
            if ($sql === false) {
                throw new RuntimeException('Não foi possível carregar o schema do banco.');
            }
            $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$adminEmail]);
            $adminId = (int)($check->fetchColumn() ?: 0);
            if (!$adminId) {
                $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role,active,auth_version) VALUES (?,?,?,'admin',1,1)");
                $stmt->execute([$adminName, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT)]);
                $adminId = (int)$pdo->lastInsertId();
            }

            $dbConfig = "<?php\nreturn " . var_export([
                'host' => $host,
                'port' => $port,
                'name' => $name,
                'user' => $user,
                'pass' => $pass,
            ], true) . ";\n";

            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/setup.php');
            $basePath = rtrim(dirname($scriptName), '/.');
            if ($basePath === '/') $basePath = '';
            $hostName = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            $isLocal = str_starts_with($hostName, 'localhost') || str_starts_with($hostName, '127.0.0.1') || str_starts_with($hostName, '[::1]');
            $appConfig = "<?php\nreturn " . var_export([
                'name' => 'Gabarito Online',
                'base_path' => $basePath,
                'school_name' => mb_substr($schoolName ?: 'Sua Escola', 0, 140),
                'timezone' => 'America/Fortaleza',
                'production' => !$isLocal,
                'force_https' => !$isLocal,
                'session_idle_minutes' => 30,
                'session_absolute_hours' => 10,
            ], true) . ";\n";

            if (file_put_contents($appFile, $appConfig, LOCK_EX) === false || file_put_contents($dbFile, $dbConfig, LOCK_EX) === false) {
                @unlink($dbFile);
                @unlink($appFile);
                throw new RuntimeException('Não foi possível gravar os arquivos de configuração. Verifique a permissão da pasta config/.');
            }
            @chmod($appFile, 0600);
            @chmod($dbFile, 0600);

            $audit = $pdo->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,description,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)');
            $audit->execute([$adminId, 'system.installed', 'system', 'gradescan', 'Instalação inicial concluída.', substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45), substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500)]);
            $success = true;
        } catch (Throwable $e) {
            $error = 'Falha na instalação: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalação • Gabarito Online</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body setup-body">
<div class="setup-shell">
    <section class="setup-intro">
        <div class="brand-pill"><span class="brand-mark">G</span><strong>Gabarito Online</strong></div>
        <span class="eyebrow light">INSTALAÇÃO RÁPIDA</span>
        <h1>Deixe a correção de provas <em>mais inteligente.</em></h1>
        <p>Conecte o MySQL, crie o primeiro administrador e a plataforma prepara as tabelas automaticamente.</p>
        <div class="setup-features"><span>✓ PHP 8+</span><span>✓ MySQL</span><span>✓ HTTPS</span><span>✓ Scanner pelo celular</span></div>
    </section>
    <section class="auth-card setup-card">
        <?php if ($success): ?>
            <div class="success-orb">✓</div>
            <h2>Gabarito Online está pronto!</h2>
            <p>O instalador ficará bloqueado automaticamente nas próximas visitas. Entre pelo login usando o administrador criado agora.</p>
            <a class="btn btn-primary btn-block" href="auth/login.php">Ir para o login</a>
        <?php else: ?>
            <div class="auth-heading"><span class="eyebrow">CONFIGURAÇÃO</span><h2>Instalar plataforma</h2><p>Use os dados do banco criado no painel da Hostinger.</p></div>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <div class="form-section-title">Banco de dados</div>
                <div class="form-grid two">
                    <label class="field"><span>Host</span><input name="db_host" value="<?= e((string)($_POST['db_host'] ?? 'localhost')) ?>" required></label>
                    <label class="field"><span>Porta</span><input name="db_port" value="<?= e((string)($_POST['db_port'] ?? '3306')) ?>" required></label>
                </div>
                <label class="field"><span>Nome do banco</span><input name="db_name" value="<?= e((string)($_POST['db_name'] ?? '')) ?>" placeholder="u123456789_gradescan" required></label>
                <div class="form-grid two">
                    <label class="field"><span>Usuário</span><input name="db_user" value="<?= e((string)($_POST['db_user'] ?? '')) ?>" required></label>
                    <label class="field"><span>Senha do banco</span><input type="password" name="db_pass"></label>
                </div>
                <div class="form-section-title">Escola e administrador</div>
                <label class="field"><span>Nome da escola</span><input name="school_name" value="<?= e((string)($_POST['school_name'] ?? '')) ?>" placeholder="EEMTI Escola Modelo"></label>
                <label class="field"><span>Nome do administrador</span><input name="admin_name" value="<?= e((string)($_POST['admin_name'] ?? '')) ?>" required></label>
                <label class="field"><span>E-mail</span><input type="email" name="admin_email" value="<?= e((string)($_POST['admin_email'] ?? '')) ?>" required></label>
                <label class="field"><span>Senha</span><input type="password" name="admin_pass" minlength="10" required><small>Mínimo de 10 caracteres. Prefira uma frase-senha longa e exclusiva.</small></label>
                <button class="btn btn-primary btn-block btn-lg" type="submit">Instalar Gabarito Online <span>→</span></button>
            </form>
        <?php endif; ?>
    </section>
</div>
</body></html>

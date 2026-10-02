<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';
$user = require_role('admin');
$pdo = db();

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT id,name,email,role,active,auth_version FROM users WHERE id=?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}

if (request_is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'toggle') {
        $id = post_int('id');
        $stmt = $pdo->prepare('SELECT id,name,email,role,active FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('danger', 'Usuário não encontrado.');
        } elseif ($id === (int)$user['id']) {
            flash('warning', 'Você não pode desativar o próprio usuário.');
        } elseif ($target['role'] === 'admin' && (int)$target['active'] === 1 && active_admin_count() <= 1) {
            flash('danger', 'Não é possível desativar o último administrador ativo.');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET active=IF(active=1,0,1),auth_version=auth_version+1 WHERE id=?');
            $stmt->execute([$id]);
            $newActive = (int)$target['active'] === 1 ? 0 : 1;
            audit_log('user.status_changed', 'user', $id, 'Status de acesso alterado pela administração.', ['name'=>$target['name'],'from'=>(int)$target['active'],'to'=>$newActive]);
            flash('success', 'Status do usuário atualizado.');
        }
        redirect('admin/users.php');
    }

    $id = post_int('id');
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $role = in_array($_POST['role'] ?? '', ['admin','teacher'], true) ? (string)$_POST['role'] : 'teacher';
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Informe nome e e-mail válidos.');
        redirect('admin/users.php' . ($id ? '?edit='.$id : ''));
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare('SELECT id,name,email,role,active,auth_version FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $before = $stmt->fetch();
            if (!$before) {
                throw new RuntimeException('Usuário não encontrado.');
            }

            if ($id === (int)$user['id']) {
                $role = 'admin';
            }
            if ($before['role'] === 'admin' && $role !== 'admin' && (int)$before['active'] === 1 && active_admin_count() <= 1) {
                throw new RuntimeException('Não é possível remover o perfil do último administrador ativo.');
            }
            if ($password !== '' && strlen($password) < 10) {
                throw new RuntimeException('A nova senha precisa ter pelo menos 10 caracteres.');
            }

            $newVersion = (int)$before['auth_version'] + 1;
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET name=?,email=?,role=?,password=?,auth_version=? WHERE id=?');
                $stmt->execute([$name,$email,$role,password_hash($password,PASSWORD_DEFAULT),$newVersion,$id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name=?,email=?,role=?,auth_version=? WHERE id=?');
                $stmt->execute([$name,$email,$role,$newVersion,$id]);
            }
            if ($id === (int)$user['id']) {
                $_SESSION['auth_version'] = $newVersion;
            }
            audit_log('user.updated', 'user', $id, 'Usuário atualizado pela administração.', [
                'before'=>['name'=>$before['name'],'email'=>$before['email'],'role'=>$before['role']],
                'after'=>['name'=>$name,'email'=>$email,'role'=>$role],
                'password_changed'=>$password !== '',
            ]);
            flash('success', 'Usuário atualizado com sucesso.');
        } else {
            if (strlen($password) < 10) {
                throw new RuntimeException('A senha inicial precisa ter pelo menos 10 caracteres.');
            }
            $stmt = $pdo->prepare('INSERT INTO users (name,email,password,role,active,auth_version) VALUES (?,?,?,?,1,1)');
            $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
            $newId = (int)$pdo->lastInsertId();
            audit_log('user.created', 'user', $newId, 'Novo acesso criado pela administração.', ['name'=>$name,'email'=>$email,'role'=>$role]);
            flash('success', 'Acesso criado com sucesso.');
        }
    } catch (PDOException $e) {
        flash('danger', $e->getCode() === '23000' ? 'Já existe um usuário com esse e-mail.' : 'Não foi possível salvar o usuário.');
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('admin/users.php');
}

$search = trim((string)($_GET['q'] ?? ''));
$params = [];
$sql = "SELECT id,name,email,role,active,created_at,last_login_at FROM users";
if ($search !== '') {
    $sql .= " WHERE name LIKE ? OR email LIKE ?";
    $params = ["%{$search}%","%{$search}%"];
}
$sql .= " ORDER BY active DESC, role ASC, name ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $users = $stmt->fetchAll();
$pageTitle = 'Usuários';
require APP_ROOT . '/partials/header.php';
?>
<div class="page-toolbar reveal">
    <div><span class="eyebrow">ACESSOS</span><h2>Professores e administradores</h2><p>Crie e controle quem pode entrar na plataforma.</p></div>
    <a class="btn btn-primary" href="<?= e(url('admin/users.php#user-form')) ?>">+ Novo acesso</a>
</div>
<div class="split-layout">
<section class="panel reveal">
    <div class="panel-head wrap"><form class="search-bar" method="get"><span>⌕</span><input name="q" value="<?= e($search) ?>" placeholder="Buscar por nome ou e-mail"><button>Buscar</button></form><span class="count-pill"><?= count($users) ?> usuários</span></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Usuário</th><th>Perfil</th><th>Status</th><th>Último acesso</th><th></th></tr></thead><tbody>
    <?php foreach ($users as $row): ?>
    <tr><td data-label="Usuário"><div class="person-cell"><div class="avatar small"><?= e(mb_strtoupper(mb_substr($row['name'],0,1))) ?></div><div><strong><?= e($row['name']) ?></strong><span><?= e($row['email']) ?></span></div></div></td>
    <td data-label="Perfil"><span class="badge <?= $row['role']==='admin'?'badge-purple':'badge-info' ?>"><?= $row['role']==='admin'?'Administrador':'Professor' ?></span></td>
    <td data-label="Status"><span class="status-dot <?= (int)$row['active']?'on':'off' ?>"></span><?= (int)$row['active']?'Ativo':'Inativo' ?></td>
    <td data-label="Último acesso"><?= $row['last_login_at'] ? date('d/m/Y H:i', strtotime($row['last_login_at'])) : 'Nunca' ?></td>
    <td class="table-actions"><a class="icon-action" href="<?= e(url('admin/users.php?edit='.$row['id'].'#user-form')) ?>" title="Editar">✎</a>
    <?php if ((int)$row['id'] !== (int)$user['id']): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="icon-action" title="Ativar/desativar" data-confirm="Alterar o status deste usuário?">◌</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<aside class="panel form-panel reveal delay-1" id="user-form">
    <div class="panel-head"><div><span class="eyebrow"><?= $editing?'EDIÇÃO':'NOVO ACESSO' ?></span><h3><?= $editing?'Editar usuário':'Criar usuário' ?></h3></div><?php if($editing): ?><a class="text-link" href="<?= e(url('admin/users.php#user-form')) ?>">Cancelar</a><?php endif; ?></div>
    <form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id']??0) ?>">
        <label class="field"><span>Nome completo</span><input name="name" value="<?= e($editing['name']??'') ?>" placeholder="Nome do professor" required></label>
        <label class="field"><span>E-mail de acesso</span><input type="email" name="email" value="<?= e($editing['email']??'') ?>" placeholder="professor@escola.com" required></label>
        <?php if($editing && (int)$editing['id']===(int)$user['id']): ?>
            <input type="hidden" name="role" value="admin"><label class="field"><span>Perfil</span><input value="Administrador" disabled><small>Seu próprio perfil de administrador não pode ser removido.</small></label>
        <?php else: ?>
            <label class="field"><span>Perfil</span><select name="role"><option value="teacher" <?= ($editing['role']??'teacher')==='teacher'?'selected':'' ?>>Professor</option><option value="admin" <?= ($editing['role']??'')==='admin'?'selected':'' ?>>Administrador</option></select></label>
        <?php endif; ?>
        <label class="field"><span><?= $editing?'Nova senha (opcional)':'Senha inicial' ?></span><div class="password-wrap"><input id="newPassword" type="password" name="password" minlength="10" <?= $editing?'':'required' ?>><button class="password-toggle" type="button" data-password-toggle="#newPassword">Ver</button></div><small><?= $editing?'Deixe vazio para manter a senha atual.':'Mínimo de 10 caracteres.' ?></small></label>
        <button class="btn btn-primary btn-block" type="submit"><?= $editing?'Salvar alterações':'Criar acesso' ?> <span>→</span></button>
    </form>
</aside>
</div>
<?php require APP_ROOT . '/partials/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';
$user = require_role('admin');
$pdo = db();

$q = trim((string)($_GET['q'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$params = [];
$where = [];

if ($q !== '') {
    $where[] = '(u.name LIKE ? OR a.description LIKE ? OR a.entity_id LIKE ? OR a.ip_address LIKE ?)';
    $like = "%{$q}%";
    array_push($params, $like, $like, $like, $like);
}
if ($action !== '') {
    $where[] = 'a.action = ?';
    $params[] = $action;
}

$sql = "SELECT a.*,u.name user_name,u.email user_email FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY a.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$todayCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURDATE()")->fetchColumn();
$loginFailures = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='auth.login_failed' AND created_at >= (NOW() - INTERVAL 24 HOUR)")->fetchColumn();
$pageTitle = 'Auditoria';
require APP_ROOT . '/partials/header.php';
?>
<div class="page-toolbar reveal"><div><span class="eyebrow">SEGURANÇA E RASTREABILIDADE</span><h2>Registro de atividades</h2><p>Acompanhe logins, alterações de cadastros, correções e ações administrativas.</p></div></div>
<section class="stats-grid compact-stats stagger">
    <article class="stat-card"><div class="stat-icon blue">≋</div><div><span>Eventos hoje</span><strong><?= $todayCount ?></strong><small>registrados</small></div></article>
    <article class="stat-card"><div class="stat-icon amber">!</div><div><span>Falhas de login</span><strong><?= $loginFailures ?></strong><small>últimas 24h</small></div></article>
</section>
<section class="panel reveal">
    <div class="panel-head wrap">
        <form class="audit-filters" method="get">
            <label class="field"><span>Buscar</span><input name="q" value="<?= e($q) ?>" placeholder="Usuário, ação, IP ou registro"></label>
            <label class="field"><span>Evento</span><select name="action"><option value="">Todos</option><?php foreach($actions as $item): ?><option value="<?= e((string)$item) ?>" <?= $action===$item?'selected':'' ?>><?= e((string)$item) ?></option><?php endforeach; ?></select></label>
            <button class="btn btn-soft" type="submit">Filtrar</button>
            <?php if($q!==''||$action!==''): ?><a class="text-link" href="<?= e(url('admin/audit.php')) ?>">Limpar</a><?php endif; ?>
        </form>
        <span class="count-pill">até 200 eventos</span>
    </div>
    <div class="table-wrap"><table class="data-table audit-table"><thead><tr><th>Data</th><th>Usuário</th><th>Evento</th><th>Registro</th><th>Descrição</th><th>IP</th></tr></thead><tbody>
    <?php if(!$logs): ?><tr><td colspan="6"><div class="empty-row">Nenhum evento encontrado.</div></td></tr><?php endif; ?>
    <?php foreach($logs as $log): ?>
        <tr>
            <td data-label="Data"><strong><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></strong></td>
            <td data-label="Usuário"><?php if($log['user_name']): ?><div><strong><?= e($log['user_name']) ?></strong><span class="table-subtext"><?= e($log['user_email']) ?></span></div><?php else: ?><span class="badge badge-muted">Sistema/visitante</span><?php endif; ?></td>
            <td data-label="Evento"><code class="code-pill audit-code"><?= e($log['action']) ?></code></td>
            <td data-label="Registro"><?= e(trim(($log['entity_type'] ?? '') . ' ' . ($log['entity_id'] ?? ''))) ?: '—' ?></td>
            <td data-label="Descrição"><?= e($log['description'] ?? '') ?: '—' ?></td>
            <td data-label="IP"><code><?= e($log['ip_address'] ?? '') ?></code></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require APP_ROOT . '/partials/footer.php'; ?>

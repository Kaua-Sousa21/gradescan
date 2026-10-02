<?php
$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
function nav_active(string $needle): string {
    global $currentPath;
    return str_contains($currentPath, $needle) ? 'active' : '';
}
?>
<aside class="sidebar" data-sidebar>
    <div class="sidebar-brand">
        <div class="brand-mark">G</div>
        <div><strong><?= e((string)config('name')) ?></strong><span>Correção inteligente</span></div>
    </div>
    <nav class="sidebar-nav">
        <span class="nav-label">Navegação</span>
        <?php if ($user['role'] === 'admin'): ?>
            <a class="nav-item <?= nav_active('/admin/dashboard.php') ?>" href="<?= e(url('admin/dashboard.php')) ?>"><i>⌂</i><span>Dashboard</span></a>
            <a class="nav-item <?= nav_active('/admin/users.php') ?>" href="<?= e(url('admin/users.php')) ?>"><i>◉</i><span>Usuários</span></a>
            <a class="nav-item <?= nav_active('/admin/students.php') ?>" href="<?= e(url('admin/students.php')) ?>"><i>◎</i><span>Alunos</span></a>
            <a class="nav-item <?= nav_active('/classes/') ?>" href="<?= e(url('classes/index.php')) ?>"><i>▦</i><span>Turmas</span></a>
            <a class="nav-item <?= nav_active('/exams/') ?>" href="<?= e(url('exams/index.php')) ?>"><i>✓</i><span>Provas e resultados</span></a>
            <a class="nav-item <?= nav_active('/admin/audit.php') ?>" href="<?= e(url('admin/audit.php')) ?>"><i>≋</i><span>Auditoria</span></a>
            <a class="nav-item <?= nav_active('/admin/backup.php') ?>" href="<?= e(url('admin/backup.php')) ?>"><i>⇩</i><span>Backup</span></a>
            <a class="nav-item <?= nav_active('/admin/system.php') ?>" href="<?= e(url('admin/system.php')) ?>"><i>◈</i><span>Diagnóstico</span></a>
        <?php else: ?>
            <a class="nav-item <?= nav_active('/teacher/dashboard.php') ?>" href="<?= e(url('teacher/dashboard.php')) ?>"><i>⌂</i><span>Dashboard</span></a>
            <a class="nav-item <?= nav_active('/classes/') ?>" href="<?= e(url('classes/index.php')) ?>"><i>▦</i><span>Minhas turmas</span></a>
            <a class="nav-item <?= nav_active('/exams/') ?>" href="<?= e(url('exams/index.php')) ?>"><i>✓</i><span>Minhas provas</span></a>
            <a class="nav-item <?= nav_active('/exams/scan.php') ?>" href="<?= e(url('exams/index.php')) ?>"><i>◫</i><span>Escanear gabarito</span></a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
        <div class="scan-tip">
            <div class="scan-tip-icon">⌁</div>
            <strong>Correção pela câmera</strong>
            <p>Use os marcadores do gabarito para uma leitura mais precisa.</p>
        </div>
        <form class="sidebar-logout" method="post" action="<?= e(url('auth/logout.php')) ?>"><?= csrf_field() ?><button class="nav-item danger" type="submit"><i>↗</i><span>Sair</span></button></form>
    </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-backdrop></div>

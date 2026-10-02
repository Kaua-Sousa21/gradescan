<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';
$user = require_role('admin');

$pdo = db();
$stats = [
    'students' => (int)$pdo->query("SELECT COUNT(*) FROM students WHERE active=1")->fetchColumn(),
    'teachers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND active=1")->fetchColumn(),
    'classes' => (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE active=1")->fetchColumn(),
    'exams' => (int)$pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
    'results' => (int)$pdo->query("SELECT COUNT(*) FROM results")->fetchColumn(),
];

$recent = $pdo->query("SELECT e.id,e.title,e.created_at,c.name class_name,s.name subject_name,u.name teacher_name,
    (SELECT COUNT(*) FROM results r WHERE r.exam_id=e.id) corrected,
    (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=e.class_id) total_students
    FROM exams e
    JOIN classes c ON c.id=e.class_id
    JOIN subjects s ON s.id=e.subject_id
    JOIN users u ON u.id=e.teacher_id
    ORDER BY e.created_at DESC LIMIT 6")->fetchAll();

$pageTitle = 'Visão geral';
require APP_ROOT . '/partials/header.php';
?>
<section class="hero-panel reveal">
    <div>
        <span class="hero-kicker">PAINEL ADMINISTRATIVO</span>
        <h2>Olá, <?= e(explode(' ', $user['name'])[0]) ?>. A escola está <span>pronta para corrigir.</span></h2>
        <p>Gerencie pessoas, turmas e acompanhe o fluxo de avaliações em um único lugar.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-light" href="<?= e(url('admin/students.php')) ?>">+ Novo aluno</a>
        <a class="btn btn-primary" href="<?= e(url('admin/users.php')) ?>">+ Novo professor</a>
    </div>
</section>

<section class="stats-grid stagger">
    <article class="stat-card"><div class="stat-icon purple">◎</div><div><span>Alunos ativos</span><strong data-count="<?= $stats['students'] ?>"><?= $stats['students'] ?></strong><small>cadastrados</small></div></article>
    <article class="stat-card"><div class="stat-icon blue">◉</div><div><span>Professores</span><strong data-count="<?= $stats['teachers'] ?>"><?= $stats['teachers'] ?></strong><small>com acesso</small></div></article>
    <article class="stat-card"><div class="stat-icon green">▦</div><div><span>Turmas</span><strong data-count="<?= $stats['classes'] ?>"><?= $stats['classes'] ?></strong><small>ativas</small></div></article>
    <article class="stat-card"><div class="stat-icon amber">✓</div><div><span>Correções</span><strong data-count="<?= $stats['results'] ?>"><?= $stats['results'] ?></strong><small>realizadas</small></div></article>
</section>

<div class="dashboard-grid">
    <section class="panel reveal">
        <div class="panel-head"><div><span class="eyebrow">ATIVIDADE</span><h3>Provas recentes</h3></div><a class="text-link" href="<?= e(url('exams/index.php')) ?>">Ver todas →</a></div>
        <?php if (!$recent): ?>
            <div class="empty-state"><div class="empty-icon">✓</div><h4>Nenhuma prova criada</h4><p>Quando os professores criarem avaliações, elas aparecerão aqui.</p></div>
        <?php else: ?>
            <div class="activity-list">
            <?php foreach ($recent as $exam):
                $total = max(1, (int)$exam['total_students']);
                $corrected = (int)$exam['corrected'];
                $pct = min(100, (int)round($corrected / $total * 100));
            ?>
                <a class="activity-item" href="<?= e(url('exams/view.php?id=' . $exam['id'])) ?>">
                    <div class="activity-icon">✓</div>
                    <div class="activity-copy"><strong><?= e($exam['title']) ?></strong><span><?= e($exam['class_name']) ?> • <?= e($exam['subject_name']) ?> • <?= e($exam['teacher_name']) ?></span><div class="mini-progress"><i style="width:<?= $pct ?>%"></i></div></div>
                    <div class="activity-meta"><strong><?= $corrected ?>/<?= (int)$exam['total_students'] ?></strong><span>corrigidos</span></div>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="panel quick-panel reveal delay-1">
        <div class="panel-head"><div><span class="eyebrow">ATALHOS</span><h3>Ações rápidas</h3></div></div>
        <a class="quick-action" href="<?= e(url('classes/index.php')) ?>"><span class="quick-icon">▦</span><div><strong>Gerenciar turmas</strong><small>Alunos e professores</small></div><b>→</b></a>
        <a class="quick-action" href="<?= e(url('admin/users.php')) ?>"><span class="quick-icon">◉</span><div><strong>Criar acesso</strong><small>Professor ou administrador</small></div><b>→</b></a>
        <a class="quick-action" href="<?= e(url('admin/students.php')) ?>"><span class="quick-icon">◎</span><div><strong>Cadastrar aluno</strong><small>Nome e matrícula</small></div><b>→</b></a>
        <a class="quick-action" href="<?= e(url('admin/audit.php')) ?>"><span class="quick-icon">≋</span><div><strong>Ver auditoria</strong><small>Logins e alterações</small></div><b>→</b></a>
        <a class="quick-action" href="<?= e(url('admin/backup.php')) ?>"><span class="quick-icon">⇩</span><div><strong>Gerar backup</strong><small>Cópia do banco de dados</small></div><b>→</b></a>
        <div class="insight-card"><span>VISÃO GERAL</span><strong><?= $stats['exams'] ?> provas</strong><p>já foram criadas na plataforma.</p></div>
    </aside>
</div>
<?php require APP_ROOT . '/partials/footer.php'; ?>

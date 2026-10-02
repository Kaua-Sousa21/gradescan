<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';
$user = require_role('teacher');
$pdo = db();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT class_id) FROM class_teachers WHERE teacher_id=?");
$stmt->execute([$user['id']]); $classCount = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id=?");
$stmt->execute([$user['id']]); $examCount = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM results r JOIN exams e ON e.id=r.exam_id WHERE e.teacher_id=?");
$stmt->execute([$user['id']]); $resultCount = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT cs.student_id) FROM class_teachers ct JOIN class_students cs ON cs.class_id=ct.class_id WHERE ct.teacher_id=?");
$stmt->execute([$user['id']]); $studentCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT e.id,e.title,e.created_at,c.name class_name,s.name subject_name,
    (SELECT COUNT(*) FROM results r WHERE r.exam_id=e.id) corrected,
    (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=e.class_id) total_students
    FROM exams e JOIN classes c ON c.id=e.class_id JOIN subjects s ON s.id=e.subject_id
    WHERE e.teacher_id=? ORDER BY e.created_at DESC LIMIT 5");
$stmt->execute([$user['id']]); $recent = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT DISTINCT c.id,c.name,c.grade_level,c.school_year,
    (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=c.id) students
    FROM classes c JOIN class_teachers ct ON ct.class_id=c.id WHERE ct.teacher_id=? AND c.active=1 ORDER BY c.name LIMIT 4");
$stmt->execute([$user['id']]); $classes = $stmt->fetchAll();

$pageTitle = 'Meu painel';
require APP_ROOT . '/partials/header.php';
?>
<section class="hero-panel teacher-hero reveal">
    <div><span class="hero-kicker">BOM TRABALHO, PROFESSOR</span><h2>Suas avaliações, turmas e resultados <span>em um só lugar.</span></h2><p>Crie uma prova, imprima os gabaritos e use a câmera do celular para corrigir em segundos.</p></div>
    <div class="hero-actions"><a class="btn btn-light" href="<?= e(url('classes/index.php')) ?>">Minhas turmas</a><a class="btn btn-primary" href="<?= e(url('exams/create.php')) ?>">+ Criar prova</a></div>
</section>
<section class="stats-grid stagger">
    <article class="stat-card"><div class="stat-icon purple">▦</div><div><span>Minhas turmas</span><strong data-count="<?= $classCount ?>"><?= $classCount ?></strong><small>vinculadas</small></div></article>
    <article class="stat-card"><div class="stat-icon blue">◎</div><div><span>Alunos</span><strong data-count="<?= $studentCount ?>"><?= $studentCount ?></strong><small>nas suas turmas</small></div></article>
    <article class="stat-card"><div class="stat-icon green">✓</div><div><span>Provas</span><strong data-count="<?= $examCount ?>"><?= $examCount ?></strong><small>criadas</small></div></article>
    <article class="stat-card"><div class="stat-icon amber">◫</div><div><span>Correções</span><strong data-count="<?= $resultCount ?>"><?= $resultCount ?></strong><small>realizadas</small></div></article>
</section>
<div class="dashboard-grid">
<section class="panel reveal"><div class="panel-head"><div><span class="eyebrow">AVALIAÇÕES</span><h3>Provas recentes</h3></div><a class="text-link" href="<?= e(url('exams/index.php')) ?>">Ver provas →</a></div>
<?php if (!$recent): ?><div class="empty-state"><div class="empty-icon">＋</div><h4>Crie sua primeira prova</h4><p>Escolha uma turma, defina o gabarito oficial e gere as folhas personalizadas.</p><a class="btn btn-primary" href="<?= e(url('exams/create.php')) ?>">Criar prova</a></div>
<?php else: ?><div class="activity-list"><?php foreach($recent as $exam): $total=max(1,(int)$exam['total_students']);$pct=(int)round((int)$exam['corrected']/$total*100); ?>
<a class="activity-item" href="<?= e(url('exams/view.php?id='.$exam['id'])) ?>"><div class="activity-icon">✓</div><div class="activity-copy"><strong><?= e($exam['title']) ?></strong><span><?= e($exam['class_name']) ?> • <?= e($exam['subject_name']) ?></span><div class="mini-progress"><i style="width:<?= min(100,$pct) ?>%"></i></div></div><div class="activity-meta"><strong><?= (int)$exam['corrected'] ?>/<?= (int)$exam['total_students'] ?></strong><span>corrigidos</span></div></a>
<?php endforeach; ?></div><?php endif; ?></section>
<aside class="panel quick-panel reveal delay-1"><div class="panel-head"><div><span class="eyebrow">TURMAS</span><h3>Acesso rápido</h3></div></div>
<?php if(!$classes): ?><div class="empty-state small"><p>Você ainda não foi vinculado a nenhuma turma.</p></div><?php else: foreach($classes as $class): ?><a class="quick-action" href="<?= e(url('classes/view.php?id='.$class['id'])) ?>"><span class="quick-icon">▦</span><div><strong><?= e($class['name']) ?></strong><small><?= (int)$class['students'] ?> alunos • <?= (int)$class['school_year'] ?></small></div><b>→</b></a><?php endforeach; endif; ?>
</aside></div>
<?php require APP_ROOT . '/partials/footer.php'; ?>

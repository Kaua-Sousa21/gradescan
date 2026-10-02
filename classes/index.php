<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';
$user = require_auth();
$pdo = db();

if (request_is_post()) {
    require_role('admin');
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $grade = trim((string)($_POST['grade_level'] ?? ''));
    $year = (int)($_POST['school_year'] ?? date('Y'));
    if ($name === '' || $year < 2020 || $year > 2100) {
        flash('danger', 'Informe nome e ano letivo válidos.');
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO classes (name,grade_level,school_year,active) VALUES (?,?,?,1)');
            $stmt->execute([$name,$grade ?: null,$year]);
            $newClassId=(int)$pdo->lastInsertId();
            audit_log('class.created','class',$newClassId,'Turma criada pela administração.',['name'=>$name,'grade_level'=>$grade,'school_year'=>$year]);
            flash('success', 'Turma criada com sucesso.');
        } catch (PDOException $e) {
            flash('danger', $e->getCode()==='23000' ? 'Já existe uma turma com esse nome neste ano letivo.' : 'Não foi possível criar a turma.');
        }
    }
    redirect('classes/index.php');
}

if ($user['role'] === 'admin') {
    $classes = $pdo->query("SELECT c.*,
        (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=c.id) students,
        (SELECT COUNT(DISTINCT teacher_id) FROM class_teachers ct WHERE ct.class_id=c.id) teachers
        FROM classes c ORDER BY c.school_year DESC,c.name")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT DISTINCT c.*,
        (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=c.id) students,
        (SELECT GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') FROM class_teachers ct2 JOIN subjects s ON s.id=ct2.subject_id WHERE ct2.class_id=c.id AND ct2.teacher_id=?) subjects
        FROM classes c JOIN class_teachers ct ON ct.class_id=c.id
        WHERE ct.teacher_id=? AND c.active=1 ORDER BY c.school_year DESC,c.name");
    $stmt->execute([$user['id'],$user['id']]);
    $classes = $stmt->fetchAll();
}

$pageTitle = $user['role']==='admin' ? 'Turmas' : 'Minhas turmas';
require APP_ROOT . '/partials/header.php';
?>
<div class="page-toolbar reveal"><div><span class="eyebrow">ORGANIZAÇÃO</span><h2><?= $user['role']==='admin'?'Turmas da escola':'Turmas em que você dá aula' ?></h2><p><?= $user['role']==='admin'?'Vincule alunos, professores e disciplinas.':'Consulte alunos e crie avaliações para suas turmas.' ?></p></div><?php if($user['role']==='teacher'): ?><a class="btn btn-primary" href="<?= e(url('exams/create.php')) ?>">+ Criar prova</a><?php endif; ?></div>

<?php if($user['role']==='admin'): ?>
<section class="panel compact-panel reveal"><form method="post" class="inline-create-form"><?= csrf_field() ?><div><span class="eyebrow">NOVA TURMA</span><strong>Crie uma turma</strong></div><label class="field"><span>Nome</span><input name="name" placeholder="9º Ano A" required></label><label class="field"><span>Série/etapa</span><input name="grade_level" placeholder="9º ano"></label><label class="field small-field"><span>Ano</span><input type="number" name="school_year" value="<?= date('Y') ?>" min="2020" max="2100" required></label><button class="btn btn-primary" type="submit">Criar turma</button></form></section>
<?php endif; ?>

<section class="class-grid stagger">
<?php if(!$classes): ?><div class="panel empty-state wide"><div class="empty-icon">▦</div><h4>Nenhuma turma disponível</h4><p><?= $user['role']==='admin'?'Crie a primeira turma usando o formulário acima.':'Peça ao administrador para vincular seu usuário a uma turma.' ?></p></div><?php endif; ?>
<?php foreach($classes as $class): ?>
<article class="class-card">
    <div class="class-card-top"><div class="class-symbol">▦</div><span class="badge badge-info"><?= (int)$class['school_year'] ?></span></div>
    <div class="class-card-copy"><h3><?= e($class['name']) ?></h3><p><?= e($class['grade_level'] ?: 'Turma escolar') ?></p></div>
    <div class="class-metrics"><div><strong><?= (int)$class['students'] ?></strong><span>alunos</span></div><?php if($user['role']==='admin'): ?><div><strong><?= (int)$class['teachers'] ?></strong><span>professores</span></div><?php else: ?><div class="subject-list"><span><?= e($class['subjects'] ?: 'Sem disciplina') ?></span></div><?php endif; ?></div>
    <div class="class-card-actions"><a class="btn btn-soft" href="<?= e(url('classes/view.php?id='.$class['id'])) ?>">Abrir turma</a><?php if($user['role']==='admin'): ?><a class="icon-action larger" href="<?= e(url('classes/manage.php?id='.$class['id'])) ?>" title="Gerenciar">⚙</a><?php endif; ?></div>
</article>
<?php endforeach; ?>
</section>
<?php require APP_ROOT . '/partials/footer.php'; ?>

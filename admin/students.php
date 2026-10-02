<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';
$user = require_role('admin');
$pdo = db();

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = null;
if ($editId) { $stmt=$pdo->prepare('SELECT id,name,enrollment,active FROM students WHERE id=?');$stmt->execute([$editId]);$editing=$stmt->fetch()?:null; }

if (request_is_post()) {
    verify_csrf();
    $action=(string)($_POST['action']??'save');
    if($action==='toggle'){
        $studentId=post_int('id');
        $before=$pdo->prepare('SELECT id,name,enrollment,active FROM students WHERE id=?');$before->execute([$studentId]);$studentBefore=$before->fetch();
        $stmt=$pdo->prepare('UPDATE students SET active=IF(active=1,0,1) WHERE id=?');$stmt->execute([$studentId]);
        if($studentBefore){audit_log('student.status_changed','student',$studentId,'Status do aluno atualizado.',['name'=>$studentBefore['name'],'from'=>(int)$studentBefore['active'],'to'=>(int)$studentBefore['active']===1?0:1]);}
        flash('success','Status do aluno atualizado.');redirect('admin/students.php');
    }
    $id=post_int('id');$name=trim((string)($_POST['name']??''));$enrollment=trim((string)($_POST['enrollment']??''));
    if($name===''||$enrollment===''){flash('danger','Informe o nome e a matrícula do aluno.');redirect('admin/students.php'.($id?'?edit='.$id:''));}
    try{
        if($id){$old=$pdo->prepare('SELECT name,enrollment FROM students WHERE id=?');$old->execute([$id]);$before=$old->fetch();$stmt=$pdo->prepare('UPDATE students SET name=?,enrollment=? WHERE id=?');$stmt->execute([$name,$enrollment,$id]);audit_log('student.updated','student',$id,'Cadastro do aluno atualizado.',['before'=>$before,'after'=>['name'=>$name,'enrollment'=>$enrollment]]);flash('success','Aluno atualizado.');}
        else{$stmt=$pdo->prepare('INSERT INTO students (name,enrollment,active) VALUES (?,?,1)');$stmt->execute([$name,$enrollment]);$newId=(int)$pdo->lastInsertId();audit_log('student.created','student',$newId,'Aluno cadastrado pela administração.',['name'=>$name,'enrollment'=>$enrollment]);flash('success','Aluno cadastrado.');}
    }catch(PDOException $e){flash('danger',$e->getCode()==='23000'?'Essa matrícula já está cadastrada.':'Não foi possível salvar o aluno.');}
    redirect('admin/students.php');
}

$search=trim((string)($_GET['q']??''));$params=[];$sql="SELECT s.id,s.name,s.enrollment,s.active,s.created_at,COUNT(cs.class_id) class_count FROM students s LEFT JOIN class_students cs ON cs.student_id=s.id";
if($search!==''){$sql.=' WHERE s.name LIKE ? OR s.enrollment LIKE ?';$params=["%{$search}%","%{$search}%"];}
$sql.=' GROUP BY s.id ORDER BY s.active DESC,s.name';$stmt=$pdo->prepare($sql);$stmt->execute($params);$students=$stmt->fetchAll();
$pageTitle='Alunos';require APP_ROOT.'/partials/header.php';
?>
<div class="page-toolbar reveal"><div><span class="eyebrow">CADASTRO</span><h2>Alunos</h2><p>Mantenha matrícula e dados dos estudantes organizados.</p></div><a class="btn btn-primary" href="<?= e(url('admin/students.php#student-form')) ?>">+ Novo aluno</a></div>
<div class="split-layout"><section class="panel reveal"><div class="panel-head wrap"><form class="search-bar" method="get"><span>⌕</span><input name="q" value="<?= e($search) ?>" placeholder="Buscar nome ou matrícula"><button>Buscar</button></form><span class="count-pill"><?= count($students) ?> alunos</span></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Aluno</th><th>Matrícula</th><th>Turmas</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($students as $row): ?><tr><td data-label="Aluno"><div class="person-cell"><div class="avatar small soft"><?= e(mb_strtoupper(mb_substr($row['name'],0,1))) ?></div><strong><?= e($row['name']) ?></strong></div></td><td data-label="Matrícula"><code class="code-pill"><?= e($row['enrollment']) ?></code></td><td data-label="Turmas"><?= (int)$row['class_count'] ?></td><td data-label="Status"><span class="status-dot <?= (int)$row['active']?'on':'off' ?>"></span><?= (int)$row['active']?'Ativo':'Inativo' ?></td><td class="table-actions"><a class="icon-action" href="<?= e(url('admin/students.php?edit='.$row['id'].'#student-form')) ?>">✎</a><form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="icon-action" data-confirm="Alterar o status deste aluno?">◌</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<aside class="panel form-panel reveal delay-1" id="student-form"><div class="panel-head"><div><span class="eyebrow"><?= $editing?'EDIÇÃO':'NOVO ALUNO' ?></span><h3><?= $editing?'Editar aluno':'Cadastrar aluno' ?></h3></div><?php if($editing): ?><a class="text-link" href="<?= e(url('admin/students.php#student-form')) ?>">Cancelar</a><?php endif; ?></div><form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id']??0) ?>"><label class="field"><span>Nome completo</span><input name="name" value="<?= e($editing['name']??'') ?>" required></label><label class="field"><span>Matrícula</span><input name="enrollment" value="<?= e($editing['enrollment']??'') ?>" placeholder="20260001" required><small>Ela também aparece impressa no gabarito do aluno.</small></label><button class="btn btn-primary btn-block" type="submit"><?= $editing?'Salvar alterações':'Cadastrar aluno' ?> <span>→</span></button></form></aside></div>
<?php require APP_ROOT.'/partials/footer.php'; ?>

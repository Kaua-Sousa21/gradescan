<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';
$user = require_role('admin');
$pdo = db();
$classId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM classes WHERE id=?');$stmt->execute([$classId]);$class=$stmt->fetch();
if(!$class){http_response_code(404);exit('Turma não encontrada.');}

if(request_is_post()){
    verify_csrf();$action=(string)($_POST['action']??'');
    try{
        if($action==='add_student'){
            $studentId=post_int('student_id');
            $stmt=$pdo->prepare('INSERT IGNORE INTO class_students (class_id,student_id) VALUES (?,?)');$stmt->execute([$classId,$studentId]);audit_log('class.student_added','class',$classId,'Aluno adicionado à turma.',['student_id'=>$studentId]);flash('success','Aluno adicionado à turma.');
        }elseif($action==='remove_student'){
            $studentId=post_int('student_id');$stmt=$pdo->prepare('DELETE FROM class_students WHERE class_id=? AND student_id=?');$stmt->execute([$classId,$studentId]);audit_log('class.student_removed','class',$classId,'Aluno removido da turma.',['student_id'=>$studentId]);flash('success','Aluno removido da turma.');
        }elseif($action==='add_teacher'){
            $teacherId=post_int('teacher_id');$subjectId=post_int('subject_id');
            $stmt=$pdo->prepare('INSERT IGNORE INTO class_teachers (class_id,teacher_id,subject_id) VALUES (?,?,?)');$stmt->execute([$classId,$teacherId,$subjectId]);audit_log('class.teacher_added','class',$classId,'Professor vinculado à turma.',['teacher_id'=>$teacherId,'subject_id'=>$subjectId]);flash('success','Professor vinculado à turma.');
        }elseif($action==='remove_teacher'){
            $teacherId=post_int('teacher_id');$subjectId=post_int('subject_id');$stmt=$pdo->prepare('DELETE FROM class_teachers WHERE class_id=? AND teacher_id=? AND subject_id=?');$stmt->execute([$classId,$teacherId,$subjectId]);audit_log('class.teacher_removed','class',$classId,'Vínculo de professor removido.',['teacher_id'=>$teacherId,'subject_id'=>$subjectId]);flash('success','Vínculo removido.');
        }elseif($action==='update_class'){
            $name=trim((string)($_POST['name']??''));$grade=trim((string)($_POST['grade_level']??''));$year=(int)($_POST['school_year']??date('Y'));
            $beforeClass=$class;$stmt=$pdo->prepare('UPDATE classes SET name=?,grade_level=?,school_year=? WHERE id=?');$stmt->execute([$name,$grade?:null,$year,$classId]);audit_log('class.updated','class',$classId,'Dados da turma atualizados.',['before'=>['name'=>$beforeClass['name'],'grade_level'=>$beforeClass['grade_level'],'school_year'=>$beforeClass['school_year']],'after'=>['name'=>$name,'grade_level'=>$grade,'school_year'=>$year]]);flash('success','Dados da turma atualizados.');
        }
    }catch(Throwable $e){flash('danger','Não foi possível concluir a ação.');}
    redirect('classes/manage.php?id='.$classId);
}

$stmt=$pdo->prepare("SELECT s.id,s.name,s.enrollment FROM students s JOIN class_students cs ON cs.student_id=s.id WHERE cs.class_id=? ORDER BY s.name");$stmt->execute([$classId]);$students=$stmt->fetchAll();
$stmt=$pdo->prepare("SELECT u.id teacher_id,u.name teacher_name,s.id subject_id,s.name subject_name FROM class_teachers ct JOIN users u ON u.id=ct.teacher_id JOIN subjects s ON s.id=ct.subject_id WHERE ct.class_id=? ORDER BY u.name,s.name");$stmt->execute([$classId]);$teachers=$stmt->fetchAll();
$availableStudents=$pdo->prepare("SELECT s.id,s.name,s.enrollment FROM students s WHERE s.active=1 AND NOT EXISTS(SELECT 1 FROM class_students cs WHERE cs.class_id=? AND cs.student_id=s.id) ORDER BY s.name");$availableStudents->execute([$classId]);$availableStudents=$availableStudents->fetchAll();
$allTeachers=$pdo->query("SELECT id,name FROM users WHERE role='teacher' AND active=1 ORDER BY name")->fetchAll();
$subjects=$pdo->query("SELECT id,name FROM subjects WHERE active=1 ORDER BY name")->fetchAll();
$pageTitle='Gerenciar turma';require APP_ROOT.'/partials/header.php';
?>
<div class="page-toolbar reveal"><div><a class="back-link" href="<?= e(url('classes/index.php')) ?>">← Turmas</a><span class="eyebrow">CONFIGURAÇÃO DA TURMA</span><h2><?= e($class['name']) ?></h2><p>Organize alunos, professores e disciplinas.</p></div><a class="btn btn-soft" href="<?= e(url('classes/view.php?id='.$classId)) ?>">Visualizar turma</a></div>
<div class="dashboard-grid manage-grid">
<section class="panel reveal"><div class="panel-head"><div><span class="eyebrow">ALUNOS</span><h3><?= count($students) ?> matriculados</h3></div></div>
<form method="post" class="inline-add"><?= csrf_field() ?><input type="hidden" name="action" value="add_student"><select name="student_id" required><option value="">Selecione um aluno</option><?php foreach($availableStudents as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name'].' • '.$s['enrollment']) ?></option><?php endforeach; ?></select><button class="btn btn-primary">Adicionar</button></form>
<div class="simple-list"><?php if(!$students): ?><div class="empty-row">Nenhum aluno nesta turma.</div><?php endif; ?><?php foreach($students as $s): ?><div class="simple-list-item"><div class="person-cell"><div class="avatar small soft"><?= e(mb_strtoupper(mb_substr($s['name'],0,1))) ?></div><div><strong><?= e($s['name']) ?></strong><span>Matrícula <?= e($s['enrollment']) ?></span></div></div><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove_student"><input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>"><button class="icon-action danger" data-confirm="Remover este aluno da turma?">×</button></form></div><?php endforeach; ?></div>
</section>
<aside class="panel reveal delay-1"><div class="panel-head"><div><span class="eyebrow">PROFESSORES</span><h3>Equipe da turma</h3></div></div>
<form method="post" class="form-stack compact"><?= csrf_field() ?><input type="hidden" name="action" value="add_teacher"><label class="field"><span>Professor</span><select name="teacher_id" required><option value="">Selecione</option><?php foreach($allTeachers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label><label class="field"><span>Disciplina</span><select name="subject_id" required><option value="">Selecione</option><?php foreach($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label><button class="btn btn-primary btn-block">Vincular professor</button></form>
<div class="simple-list compact-list"><?php if(!$teachers): ?><div class="empty-row">Nenhum professor vinculado.</div><?php endif; ?><?php foreach($teachers as $t): ?><div class="simple-list-item"><div><strong><?= e($t['teacher_name']) ?></strong><span><?= e($t['subject_name']) ?></span></div><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove_teacher"><input type="hidden" name="teacher_id" value="<?= (int)$t['teacher_id'] ?>"><input type="hidden" name="subject_id" value="<?= (int)$t['subject_id'] ?>"><button class="icon-action danger" data-confirm="Remover este vínculo?">×</button></form></div><?php endforeach; ?></div>
</aside>
</div>
<section class="panel reveal"><div class="panel-head"><div><span class="eyebrow">DADOS</span><h3>Informações da turma</h3></div></div><form method="post" class="inline-create-form settings-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_class"><label class="field"><span>Nome</span><input name="name" value="<?= e($class['name']) ?>" required></label><label class="field"><span>Série/etapa</span><input name="grade_level" value="<?= e($class['grade_level']) ?>"></label><label class="field small-field"><span>Ano</span><input type="number" name="school_year" value="<?= (int)$class['school_year'] ?>" required></label><button class="btn btn-soft">Salvar dados</button></form></section>
<?php require APP_ROOT.'/partials/footer.php'; ?>

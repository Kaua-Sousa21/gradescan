<?php
declare(strict_types=1);
require_once __DIR__.'/../config/app.php';
require_once APP_ROOT.'/core/auth.php';
$user=require_role('teacher','admin');
$pdo=db();
$isAdmin=$user['role']==='admin';
$prefillClass=(int)($_GET['class']??0);

if($isAdmin){
    $assignments=$pdo->query("SELECT ct.teacher_id,u.name teacher_name,c.id class_id,c.name class_name,c.school_year,s.id subject_id,s.name subject_name
        FROM class_teachers ct
        JOIN users u ON u.id=ct.teacher_id AND u.role='teacher' AND u.active=1
        JOIN classes c ON c.id=ct.class_id AND c.active=1
        JOIN subjects s ON s.id=ct.subject_id AND s.active=1
        ORDER BY u.name,c.name,s.name")->fetchAll();
    $teachers=[];$classes=[];
    foreach($assignments as $a){
        $teachers[(int)$a['teacher_id']]=['id'=>(int)$a['teacher_id'],'name'=>$a['teacher_name']];
        $classes[(int)$a['teacher_id'].':'.(int)$a['class_id']]=['id'=>(int)$a['class_id'],'name'=>$a['class_name'],'school_year'=>(int)$a['school_year'],'teacher_id'=>(int)$a['teacher_id']];
    }
    $teachers=array_values($teachers);$classes=array_values($classes);
    $prefillTeacher=0;
    if($prefillClass){foreach($assignments as $a){if((int)$a['class_id']===$prefillClass){$prefillTeacher=(int)$a['teacher_id'];break;}}}
}else{
    $stmt=$pdo->prepare("SELECT DISTINCT c.id,c.name,c.school_year FROM classes c JOIN class_teachers ct ON ct.class_id=c.id WHERE ct.teacher_id=? AND c.active=1 ORDER BY c.name");
    $stmt->execute([$user['id']]);$classes=$stmt->fetchAll();
    $stmt=$pdo->prepare("SELECT DISTINCT s.id subject_id,s.name subject_name,ct.class_id FROM class_teachers ct JOIN subjects s ON s.id=ct.subject_id WHERE ct.teacher_id=? AND s.active=1 ORDER BY s.name");
    $stmt->execute([$user['id']]);$assignments=$stmt->fetchAll();
    $teachers=[];$prefillTeacher=(int)$user['id'];
}

if(request_is_post()){
    verify_csrf();
    $title=trim((string)($_POST['title']??''));
    $classId=post_int('class_id');
    $subjectId=post_int('subject_id');
    $teacherId=$isAdmin?post_int('teacher_id'):(int)$user['id'];
    $count=(int)($_POST['questions_count']??20);
    $points=(float)str_replace(',','.',(string)($_POST['total_points']??'10'));
    $version=strtoupper(substr(trim((string)($_POST['version']??'A')),0,1));

    $allowedStmt=$pdo->prepare('SELECT 1 FROM class_teachers WHERE teacher_id=? AND class_id=? AND subject_id=? LIMIT 1');
    $allowedStmt->execute([$teacherId,$classId,$subjectId]);
    if($title===''||!$allowedStmt->fetchColumn()||$count<5||$count>40||$points<=0||$points>100){
        flash('danger','Revise os dados da prova. São permitidas de 5 a 40 questões e até 100 pontos.');
        redirect('exams/create.php');
    }

    $answers=[];
    for($i=1;$i<=$count;$i++){
        $v=strtoupper((string)($_POST['answer_'.$i]??''));
        if(!in_array($v,['A','B','C','D','E'],true)){
            flash('danger','Marque a alternativa correta de todas as questões.');
            redirect('exams/create.php');
        }
        $answers[$i]=$v;
    }

    try{
        $pdo->beginTransaction();
        $stmt=$pdo->prepare("INSERT INTO exams (teacher_id,class_id,subject_id,title,questions_count,total_points,version,status) VALUES (?,?,?,?,?,?,?,'published')");
        $stmt->execute([$teacherId,$classId,$subjectId,$title,$count,$points,$version?:'A']);
        $examId=(int)$pdo->lastInsertId();
        $per=$points/$count;
        $q=$pdo->prepare('INSERT INTO exam_questions (exam_id,question_number,correct_option,points) VALUES (?,?,?,?)');
        foreach($answers as $n=>$a){$q->execute([$examId,$n,$a,$per]);}
        $pdo->commit();
        audit_log('exam.created','exam',$examId,$isAdmin?'Prova criada pela administração para um professor.':'Prova criada pelo professor.',[
            'title'=>$title,'teacher_id'=>$teacherId,'class_id'=>$classId,'subject_id'=>$subjectId,'questions_count'=>$count,'total_points'=>$points,'version'=>$version?:'A'
        ]);
        flash('success','Prova criada. Agora você já pode imprimir os gabaritos personalizados.');
        redirect('exams/view.php?id='.$examId);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        error_log('[Gabarito Online create exam] '.$e->getMessage());
        flash('danger','Não foi possível criar a prova.');
        redirect('exams/create.php');
    }
}

$pageTitle='Criar prova';
require APP_ROOT.'/partials/header.php';
?>
<div class="page-toolbar reveal"><div><a class="back-link" href="<?= e(url('exams/index.php')) ?>">← Provas</a><span class="eyebrow">NOVA AVALIAÇÃO</span><h2>Crie a prova e o gabarito oficial</h2><p><?= $isAdmin?'Escolha o professor responsável, a turma e a disciplina.':'Depois, o sistema gera uma folha personalizada para cada aluno da turma.' ?></p></div></div>
<?php if(!$classes): ?>
<div class="panel empty-state"><div class="empty-icon">▦</div><h4>Nenhum vínculo disponível</h4><p><?= $isAdmin?'Cadastre professores e vincule-os às turmas antes de criar uma prova.':'O administrador precisa vincular seu usuário a uma turma e disciplina antes de criar provas.' ?></p></div>
<?php else: ?>
<form method="post" class="exam-builder" data-exam-builder><?= csrf_field() ?>
<section class="panel reveal"><div class="panel-head"><div><span class="step-number">01</span><h3>Informações da prova</h3></div></div><div class="form-grid three">
    <label class="field span-2"><span>Título da avaliação</span><input name="title" placeholder="Avaliação Bimestral" required></label>
    <label class="field"><span>Versão</span><select name="version"><option>A</option><option>B</option><option>C</option><option>D</option></select></label>
    <?php if($isAdmin): ?>
    <label class="field"><span>Professor responsável</span><select name="teacher_id" id="examTeacher" required><option value="">Selecione</option><?php foreach($teachers as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $prefillTeacher===(int)$t['id']?'selected':'' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label class="field"><span>Turma</span><select name="class_id" id="examClass" required><option value="">Selecione</option><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $isAdmin?'data-teacher="'.(int)$c['teacher_id'].'"':'' ?> <?= $prefillClass===(int)$c['id'] && (!$isAdmin||$prefillTeacher===(int)$c['teacher_id'])?'selected':'' ?>><?= e($c['name'].' • '.$c['school_year']) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Disciplina</span><select name="subject_id" id="examSubject" required><option value="">Selecione a turma primeiro</option><?php foreach($assignments as $a): ?><option value="<?= (int)$a['subject_id'] ?>" data-class="<?= (int)$a['class_id'] ?>" <?= $isAdmin?'data-teacher="'.(int)$a['teacher_id'].'"':'' ?>><?= e($a['subject_name']) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Valor da prova</span><input name="total_points" type="number" min="0.1" max="100" step="0.1" value="10" required></label>
</div></section>
<section class="panel reveal delay-1"><div class="panel-head"><div><span class="step-number">02</span><div><h3>Gabarito oficial</h3><p>Selecione a resposta correta de cada questão.</p></div></div><label class="field questions-field"><span>Questões</span><select name="questions_count" id="questionCount"><option>5</option><option>10</option><option selected>20</option><option>25</option><option>30</option><option>40</option></select></label></div><div class="answer-key-grid" id="answerKey"></div></section>
<div class="builder-footer"><div><strong>Pronto para gerar os gabaritos?</strong><span>Você poderá revisar a prova antes de imprimir.</span></div><button class="btn btn-primary btn-lg" type="submit">Criar prova <span>→</span></button></div>
</form>
<?php endif; ?>
<?php require APP_ROOT.'/partials/footer.php'; ?>

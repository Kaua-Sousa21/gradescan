<?php
declare(strict_types=1);
require_once __DIR__.'/../config/app.php';require_once APP_ROOT.'/core/auth.php';$user=require_auth();$pdo=db();
if($user['role']==='admin'){
    $stmt=$pdo->query("SELECT e.*,c.name class_name,s.name subject_name,u.name teacher_name,
      (SELECT COUNT(*) FROM results r WHERE r.exam_id=e.id) corrected,
      (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=e.class_id) total_students,
      (SELECT ROUND(AVG(r.score),2) FROM results r WHERE r.exam_id=e.id) avg_score
      FROM exams e JOIN classes c ON c.id=e.class_id JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.teacher_id ORDER BY e.created_at DESC");
}else{
    $stmt=$pdo->prepare("SELECT e.*,c.name class_name,s.name subject_name,u.name teacher_name,
      (SELECT COUNT(*) FROM results r WHERE r.exam_id=e.id) corrected,
      (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=e.class_id) total_students,
      (SELECT ROUND(AVG(r.score),2) FROM results r WHERE r.exam_id=e.id) avg_score
      FROM exams e JOIN classes c ON c.id=e.class_id JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.teacher_id WHERE e.teacher_id=? ORDER BY e.created_at DESC");$stmt->execute([$user['id']]);
}
$exams=$stmt->fetchAll();$pageTitle=$user['role']==='admin'?'Provas e resultados':'Minhas provas';require APP_ROOT.'/partials/header.php';
?>
<div class="page-toolbar reveal"><div><span class="eyebrow">AVALIAÇÕES</span><h2><?= $user['role']==='admin'?'Todas as provas':'Provas criadas por você' ?></h2><p>Acompanhe gabaritos, correções e resultados.</p></div><a class="btn btn-primary" href="<?= e(url('exams/create.php')) ?>">+ <?= $user['role']==='admin'?'Criar prova':'Criar nova prova' ?></a></div>
<section class="exam-grid stagger"><?php if(!$exams): ?><div class="panel empty-state wide"><div class="empty-icon">✓</div><h4>Nenhuma prova encontrada</h4><p><?= $user['role']==='teacher'?'Crie uma avaliação e defina o gabarito oficial.':'As provas criadas pelos professores aparecerão aqui.' ?></p><a class="btn btn-primary" href="<?= e(url('exams/create.php')) ?>">Criar prova</a></div><?php endif; ?>
<?php foreach($exams as $exam): $total=(int)$exam['total_students'];$corrected=(int)$exam['corrected'];$pct=$total?round($corrected/$total*100):0; ?>
<article class="exam-card"><div class="exam-card-head"><span class="badge badge-purple"><?= e($exam['subject_name']) ?></span><span class="muted-date"><?= date('d/m/Y',strtotime($exam['created_at'])) ?></span></div><h3><?= e($exam['title']) ?></h3><p><?= e($exam['class_name']) ?><?php if($user['role']==='admin'): ?> • <?= e($exam['teacher_name']) ?><?php endif; ?></p><div class="exam-summary"><div><strong><?= (int)$exam['questions_count'] ?></strong><span>questões</span></div><div><strong><?= format_score((float)$exam['total_points']) ?></strong><span>pontos</span></div><div><strong><?= $exam['avg_score']!==null?format_score((float)$exam['avg_score']):'—' ?></strong><span>média</span></div></div><div class="progress-label"><span>Correções</span><b><?= $corrected ?>/<?= $total ?></b></div><div class="progress-bar"><i style="width:<?= min(100,$pct) ?>%"></i></div><div class="exam-card-actions"><a class="btn btn-soft" href="<?= e(url('exams/view.php?id='.$exam['id'])) ?>">Abrir prova</a><a class="btn btn-primary" href="<?= e(url('exams/scan.php?id='.$exam['id'])) ?>">◫ Escanear</a></div></article>
<?php endforeach; ?></section>
<?php require APP_ROOT.'/partials/footer.php'; ?>

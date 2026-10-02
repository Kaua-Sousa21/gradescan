<?php
declare(strict_types=1);
require_once __DIR__.'/../config/app.php';
require_once APP_ROOT.'/core/auth.php';
$user=require_auth();
$pdo=db();
$examId=(int)($_GET['id']??0);
$stmt=$pdo->prepare("SELECT e.*,c.name class_name,c.school_year,s.name subject_name,u.name teacher_name,
(SELECT COUNT(*) FROM class_students cs WHERE cs.class_id=e.class_id) total_students,
(SELECT COUNT(*) FROM results r WHERE r.exam_id=e.id) corrected,
(SELECT ROUND(AVG(r.score),2) FROM results r WHERE r.exam_id=e.id) avg_score,
(SELECT MAX(r.score) FROM results r WHERE r.exam_id=e.id) max_score,
(SELECT MIN(r.score) FROM results r WHERE r.exam_id=e.id) min_score
FROM exams e JOIN classes c ON c.id=e.class_id JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.teacher_id WHERE e.id=?");
$stmt->execute([$examId]);$exam=$stmt->fetch();
if(!$exam){http_response_code(404);exit('Prova não encontrada.');}
if(!can_access_exam($user,$exam)){http_response_code(403);require APP_ROOT.'/partials/403.php';exit;}
$stmt=$pdo->prepare('SELECT question_number,correct_option,points FROM exam_questions WHERE exam_id=? ORDER BY question_number');$stmt->execute([$examId]);$questions=$stmt->fetchAll();
$stmt=$pdo->prepare("SELECT st.id student_id,st.name,st.enrollment,r.id result_id,r.correct_count,r.incorrect_count,r.blank_count,r.score,r.scanned_at
FROM class_students cs JOIN students st ON st.id=cs.student_id LEFT JOIN results r ON r.student_id=st.id AND r.exam_id=? WHERE cs.class_id=? ORDER BY st.name");
$stmt->execute([$examId,$exam['class_id']]);$students=$stmt->fetchAll();
$pageTitle=$exam['title'];require APP_ROOT.'/partials/header.php';
$total=(int)$exam['total_students'];$corrected=(int)$exam['corrected'];$pct=$total?round($corrected/$total*100):0;
?>
<div class="page-toolbar reveal"><div><a class="back-link" href="<?= e(url('exams/index.php')) ?>">← Provas</a><span class="eyebrow"><?= e($exam['subject_name']) ?> • <?= e($exam['class_name']) ?></span><h2><?= e($exam['title']) ?></h2><p>Professor <?= e($exam['teacher_name']) ?> • <?= (int)$exam['questions_count'] ?> questões • <?= format_score((float)$exam['total_points']) ?> pontos</p></div><div class="hero-actions"><a class="btn btn-soft" target="_blank" href="<?= e(url('exams/print_sheets.php?id='.$examId)) ?>">▤ Imprimir gabaritos</a><a class="btn btn-primary" href="<?= e(url('exams/scan.php?id='.$examId)) ?>">◫ Escanear respostas</a></div></div>
<section class="stats-grid compact-stats stagger"><article class="stat-card"><div class="stat-icon purple">◫</div><div><span>Corrigidos</span><strong><?= $corrected ?>/<?= $total ?></strong><small><?= $pct ?>% da turma</small></div></article><article class="stat-card"><div class="stat-icon blue">≈</div><div><span>Média</span><strong><?= $exam['avg_score']!==null?format_score((float)$exam['avg_score']):'—' ?></strong><small>pontos</small></div></article><article class="stat-card"><div class="stat-icon green">↑</div><div><span>Maior nota</span><strong><?= $exam['max_score']!==null?format_score((float)$exam['max_score']):'—' ?></strong><small>pontos</small></div></article><article class="stat-card"><div class="stat-icon amber">↓</div><div><span>Menor nota</span><strong><?= $exam['min_score']!==null?format_score((float)$exam['min_score']):'—' ?></strong><small>pontos</small></div></article></section>
<div class="dashboard-grid results-grid"><section class="panel reveal"><div class="panel-head wrap"><div><span class="eyebrow">RESULTADOS</span><h3>Desempenho da turma</h3></div><div class="progress-inline"><div class="progress-bar"><i style="width:<?= min(100,$pct) ?>%"></i></div><span><?= $pct ?>%</span></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Aluno</th><th>Acertos</th><th>Erros</th><th>Em branco</th><th>Nota</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($students as $s): ?><tr><td data-label="Aluno"><div class="person-cell"><div class="avatar small soft"><?= e(mb_strtoupper(mb_substr($s['name'],0,1))) ?></div><div><strong><?= e($s['name']) ?></strong><span><?= e($s['enrollment']) ?></span></div></div></td><?php if($s['result_id']): ?><td data-label="Acertos"><strong class="good"><?= (int)$s['correct_count'] ?></strong></td><td data-label="Erros"><strong class="bad"><?= (int)$s['incorrect_count'] ?></strong></td><td data-label="Em branco"><?= (int)$s['blank_count'] ?></td><td data-label="Nota"><span class="score-pill"><?= format_score((float)$s['score']) ?></span></td><td data-label="Status"><span class="badge badge-success">Corrigida</span></td><td><a class="icon-action" href="<?= e(url('exams/result.php?id='.$s['result_id'])) ?>">→</a></td><?php else: ?><td>—</td><td>—</td><td>—</td><td><span class="score-pill muted">—</span></td><td><span class="badge badge-warning">Pendente</span></td><td><a class="icon-action" href="<?= e(url('exams/scan.php?id='.$examId.'&student='.$s['student_id'])) ?>">◫</a></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section>
<aside class="panel reveal delay-1"><div class="panel-head"><div><span class="eyebrow">GABARITO OFICIAL</span><h3>Respostas corretas</h3></div><span class="badge badge-purple">Versão <?= e($exam['version']) ?></span></div><div class="key-preview"><?php foreach($questions as $q): ?><div><span><?= (int)$q['question_number'] ?></span><strong><?= e($q['correct_option']) ?></strong></div><?php endforeach; ?></div><div class="info-box"><strong>Como corrigir</strong><p>Abra o scanner pelo celular, fotografe a folha inteira e mantenha os quatro marcadores pretos visíveis.</p></div></aside></div>
<?php require APP_ROOT.'/partials/footer.php'; ?>

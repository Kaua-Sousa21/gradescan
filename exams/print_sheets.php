<?php
declare(strict_types=1);
require_once __DIR__.'/../config/app.php';
require_once APP_ROOT.'/core/auth.php';
$user=require_auth();$pdo=db();$examId=(int)($_GET['id']??0);
$stmt=$pdo->prepare("SELECT e.*,c.name class_name,s.name subject_name,u.name teacher_name FROM exams e JOIN classes c ON c.id=e.class_id JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.teacher_id WHERE e.id=?");$stmt->execute([$examId]);$exam=$stmt->fetch();if(!$exam){exit('Prova não encontrada.');}if(!can_access_exam($user,$exam)){http_response_code(403);exit('Sem permissão.');}
$stmt=$pdo->prepare("SELECT st.id,st.name,st.enrollment FROM class_students cs JOIN students st ON st.id=cs.student_id WHERE cs.class_id=? AND st.active=1 ORDER BY st.name");$stmt->execute([$exam['class_id']]);$students=$stmt->fetchAll();
$tokenGet=$pdo->prepare('SELECT token FROM exam_student_tokens WHERE exam_id=? AND student_id=?');$tokenCreate=$pdo->prepare('INSERT INTO exam_student_tokens (exam_id,student_id,token) VALUES (?,?,?)');
foreach($students as &$st){$tokenGet->execute([$examId,$st['id']]);$token=$tokenGet->fetchColumn();if(!$token){$token=random_token(24);try{$tokenCreate->execute([$examId,$st['id'],$token]);}catch(PDOException $e){$tokenGet->execute([$examId,$st['id']]);$token=$tokenGet->fetchColumn();}}$st['token']=$token;}unset($st);

$scalePct=(int)($_GET['scale']??100); if($scalePct<55){$scalePct=55;} if($scalePct>100){$scalePct=100;} $scale=$scalePct/100;
$templateW=178.0; $templateH=232.0; $markerSize=6.0; $markerMargin=4.0;
$actualW=$templateW*$scale; $actualH=$templateH*$scale; $blockLeft=(210-$actualW)/2; $blockTop=(297-$actualH)/2;
$qcount=(int)$exam['questions_count']; $rows=(int)ceil($qcount/2);
$verticalSpan=110.0; $spacing=min(9.2,$verticalSpan/max(1,$rows-1));
if($rows>16){$spacing=min($spacing,6.6);} if($rows>20){$spacing=min($spacing,5.1);} 
$bubbleSize=$rows>18?4.1:($rows>12?4.8:5.5); $answerStartY=78.0;
function s(float $mm,float $scale): string { return number_format($mm*$scale,2,'.',''); }
function page_mm(float $mm,float $scale,float $offset): string { return number_format($offset+($mm*$scale),2,'.',''); }
function row_pos(float $q,int $rows,float $spacing,float $answerStartY): array { $right=$q>$rows;$idx=$right?$q-$rows-1:$q-1;return [$right,$answerStartY+($idx*$spacing)]; }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gabaritos • <?= e($exam['title']) ?></title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= e(url('assets/css/print-sheet.css?v=40')) ?>"></head><body>
<div class="print-toolbar"><div><strong><?= e($exam['title']) ?></strong><span><?= count($students) ?> gabaritos • bloco escalável • tamanho atual <?= $scalePct ?>%</span></div><div class="toolbar-scales"><a href="<?= e(url('exams/print_sheets.php?id='.$examId.'&scale=100')) ?>">100%</a><a href="<?= e(url('exams/print_sheets.php?id='.$examId.'&scale=85')) ?>">85%</a><a href="<?= e(url('exams/print_sheets.php?id='.$examId.'&scale=70')) ?>">70%</a></div><button type="button" data-print-all>Imprimir todos</button><a href="<?= e(url('exams/view.php?id='.$examId)) ?>">Voltar</a></div>
<?php foreach($students as $st): $payload='GO4:'.$examId.':'.$st['id'].':'.substr((string)$st['token'],0,10); ?>
<section class="sheet" style="--sheet-bubble-size:<?= s($bubbleSize,$scale) ?>mm">
  <div class="omr-block" style="left:<?= number_format($blockLeft,2,'.','') ?>mm;top:<?= number_format($blockTop,2,'.','') ?>mm;width:<?= number_format($actualW,2,'.','') ?>mm;height:<?= number_format($actualH,2,'.','') ?>mm">
    <svg class="fiducial tl" viewBox="0 0 100 100" style="width:<?= s($markerSize,$scale) ?>mm;height:<?= s($markerSize,$scale) ?>mm;left:<?= s($markerMargin,$scale) ?>mm;top:<?= s($markerMargin,$scale) ?>mm" aria-hidden="true"><rect x="0" y="0" width="100" height="100" fill="#000"/></svg>
    <svg class="fiducial tr" viewBox="0 0 100 100" style="width:<?= s($markerSize,$scale) ?>mm;height:<?= s($markerSize,$scale) ?>mm;right:<?= s($markerMargin,$scale) ?>mm;top:<?= s($markerMargin,$scale) ?>mm" aria-hidden="true"><rect x="0" y="0" width="100" height="100" fill="#000"/></svg>
    <svg class="fiducial ml" viewBox="0 0 100 100" style="width:<?= s($markerSize,$scale) ?>mm;height:<?= s($markerSize,$scale) ?>mm;left:<?= s($markerMargin,$scale) ?>mm;top:50%;transform:translateY(-50%)" aria-hidden="true"><rect x="0" y="0" width="100" height="100" fill="#000"/></svg>
    <svg class="fiducial mr" viewBox="0 0 100 100" style="width:<?= s($markerSize,$scale) ?>mm;height:<?= s($markerSize,$scale) ?>mm;right:<?= s($markerMargin,$scale) ?>mm;top:50%;transform:translateY(-50%)" aria-hidden="true"><rect x="0" y="0" width="100" height="100" fill="#000"/></svg>
    <svg class="fiducial bl" viewBox="0 0 100 100" style="width:<?= s($markerSize,$scale) ?>mm;height:<?= s($markerSize,$scale) ?>mm;left:<?= s($markerMargin,$scale) ?>mm;bottom:<?= s($markerMargin,$scale) ?>mm" aria-hidden="true"><rect x="0" y="0" width="100" height="100" fill="#000"/></svg>
    <svg class="fiducial br" viewBox="0 0 100 100" style="width:<?= s($markerSize,$scale) ?>mm;height:<?= s($markerSize,$scale) ?>mm;right:<?= s($markerMargin,$scale) ?>mm;bottom:<?= s($markerMargin,$scale) ?>mm" aria-hidden="true"><rect x="0" y="0" width="100" height="100" fill="#000"/></svg>

    <div class="block-header" style="left:<?= s(16,$scale) ?>mm;right:<?= s(16,$scale) ?>mm;top:<?= s(10,$scale) ?>mm">
      <div class="school-name"><?= e((string)config('school_name')) ?></div>
      <div class="sheet-title">GABARITO DE RESPOSTAS</div>
      <div class="sheet-meta"><span><?= e($exam['subject_name']) ?></span><span><?= e($exam['title']) ?></span><span>Versão <?= e($exam['version']) ?></span></div>
    </div>

    <div class="student-box" style="left:<?= s(16,$scale) ?>mm;right:<?= s(16,$scale) ?>mm;top:<?= s(30,$scale) ?>mm;height:<?= s(34,$scale) ?>mm">
      <div class="student-name" style="height:<?= s(12,$scale) ?>mm;padding:0 <?= s(5,$scale) ?>mm"><span>Aluno</span><strong><?= e($st['name']) ?></strong></div>
      <div class="student-meta" style="height:<?= s(22,$scale) ?>mm;grid-template-columns:1fr 1fr <?= s(24,$scale) ?>mm">
        <div style="padding:<?= s(2,$scale) ?>mm <?= s(4,$scale) ?>mm"><span>Matrícula</span><strong><?= e($st['enrollment']) ?></strong></div>
        <div style="padding:<?= s(2,$scale) ?>mm <?= s(4,$scale) ?>mm"><span>Turma</span><strong><?= e($exam['class_name']) ?></strong></div>
        <div class="qr-cell" style="padding:<?= s(1.2,$scale) ?>mm"><div class="qr-code" data-qr="<?= e($payload) ?>" style="width:<?= s(21,$scale) ?>mm;height:<?= s(21,$scale) ?>mm"></div></div>
      </div>
    </div>

    <div class="answer-head left" style="left:<?= s(35,$scale) ?>mm;top:<?= s(70,$scale) ?>mm"><b>A</b><b>B</b><b>C</b><b>D</b><b>E</b></div>
    <div class="answer-head right" style="left:<?= s(110,$scale) ?>mm;top:<?= s(70,$scale) ?>mm"><b>A</b><b>B</b><b>C</b><b>D</b><b>E</b></div>

    <?php for($q=1;$q<=$qcount;$q++): [$right,$top]=row_pos($q,$rows,$spacing,$answerStartY);$base=$right?110:35;$qLeft=$right?101:26; ?>
    <div class="answer-row <?= $right?'right':'left' ?>" style="top:<?= s($top,$scale) ?>mm;height:<?= s($bubbleSize,$scale) ?>mm"><span class="qnum" style="left:<?= s($qLeft,$scale) ?>mm;width:<?= s(8,$scale) ?>mm;height:<?= s($bubbleSize,$scale) ?>mm"><?= $q ?></span><?php foreach(['A','B','C','D','E'] as $i=>$letter): ?><span class="bubble" data-q="<?= $q ?>" data-o="<?= $letter ?>" style="left:<?= s($base+($i*10),$scale) ?>mm;top:0;width:<?= s($bubbleSize,$scale) ?>mm;height:<?= s($bubbleSize,$scale) ?>mm"></span><?php endforeach; ?></div>
    <?php endfor; ?>

    <div class="sheet-footer" style="left:<?= s(16,$scale) ?>mm;right:<?= s(16,$scale) ?>mm;bottom:<?= s(11,$scale) ?>mm;padding-top:<?= s(2.8,$scale) ?>mm">
      <div><strong>Bloco escalável</strong><span>Você pode reduzir para encaixar na prova, sem alterar a proporção e sem cortar os 6 marcadores.</span></div>
      <div class="footer-brand"><b>Gabarito Online</b><span>Leitura por câmera • Formulário v4.0</span></div>
    </div>
  </div>
</section>
<?php endforeach; ?>
<script src="<?= e(url('assets/js/vendor-loader.js')) ?>"></script><script nonce="<?= e(csp_nonce()) ?>">document.querySelector('[data-print-all]')?.addEventListener('click',()=>window.print());(async()=>{try{await window.GradeScanVendors.ensureQRCode();document.querySelectorAll('[data-qr]').forEach(el=>new QRCode(el,{text:el.dataset.qr,width:132,height:132,correctLevel:QRCode.CorrectLevel.M}));}catch(e){console.error(e);document.querySelectorAll('[data-qr]').forEach(el=>{el.textContent='QR indisponível';el.classList.add('qr-error');});}})();</script></body></html>

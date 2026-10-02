<?php
declare(strict_types=1);
require_once __DIR__.'/../config/app.php';
require_once APP_ROOT.'/core/auth.php';
header('Content-Type: application/json; charset=utf-8');
$user=require_role('teacher','admin');

if(!request_is_post()){
    http_response_code(405);echo json_encode(['ok'=>false,'message'=>'Método inválido.']);exit;
}
verify_csrf();
$pdo=db();
$examId=(int)($_POST['exam_id']??0);
$studentId=(int)($_POST['student_id']??0);
$token=trim((string)($_POST['token']??''));
$overwrite=(string)($_POST['overwrite']??'')==='1';
$answersRaw=(string)($_POST['answers']??'[]');
$confidence=max(0,min(100,(float)($_POST['confidence']??0)));
$answers=json_decode($answersRaw,true);
if(!is_array($answers)){$answers=[];}

if($user['role']==='admin'){
    $stmt=$pdo->prepare("SELECT e.*,c.name class_name FROM exams e JOIN classes c ON c.id=e.class_id WHERE e.id=?");
    $stmt->execute([$examId]);
}else{
    $stmt=$pdo->prepare("SELECT e.*,c.name class_name FROM exams e JOIN classes c ON c.id=e.class_id WHERE e.id=? AND e.teacher_id=?");
    $stmt->execute([$examId,$user['id']]);
}
$exam=$stmt->fetch();
if(!$exam){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Prova inválida ou sem permissão.']);exit;}

$stmt=$pdo->prepare("SELECT st.id,st.name,st.enrollment FROM students st JOIN class_students cs ON cs.student_id=st.id WHERE st.id=? AND cs.class_id=? AND st.active=1");
$stmt->execute([$studentId,$exam['class_id']]);
$student=$stmt->fetch();
if(!$student){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'O aluno não pertence a esta turma.']);exit;}

if($token!==''){
    $stmt=$pdo->prepare('SELECT token FROM exam_student_tokens WHERE exam_id=? AND student_id=? LIMIT 1');
    $stmt->execute([$examId,$studentId]);
    $storedToken=(string)($stmt->fetchColumn()?:'');
    $validToken=false;
    if($storedToken!=='' && strlen($token)>=8 && strlen($token)<=strlen($storedToken)){
        $validToken=hash_equals(substr($storedToken,0,strlen($token)),$token);
    }
    if(!$validToken){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'O código do gabarito não corresponde a este aluno/prova.']);exit;}
}

$stmt=$pdo->prepare('SELECT question_number,correct_option,points FROM exam_questions WHERE exam_id=? ORDER BY question_number');
$stmt->execute([$examId]);
$official=$stmt->fetchAll();
if(count($official)!==(int)$exam['questions_count']){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'O gabarito oficial desta prova está incompleto.']);exit;}

$byNumber=[];
foreach($answers as $row){
    $n=(int)($row['question']??0);
    if($n<1||$n>(int)$exam['questions_count']){continue;}
    $opt=strtoupper((string)($row['answer']??''));
    $conf=max(0,min(100,(float)($row['confidence']??0)));
    $byNumber[$n]=['answer'=>in_array($opt,['A','B','C','D','E'],true)?$opt:null,'confidence'=>$conf];
}

$correct=0;$wrong=0;$blank=0;$score=0.0;$rows=[];
foreach($official as $q){
    $n=(int)$q['question_number'];
    $selected=$byNumber[$n]['answer']??null;
    $isCorrect=$selected!==null&&$selected===$q['correct_option'];
    if($selected===null){$blank++;}
    elseif($isCorrect){$correct++;$score+=(float)$q['points'];}
    else{$wrong++;}
    $rows[]=['n'=>$n,'selected'=>$selected,'correct'=>$q['correct_option'],'is_correct'=>$isCorrect?1:0,'confidence'=>$byNumber[$n]['confidence']??0];
}
$score=round(min((float)$exam['total_points'],$score),2);

try{
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT id,correct_count,incorrect_count,blank_count,score,scanner_confidence,corrected_by,scanned_at FROM results WHERE exam_id=? AND student_id=? FOR UPDATE');
    $stmt->execute([$examId,$studentId]);
    $existing=$stmt->fetch();
    $resultId=$existing?(int)$existing['id']:0;

    if($existing&&!$overwrite){
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'ok'=>false,
            'requires_overwrite'=>true,
            'message'=>'Este aluno já possui uma correção salva para esta prova.',
            'current_score'=>(float)$existing['score'],
        ],JSON_UNESCAPED_UNICODE);
        exit;
    }

    if($resultId){
        $stmt=$pdo->prepare('UPDATE results SET correct_count=?,incorrect_count=?,blank_count=?,score=?,scanner_confidence=?,corrected_by=?,scanned_at=CURRENT_TIMESTAMP WHERE id=?');
        $stmt->execute([$correct,$wrong,$blank,$score,$confidence,$user['id'],$resultId]);
        $pdo->prepare('DELETE FROM result_answers WHERE result_id=?')->execute([$resultId]);
    }else{
        $stmt=$pdo->prepare('INSERT INTO results (exam_id,student_id,correct_count,incorrect_count,blank_count,score,scanner_confidence,corrected_by) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$examId,$studentId,$correct,$wrong,$blank,$score,$confidence,$user['id']]);
        $resultId=(int)$pdo->lastInsertId();
    }

    $ins=$pdo->prepare('INSERT INTO result_answers (result_id,question_number,selected_option,correct_option,is_correct,confidence) VALUES (?,?,?,?,?,?)');
    foreach($rows as $r){$ins->execute([$resultId,$r['n'],$r['selected'],$r['correct'],$r['is_correct'],$r['confidence']]);}
    $pdo->commit();

    if($existing){
        audit_log('result.updated','result',$resultId,'Correção refeita e nota atualizada pelo usuário responsável.',[
            'exam_id'=>$examId,'student_id'=>$studentId,
            'before'=>['score'=>(float)$existing['score'],'correct'=>(int)$existing['correct_count'],'wrong'=>(int)$existing['incorrect_count'],'blank'=>(int)$existing['blank_count']],
            'after'=>['score'=>$score,'correct'=>$correct,'wrong'=>$wrong,'blank'=>$blank],
            'scanner_confidence'=>$confidence,'identification'=>$token!==''?'qr':'manual'
        ]);
    }else{
        audit_log('result.created','result',$resultId,'Gabarito corrigido e resultado salvo.',[
            'exam_id'=>$examId,'student_id'=>$studentId,'score'=>$score,'correct'=>$correct,'wrong'=>$wrong,'blank'=>$blank,
            'scanner_confidence'=>$confidence,'identification'=>$token!==''?'qr':'manual'
        ]);
    }

    echo json_encode(['ok'=>true,'updated'=>(bool)$existing,'result_id'=>$resultId,'student'=>['id'=>(int)$student['id'],'name'=>$student['name'],'enrollment'=>$student['enrollment']],'score'=>$score,'correct'=>$correct,'wrong'=>$wrong,'blank'=>$blank,'total_points'=>(float)$exam['total_points']],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('[Gabarito Online save_scan] '.$e->getMessage());
    http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Não foi possível salvar a correção.']);
}

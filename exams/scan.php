<?php
declare(strict_types=1);
require_once __DIR__.'/../config/app.php';
require_once APP_ROOT.'/core/auth.php';
$user=require_role('teacher','admin');
$pdo=db();
$examId=(int)($_GET['id']??0);
if($user['role']==='admin'){
    $stmt=$pdo->prepare("SELECT e.*,c.name class_name,s.name subject_name FROM exams e JOIN classes c ON c.id=e.class_id JOIN subjects s ON s.id=e.subject_id WHERE e.id=?");
    $stmt->execute([$examId]);
}else{
    $stmt=$pdo->prepare("SELECT e.*,c.name class_name,s.name subject_name FROM exams e JOIN classes c ON c.id=e.class_id JOIN subjects s ON s.id=e.subject_id WHERE e.id=? AND e.teacher_id=?");
    $stmt->execute([$examId,$user['id']]);
}
$exam=$stmt->fetch();
if(!$exam){http_response_code(404);exit('Prova não encontrada ou sem permissão.');}
$stmt=$pdo->prepare("SELECT st.id,st.name,st.enrollment,CASE WHEN r.id IS NULL THEN 0 ELSE 1 END corrected,r.score,r.id result_id FROM class_students cs JOIN students st ON st.id=cs.student_id LEFT JOIN results r ON r.student_id=st.id AND r.exam_id=? WHERE cs.class_id=? AND st.active=1 ORDER BY st.name");
$stmt->execute([$examId,$exam['class_id']]);$students=$stmt->fetchAll();
$prefill=(int)($_GET['student']??0);
$pageTitle='Escanear gabarito';
$pageScripts=[asset_url('assets/js/vendor-loader.js'),asset_url('assets/js/scanner.js')];
require APP_ROOT.'/partials/header.php';
?>
<div class="page-toolbar reveal">
    <div>
        <a class="back-link" href="<?= e(url('exams/view.php?id='.$examId)) ?>">← <?= e($exam['title']) ?></a>
        <span class="eyebrow">SCANNER • <?= e($exam['class_name']) ?></span>
        <h2>Corrigir pelo scanner</h2>
        <p>Abra a câmera, acompanhe a moldura geométrica em tempo real e confirme a prévia do alinhamento antes da leitura das respostas.</p>
    </div>
    <span class="scanner-status-pill ready"><i></i><span data-cv-status>Pronto para abrir o scanner</span></span>
</div>

<div class="scanner-layout" data-scanner data-exam-id="<?= $examId ?>" data-question-count="<?= (int)$exam['questions_count'] ?>" data-total-points="<?= e((string)$exam['total_points']) ?>">
<section class="panel scanner-panel reveal">
    <div class="scanner-step"><span>01</span><div><strong>Escaneie a folha</strong><p>O scanner funciona dentro do navegador. Você pode reduzir o bloco do gabarito, mas não corte nenhum dos 6 quadrados pretos.</p></div></div>

    <div class="browser-scanner-launch" data-capture-choices>
        <button class="scanner-launch-button" type="button" data-open-live-camera>
            <span class="scanner-launch-icon">▣</span>
            <span class="scanner-launch-copy">
                <strong>Abrir scanner</strong>
                <small>Usar a câmera do celular dentro do Gabarito Online</small>
            </span>
            <span class="scanner-launch-arrow">→</span>
        </button>
        <div class="scanner-launch-note"><i>✓</i><span>A imagem é processada no próprio navegador em resolução controlada.</span></div>
        <div class="scanner-browser-diagnostic" hidden data-scanner-diagnostic role="status"></div>
        <input id="scanInput" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" hidden tabindex="-1" aria-hidden="true">
    </div>

    <div class="live-camera browser-scanner" hidden data-live-camera>
        <div class="browser-scanner-topbar">
            <button class="scanner-circle-button" type="button" data-camera-cancel aria-label="Fechar scanner">×</button>
            <div class="browser-scanner-title"><strong>Gabarito Online</strong><span data-live-status>Procurando folha…</span></div>
            <button class="scanner-circle-button" type="button" data-camera-torch hidden aria-label="Lanterna">☼</button>
        </div>
        <div class="live-camera-frame" data-camera-frame>
            <video autoplay muted playsinline data-camera-video></video>
            <canvas class="scanner-geometry-overlay" data-geometry-overlay aria-hidden="true"></canvas>
            <div class="camera-guide" data-camera-guide>
                <span></span><span></span><span></span><span></span>
                <div class="marker-hint mh-tl"></div><div class="marker-hint mh-tr"></div>
                <div class="marker-hint mh-ml"></div><div class="marker-hint mh-mr"></div>
                <div class="marker-hint mh-bl"></div><div class="marker-hint mh-br"></div>
                <em data-guide-text>Enquadre a folha inteira</em>
            </div>
            <div class="scanner-live-badge" data-live-badge><i></i><span>Procurando 6 marcadores</span></div>
        </div>
        <div class="browser-scanner-metrics">
            <span data-live-light><i></i>Luz: verificando</span>
            <span data-live-markers><i></i>Marcadores: 0/6</span>
            <span data-live-stability><i></i>Enquadramento: aguardando</span>
        </div>
        <div class="live-camera-actions single-action">
            <button class="btn btn-primary btn-lg scanner-capture-button" type="button" data-camera-capture disabled>
                <span class="btn-spinner"></span><span data-camera-capture-label>Aponte para o gabarito</span>
            </button>
        </div>
        <small class="camera-memory-note">A moldura verde mostra a área que será corrigida. Quando o alinhamento estiver estável, o botão será liberado.</small>
    </div>

    <div class="scan-preview-wrap" hidden data-preview-wrap>
        <canvas id="scanCanvas"></canvas>
        <div class="scan-overlay"><div class="scan-line"></div></div>
        <div class="scan-quality" data-quality>
            <span data-quality-resolution>Resolução: —</span>
            <span data-quality-light>Luz: —</span>
            <span data-quality-markers>Marcadores: aguardando</span>
        </div>
        <button class="btn btn-soft btn-block" type="button" data-change-photo>Escanear novamente</button>
    </div>

    <div class="scan-actions" hidden data-scan-actions>
        <button class="btn btn-primary btn-lg btn-block" type="button" data-process-scan hidden><span class="btn-spinner"></span><span data-process-label>Processando…</span></button>
    </div>
</section>

<aside class="panel scanner-side reveal delay-1">
    <div class="scanner-step"><span>02</span><div><strong>Identificação do aluno</strong><p>O QR identifica automaticamente o aluno e a prova após o alinhamento do bloco do gabarito.</p></div></div>
    <div class="identity-card" data-identity-card>
        <div class="identity-placeholder"><div class="pulse-avatar"></div><div><i></i><i></i></div></div>
        <div class="identity-found" hidden><div class="avatar large" data-student-initial>?</div><div><span>Aluno identificado</span><strong data-student-name>—</strong><small>Matrícula <b data-student-enrollment>—</b></small></div><div class="verified-mark">✓</div></div>
    </div>
    <div class="alert alert-warning scan-existing-warning" hidden data-existing-warning>Este aluno já possui uma correção salva. Ao salvar novamente, a alteração ficará registrada na auditoria.</div>
    <label class="field fallback-select"><span>Se o QR não for reconhecido</span><select id="manualStudent"><option value="">Selecione manualmente o aluno</option><?php foreach($students as $s): ?><option value="<?= (int)$s['id'] ?>" data-name="<?= e($s['name']) ?>" data-enrollment="<?= e($s['enrollment']) ?>" <?= $prefill===(int)$s['id']?'selected':'' ?>><?= e($s['name'].' • '.$s['enrollment'].((int)$s['corrected']?' • já corrigido':'')) ?></option><?php endforeach; ?></select></label>
    <div class="scanner-tips"><strong>Para a leitura ficar confiável</strong><ul><li>Prefira o gabarito <b>Formulário v4.0</b>, com bloco escalável, QR maior e 6 marcadores ao redor do próprio gabarito.</li><li>Mantenha os 6 quadrados pretos totalmente visíveis.</li><li>Evite reflexos, sombras fortes e movimento na hora da captura.</li><li>Preencha completamente uma única bolha por questão.</li><li>A moldura verde mostra a geometria detectada em tempo real.</li><li>Depois da captura, confirme a prévia já corrigida antes de ler as respostas.</li><li>O sistema rejeita a correção quando o alinhamento não é seguro.</li></ul></div>
</aside>
</div>

<div class="alignment-preview-overlay" hidden data-alignment-preview>
    <div class="alignment-preview-topbar">
        <button class="scanner-circle-button" type="button" data-alignment-retake aria-label="Refazer foto">×</button>
        <div><strong>Prévia do alinhamento</strong><span data-alignment-status>Verificando geometria…</span></div>
        <span class="alignment-quality-badge" data-alignment-badge>—</span>
    </div>
    <div class="alignment-preview-stage">
        <canvas id="alignmentPreviewCanvas"></canvas>
        <div class="alignment-safe-frame"><span></span><span></span><span></span><span></span></div>
    </div>
    <div class="alignment-preview-info">
        <div><span>Geometria</span><strong data-alignment-geometry>—</strong></div>
        <div><span>Grade de respostas</span><strong data-alignment-grid>—</strong></div>
        <div><span>Perspectiva</span><strong data-alignment-perspective>—</strong></div>
        <div><span>Nitidez</span><strong data-alignment-sharpness>—</strong></div>
    </div>
    <div class="alignment-preview-message" data-alignment-message>Confira se o gabarito aparece reto, inteiro e sem cortes.</div>
    <div class="alignment-preview-actions">
        <button class="btn btn-soft btn-lg" type="button" data-alignment-retake>Refazer foto</button>
        <button class="btn btn-primary btn-lg" type="button" data-alignment-confirm>Usar esta captura e corrigir <span>→</span></button>
    </div>
</div>

<section class="panel scan-review" hidden data-review>
    <div class="panel-head wrap"><div><span class="eyebrow">REVISÃO</span><h3>Respostas detectadas</h3><p>Confira as marcações antes de salvar a correção.</p></div><div class="confidence-meter"><span>Confiança da leitura</span><strong data-confidence>—</strong></div></div>
    <div class="review-alert" hidden data-review-alert>Algumas questões não atingiram o nível de confiança necessário. Confirme manualmente antes de salvar.</div>
    <div class="detected-grid" data-detected-grid></div>
    <div class="review-footer"><div><strong data-ready-student>Aluno não identificado</strong><span>O servidor recalcula a nota usando o gabarito oficial.</span></div><button class="btn btn-primary btn-lg" type="button" data-save-scan>Salvar correção <span>→</span></button></div>
</section>

<section class="scan-result-card" hidden data-result-card>
    <div class="result-check">✓</div><span data-result-status>CORREÇÃO CONCLUÍDA</span><h3 data-result-name>—</h3><p data-result-enrollment>—</p><div class="result-score"><strong data-result-score>0,0</strong><span>pontos</span></div><div class="result-metrics"><div><strong data-result-correct>0</strong><span>acertos</span></div><div><strong data-result-wrong>0</strong><span>erros</span></div><div><strong data-result-blank>0</strong><span>em branco</span></div></div><div class="result-actions"><button class="btn btn-light" type="button" data-scan-next>Corrigir próximo</button><a class="btn btn-primary" href="<?= e(url('exams/view.php?id='.$examId)) ?>">Ver tabela da turma</a></div>
</section>
<script nonce="<?= e(csp_nonce()) ?>">window.SCAN_STUDENTS=<?= json_encode($students,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>; window.SCAN_PREFILL=<?= $prefill ?>;</script>
<?php require APP_ROOT.'/partials/footer.php'; ?>

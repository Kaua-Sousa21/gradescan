<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';
$user = require_role('admin');
$pdo = db();

$checks = [];
$checks[] = ['label'=>'PHP 8.1 ou superior','ok'=>version_compare(PHP_VERSION,'8.1.0','>='),'detail'=>PHP_VERSION];
$checks[] = ['label'=>'PDO MySQL habilitado','ok'=>extension_loaded('pdo_mysql'),'detail'=>extension_loaded('pdo_mysql')?'Disponível':'Extensão não encontrada'];
$checks[] = ['label'=>'Conexão com MySQL','ok'=>true,'detail'=>'Conectado'];
$checks[] = ['label'=>'HTTPS ativo','ok'=>gradescan_is_https(),'detail'=>gradescan_is_https()?'Conexão segura':'Acesse usando https://'];
$checks[] = ['label'=>'Modo de produção','ok'=>(bool)config('production'),'detail'=>(bool)config('production')?'Ativo':'Desativado'];
$checks[] = ['label'=>'HTTPS obrigatório','ok'=>(bool)config('force_https'),'detail'=>(bool)config('force_https')?'Ativo':'Desativado'];
$checks[] = ['label'=>'Sessão em modo estrito','ok'=>ini_get('session.use_strict_mode')==='1','detail'=>ini_get('session.use_strict_mode')==='1'?'Ativo':'Inativo'];

$requiredTables=['users','students','classes','class_students','class_teachers','exams','exam_questions','exam_student_tokens','results','result_answers','audit_logs','login_rate_limits'];
$missing=[];
foreach($requiredTables as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$stmt->execute([$table]);if(!(int)$stmt->fetchColumn()){$missing[]=$table;}
}
$checks[]=['label'=>'Estrutura do banco','ok'=>!$missing,'detail'=>$missing?'Faltando: '.implode(', ',$missing):'Todas as tabelas principais encontradas'];
$activeAdmins=active_admin_count();
$checks[]=['label'=>'Administrador ativo','ok'=>$activeAdmins>=1,'detail'=>$activeAdmins.' administrador(es) ativo(s)'];

$pageTitle='Diagnóstico';
$pageScripts=[url('assets/js/vendor-loader.js')];
require APP_ROOT . '/partials/header.php';
?>
<div class="page-toolbar reveal"><div><span class="eyebrow">PRÉ-PRODUÇÃO</span><h2>Diagnóstico do sistema</h2><p>Use esta tela depois de hospedar para confirmar os requisitos antes dos testes com gabaritos reais.</p></div></div>
<div class="dashboard-grid system-grid">
<section class="panel reveal">
    <div class="panel-head"><div><span class="eyebrow">SERVIDOR</span><h3>Configuração da hospedagem</h3></div></div>
    <div class="system-checks">
    <?php foreach($checks as $check): ?>
        <div class="system-check <?= $check['ok']?'ok':'warn' ?>"><i><?= $check['ok']?'✓':'!' ?></i><div><strong><?= e($check['label']) ?></strong><span><?= e($check['detail']) ?></span></div></div>
    <?php endforeach; ?>
    </div>
</section>
<aside class="panel reveal delay-1">
    <div class="panel-head"><div><span class="eyebrow">NAVEGADOR</span><h3>Bibliotecas do scanner</h3></div></div>
    <div class="system-checks">
        <div class="system-check pending" data-dep="jsqr"><i>…</i><div><strong>Leitura de QR</strong><span>Verificando jsQR…</span></div></div>
        <div class="system-check pending" data-dep="opencv"><i>…</i><div><strong>Visão computacional</strong><span>Verificando OpenCV.js…</span></div></div>
        <div class="system-check pending" data-dep="qrcode"><i>…</i><div><strong>Geração de QR</strong><span>Verificando QRCode.js…</span></div></div>
    </div>
    <div class="alert alert-info system-note">Essas verificações acontecem no navegador. Se uma biblioteca falhar, confirme se o celular/computador tem acesso à internet e se a rede da escola não bloqueia os CDNs.</div>
</aside>
</div>
<script nonce="<?= e(csp_nonce()) ?>">
(async()=>{
 const set=(name,ok,text)=>{const el=document.querySelector(`[data-dep="${name}"]`);if(!el)return;el.className=`system-check ${ok?'ok':'warn'}`;el.querySelector('i').textContent=ok?'✓':'!';el.querySelector('span').textContent=text;};
 try{await window.GradeScanVendors.ensureJsQR();set('jsqr',typeof window.jsQR==='function','jsQR carregado com sucesso');}catch(e){set('jsqr',false,'Não foi possível carregar jsQR');}
 try{await window.GradeScanVendors.ensureOpenCV();set('opencv',window.cv&&typeof window.cv.Mat==='function','OpenCV.js carregado com sucesso');}catch(e){set('opencv',false,'Não foi possível carregar OpenCV.js');}
 try{await window.GradeScanVendors.ensureQRCode();set('qrcode',typeof window.QRCode==='function','QRCode.js carregado com sucesso');}catch(e){set('qrcode',false,'Não foi possível carregar QRCode.js');}
})();
</script>
<?php require APP_ROOT . '/partials/footer.php'; ?>

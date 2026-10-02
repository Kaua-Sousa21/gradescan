<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once APP_ROOT . '/core/auth.php';
$user = require_role('admin');
$pdo = db();

function sql_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function sql_value(PDO $pdo, mixed $value): string
{
    if ($value === null) return 'NULL';
    return $pdo->quote((string)$value);
}

if (request_is_post()) {
    verify_csrf();
    if ((string)($_POST['action'] ?? '') !== 'download') {
        http_response_code(400);
        exit('Ação inválida.');
    }

    audit_log('backup.download', 'system', 'database', 'Backup completo do banco solicitado.');
    session_write_close();
    @set_time_limit(0);
    $filename = 'gradescan-backup-' . date('Y-m-d-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    echo "-- Gabarito Online database backup\n";
    echo '-- Gerado em ' . date('c') . "\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
    $tables = [];
    while ($row = $tablesStmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = (string)$row[0];
    }

    foreach ($tables as $table) {
        $id = sql_identifier($table);
        echo "-- ----------------------------------------\n-- Tabela {$table}\n-- ----------------------------------------\n";
        echo "DROP TABLE IF EXISTS {$id};\n";
        $create = $pdo->query("SHOW CREATE TABLE {$id}")->fetch(PDO::FETCH_NUM);
        echo ($create[1] ?? '') . ";\n\n";

        $rows = $pdo->query("SELECT * FROM {$id}");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map('sql_identifier', array_keys($row));
            $values = array_map(fn($v) => sql_value($pdo, $v), array_values($row));
            echo "INSERT INTO {$id} (" . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
        }
        echo "\n";
        @ob_flush();
        flush();
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

$counts = [
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'students' => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'classes' => (int)$pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn(),
    'exams' => (int)$pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn(),
    'results' => (int)$pdo->query('SELECT COUNT(*) FROM results')->fetchColumn(),
];
$stmt = $pdo->query("SELECT created_at FROM audit_logs WHERE action='backup.download' ORDER BY id DESC LIMIT 1");
$lastBackup = $stmt->fetchColumn();
$pageTitle = 'Backup';
require APP_ROOT . '/partials/header.php';
?>
<div class="page-toolbar reveal"><div><span class="eyebrow">SEGURANÇA DOS DADOS</span><h2>Backup do banco de dados</h2><p>Gere uma cópia SQL com cadastros, turmas, provas, respostas, notas e auditoria.</p></div></div>
<div class="dashboard-grid backup-grid">
<section class="panel reveal">
    <div class="panel-head"><div><span class="eyebrow">BACKUP COMPLETO</span><h3>Baixar cópia de segurança</h3></div><div class="backup-icon">⇩</div></div>
    <div class="backup-summary">
        <div><strong><?= $counts['students'] ?></strong><span>alunos</span></div><div><strong><?= $counts['users'] ?></strong><span>usuários</span></div><div><strong><?= $counts['classes'] ?></strong><span>turmas</span></div><div><strong><?= $counts['exams'] ?></strong><span>provas</span></div><div><strong><?= $counts['results'] ?></strong><span>resultados</span></div>
    </div>
    <div class="alert alert-info">O arquivo contém dados escolares. Guarde-o em local protegido e não envie por grupos ou serviços públicos.</div>
    <form method="post" class="backup-action"><?= csrf_field() ?><input type="hidden" name="action" value="download"><button class="btn btn-primary btn-lg" type="submit">Gerar e baixar backup <span>⇩</span></button></form>
</section>
<aside class="panel reveal delay-1">
    <div class="panel-head"><div><span class="eyebrow">ROTINA RECOMENDADA</span><h3>Antes do uso oficial</h3></div></div>
    <div class="security-checklist"><div><i>✓</i><span>Faça um backup antes de alterações grandes.</span></div><div><i>✓</i><span>Mantenha ao menos uma cópia fora da hospedagem.</span></div><div><i>✓</i><span>Use uma pasta privada com acesso restrito.</span></div><div><i>✓</i><span>Teste periodicamente se o arquivo está sendo gerado.</span></div></div>
    <div class="backup-last"><span>Último backup registrado</span><strong><?= $lastBackup ? date('d/m/Y H:i', strtotime((string)$lastBackup)) : 'Ainda não realizado' ?></strong></div>
</aside>
</div>
<?php require APP_ROOT . '/partials/footer.php'; ?>

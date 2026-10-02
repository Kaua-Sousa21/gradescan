<?php
/** @var string $pageTitle */
$user = current_user();
$pageTitle = $pageTitle ?? 'Painel';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111827">
    <title><?= e($pageTitle) ?> • <?= e((string)config('name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php require APP_ROOT . '/partials/sidebar.php'; ?>
    <div class="app-main">
        <header class="topbar">
            <button class="icon-btn menu-trigger" type="button" data-sidebar-toggle aria-label="Abrir menu">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar-heading">
                <span class="eyebrow"><?= e($user['role'] === 'admin' ? 'Administração' : 'Área do professor') ?></span>
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <div class="topbar-user">
                <div class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
                <div class="topbar-user-copy">
                    <strong><?= e($user['name']) ?></strong>
                    <span><?= e($user['email']) ?></span>
                </div>
                <form class="logout-form" method="post" action="<?= e(url('auth/logout.php')) ?>"><?= csrf_field() ?><button class="icon-btn logout-btn" type="submit" title="Sair" aria-label="Sair">↗</button></form>
            </div>
        </header>
        <main class="content-wrap">
            <div class="toast-stack"><?php require APP_ROOT . '/partials/flash.php'; ?></div>

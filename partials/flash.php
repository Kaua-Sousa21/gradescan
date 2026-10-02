<?php
$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
foreach ($flashes as $item):
?>
<div class="toast toast-<?= e($item['type']) ?>" data-toast>
    <div class="toast-icon">●</div>
    <div><?= e($item['message']) ?></div>
    <button type="button" class="toast-close" data-toast-close aria-label="Fechar">×</button>
</div>
<?php endforeach; ?>

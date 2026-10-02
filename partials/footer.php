        </main>
        <footer class="app-footer">
            <span><?= e((string)config('name')) ?> • <?= date('Y') ?></span>
            <span>Plataforma de correção por gabarito</span>
        </footer>
    </div>
</div>
<script nonce="<?= e(csp_nonce()) ?>">window.GRADESCAN = <?= json_encode(['baseUrl' => url(''), 'csrf' => csrf_token()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
<?php if (!empty($pageScripts)): foreach ($pageScripts as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>

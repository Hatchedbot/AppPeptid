</main>
<?php if (usuario_actual()): ?>
<div id="lia-raiz"></div>
<?php endif; ?>
<script src="assets/app.js?v=<?= @filemtime(RAIZ . '/assets/app.js') ?>"></script>
<?php if (usuario_actual() && puede('lia') && is_file(RAIZ . '/assets/lia.js')): ?>
<script src="assets/lia.js?v=<?= @filemtime(RAIZ . '/assets/lia.js') ?>"></script>
<?php endif; ?>
</body>
</html>

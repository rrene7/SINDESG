<?php
declare(strict_types=1);

$flashMessages = pull_flash();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#06172c">
    <title><?= e($pageTitle) ?> · SINDESG</title>
    <link rel="stylesheet" href="<?= e(sindesg_asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(sindesg_asset('css/auth.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-shell">
    <header class="auth-brand">
        <div class="brand-mark">PN</div>
        <div>
            <strong>POLICÍA NACIONAL DE PANAMÁ</strong>
            <span>Sistema de Inventario y Despacho de Servicios Generales</span>
        </div>
    </header>

    <?php if ($flashMessages !== []): ?>
        <div class="flash-stack auth-flash" role="status">
            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="flash-message <?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <main><?php require $contentView; ?></main>

    <footer class="auth-footer">
        <span>SINDESG</span>
        <span>Versión <?= e(sindesg_config('version')) ?></span>
    </footer>
</div>
<script src="<?= e(sindesg_asset('js/auth.js')) ?>"></script>
</body>
</html>

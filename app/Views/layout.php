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
    <title><?= e($pageTitle) ?> · <?= e(sindesg_config('name')) ?></title>
    <link rel="stylesheet" href="<?= e(sindesg_asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(sindesg_asset('css/inventory.css')) ?>">
</head>
<body>
<div class="app-shell">
    <header class="site-header">
        <a class="brand" href="<?= e(sindesg_url()) ?>" aria-label="Ir al dashboard">
            <div class="brand-mark">PN</div>
            <div>
                <strong>POLICÍA NACIONAL</strong>
                <span>Disciplina · Servicio · Protección</span>
            </div>
        </a>

        <div class="system-title">
            <strong>SINDESG</strong>
            <span>Sistema de Inventario y Despacho de Servicios Generales</span>
        </div>

        <div class="header-actions">
            <button class="search-trigger" type="button" data-search-open>
                <span>⌕</span>
                <span>Buscar en SINDESG</span>
            </button>
            <div class="user-chip">
                <span class="user-avatar">SG</span>
                <span><strong>Servicios Generales</strong><small>Entorno local</small></span>
            </div>
        </div>
    </header>

    <nav class="main-nav" aria-label="Módulos principales">
        <a class="<?= $currentModule === 'dashboard' ? 'active' : '' ?>" href="<?= e(sindesg_url()) ?>">Dashboard</a>
        <?php foreach (['inventory', 'receipts', 'requests', 'distribution', 'reports'] as $navKey): ?>
            <a class="<?= $currentModule === $navKey ? 'active' : '' ?>"
               href="<?= e(sindesg_url('?module=' . $navKey)) ?>">
                <?= e($modules[$navKey]['short']) ?>
            </a>
        <?php endforeach; ?>
        <button class="nav-more" type="button" data-more-toggle>Más</button>
    </nav>

    <div class="more-menu" data-more-menu hidden>
        <?php foreach (['production', 'deliveries', 'warehouses', 'people', 'audit'] as $navKey): ?>
            <a href="<?= e(sindesg_url('?module=' . $navKey)) ?>">
                <span class="mini-icon"><?= e($modules[$navKey]['icon']) ?></span>
                <span><strong><?= e($modules[$navKey]['label']) ?></strong><small><?= e($modules[$navKey]['phase']) ?></small></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($flashMessages !== []): ?>
        <div class="flash-stack" role="status" aria-live="polite">
            <?php foreach ($flashMessages as $flashMessage): ?>
                <div class="flash-message <?= e($flashMessage['type']) ?>">
                    <?= e($flashMessage['message']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <main class="main-content">
        <?php require $contentView; ?>
    </main>

    <footer class="site-footer">
        <span><?= e(sindesg_config('full_name')) ?></span>
        <span>Versión <?= e(sindesg_config('version')) ?></span>
    </footer>
</div>

<div class="search-overlay" data-search-overlay hidden>
    <div class="search-panel">
        <div class="search-panel-head">
            <div>
                <strong>Búsqueda rápida</strong>
                <span>Localiza módulos y categorías del dashboard.</span>
            </div>
            <button type="button" data-search-close aria-label="Cerrar búsqueda">×</button>
        </div>
        <input type="search" placeholder="Ej.: camisa, inventario, P4, reportes..." data-search-input autocomplete="off">
        <div class="search-results" data-search-results></div>
    </div>
</div>

<script>
window.SINDESG_SEARCH_ITEMS = <?= json_encode(array_merge(
    array_map(
        static fn (array $item, string $key): array => [
            'label' => $item['label'],
            'meta' => $item['phase'],
            'url' => sindesg_url('?module=' . $key),
        ],
        $modules,
        array_keys($modules)
    ),
    array_map(
        static fn (array $item): array => [
            'label' => $item['name'],
            'meta' => 'Categoría',
            'url' => sindesg_url('?module=inventory&category_id=' . (int) ($item['id'] ?? 0)),
        ],
        $categories ?? []
    )
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(sindesg_asset('js/app.js')) ?>"></script>
</body>
</html>

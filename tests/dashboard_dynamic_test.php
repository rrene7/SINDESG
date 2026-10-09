<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/DashboardRepository.php');
$view = file_get_contents($root . '/app/Views/dashboard.php');
$index = file_get_contents($root . '/public/index.php');
$javascript = file_get_contents($root . '/public/assets/js/app.js');

if ($repository === false || $view === false || $index === false || $javascript === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer dashboard dinámico." . PHP_EOL);
    exit(1);
}

foreach ([
    'v_inventory_stock',
    'v_inventory_product_stock',
    'product_images',
    'recentReceipts',
    'stockBreakdownForProduct',
    'categoryFocusData',
    'featuredProductForCategory',
    'recentRequests',
    'operationalSummary',
    'requests_pending_review',
    'requests_ready_distribution',
    'distributions_in_transit',
    'quantity_dispatched',
    "'CENTRAL-SG'",
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Dashboard no consume dato real: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'featuredProduct',
    'featuredSizes',
    'recentDashboardReceipts',
    'recentDashboardRequests',
    'dashboardOperationalSummary',
    'dashboardCategoryFocus',
    'data-dashboard-category-id',
    'data-dashboard-category-focus',
    'data-dashboard-focus-panel',
    'data-feature-category',
    'data-feature-available',
    'data-feature-sizes',
    'Entrar a categoría',
    'sindesg_upload_url',
    'STOCK DISPONIBLE',
    'unidades libres para nuevas entregas',
    'Comprometido',
    'institucionales',
    'Solicitudes y distribución',
    'listas para distribuir',
    'en tránsito',
    'P4 activos',
] as $fragment) {
    if (!str_contains($view, $fragment)) {
        fwrite(STDERR, "[ERROR] Vista dashboard no renderiza dato real: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (
    str_contains($view, 'STOCK ACTUAL')
    || str_contains($view, 'Stock pendiente')
    || str_contains($view, 'La Fase 7 habilitará')
) {
    fwrite(STDERR, "[ERROR] Dashboard todavía contiene contenido demostrativo o de fase futura." . PHP_EOL);
    exit(1);
}

foreach ([
    '$dashboardRepository->recentRequests(5)',
    '$dashboardRepository->categoryFocusData($dashboardCategories)',
    '$dashboardRepository->operationalSummary()',
] as $fragment) {
    if (!str_contains($index, $fragment)) {
        fwrite(STDERR, "[ERROR] public/index.php no carga dato operativo real: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "querySelectorAll('[data-dashboard-category-id]')",
    "JSON.parse(focusPayload.textContent || '{}')",
    'activateCategory',
    "candidate.classList.toggle('is-selected', active)",
    "fields.link.textContent = product ? 'Abrir artículo →' : 'Abrir categoría →'",
    'renderSizes(entry.sizes)',
] as $fragment) {
    if (!str_contains($javascript, $fragment)) {
        fwrite(STDERR, "[ERROR] Interacción de categorías incompleta: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Dashboard dinámico, operativo y selector de categorías validados." . PHP_EOL;

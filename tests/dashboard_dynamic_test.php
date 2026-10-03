<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/DashboardRepository.php');
$view = file_get_contents($root . '/app/Views/dashboard.php');

if ($repository === false || $view === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer dashboard dinámico." . PHP_EOL);
    exit(1);
}

foreach (['v_inventory_stock', 'v_inventory_product_stock', 'product_images', 'recentReceipts'] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Dashboard no consume dato real: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['featuredProduct', 'featuredSizes', 'recentDashboardReceipts', 'sindesg_upload_url'] as $fragment) {
    if (!str_contains($view, $fragment)) {
        fwrite(STDERR, "[ERROR] Vista dashboard no renderiza dato real: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($view, 'Stock pendiente')) {
    fwrite(STDERR, "[ERROR] Dashboard todavía contiene stock demostrativo." . PHP_EOL);
    exit(1);
}

echo "[OK] Dashboard dinámico validado." . PHP_EOL;

<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/ReportRepository.php');
$controller = file_get_contents($root . '/app/Controllers/ReportsController.php');
$router = file_get_contents($root . '/public/index.php');
$view = file_get_contents($root . '/app/Views/reports/index.php');

foreach (compact('repository','controller','router','view') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "'stock'",
    "'low_stock'",
    "'kardex'",
    "'entries'",
    "'exits'",
    "'receipts'",
    "'requests'",
    "'transfers'",
    "'deliveries'",
    "'employee_dotations'",
    "'adjustments'",
    'v_inventory_stock',
    'inventory_movements',
    'quantity_delivered',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Reporte o fuente ausente: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['date_from', 'warehouse_id', 'category_id', 'product_id', 'size_id', 'employee_id', 'threshold'] as $fragment) {
    if (!str_contains($controller, $fragment) || !str_contains($view, $fragment)) {
        fwrite(STDERR, "[ERROR] Filtro no integrado: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($router, 'new ReportsController')) {
    fwrite(STDERR, "[ERROR] Reportes no está conectado al router." . PHP_EOL);
    exit(1);
}

echo "[OK] Centro de reportes validado." . PHP_EOL;

<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/InventoryRepository.php');
$controller = file_get_contents($root . '/app/Controllers/InventoryController.php');
$index = file_get_contents($root . '/app/Views/inventory/index.php');
$show = file_get_contents($root . '/app/Views/inventory/product-show.php');

foreach (compact('repository','controller','index','show') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'stock_available',
    'stock_committed',
    'stock_p4',
    'stock_transit',
    'quantity_approved - i.quantity_dispatched',
    "'CENTRAL'",
    "'P4'",
    "'TRANSIT'",
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Inventario no calcula disponibilidad: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['stock_available', 'stock_committed', 'stock_p4', 'stock_transit'] as $fragment) {
    if (!str_contains($controller, $fragment)) {
        fwrite(STDERR, "[ERROR] Controller no entrega dato: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['Stock disponible', '<span>Disponible</span>', 'institucionales'] as $fragment) {
    if (!str_contains($index, $fragment)) {
        fwrite(STDERR, "[ERROR] Listado no muestra disponibilidad: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['<dt>Disponible</dt>', '<dt>Central físico</dt>', '<dt>Comprometido</dt>', '<dt>En P4</dt>', '<dt>En tránsito</dt>', '<dt>Stock institucional</dt>'] as $fragment) {
    if (!str_contains($show, $fragment)) {
        fwrite(STDERR, "[ERROR] Detalle no separa stock: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($index, '<span>Existencia</span>')) {
    fwrite(STDERR, "[ERROR] La tarjeta todavía usa Existencia como total ambiguo." . PHP_EOL);
    exit(1);
}

echo "[OK] Disponibilidad operativa del módulo Inventario validada." . PHP_EOL;

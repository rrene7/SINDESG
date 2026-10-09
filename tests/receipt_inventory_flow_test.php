<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/ReceiptRepository.php');
$controller = file_get_contents($root . '/app/Controllers/ReceiptsController.php');
$receiptView = file_get_contents($root . '/app/Views/receipts/show.php');
$productView = file_get_contents($root . '/app/Views/inventory/product-show.php');
$inventorySchema = file_get_contents($root . '/database/schema/003_inventory_core.sql');

foreach (compact('repository', 'controller', 'receiptView', 'productView', 'inventorySchema') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name} para validar Recepción → Inventario." . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'public function confirmationEvidence',
    "im.movement_kind = 'receipt'",
    "im.direction = 'IN'",
    "CONCAT('receipt:', ri.receipt_id, ':item:', ri.id)",
    'LEFT JOIN v_inventory_stock vs',
    "'all_verified'",
    'beginTransaction()',
    'FOR UPDATE',
    "sprintf('receipt:%d:item:%d'",
    'ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)',
] as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Flujo Recepción → Inventario incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    "receipt['status'] === 'confirmed'",
    'confirmationEvidence($receiptId)',
    "'receiptVerification'",
] as $fragment) {
    if (!str_contains($controller, $fragment)) {
        fwrite(STDERR, "[ERROR] ReceiptsController no expone verificación real: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'VERIFICACIÓN DE INVENTARIO',
    'Recepción → kardex → existencia',
    'movimientos verificados',
    'Stock Central actual',
    'Verificado',
    'Ver artículo →',
] as $fragment) {
    if (!str_contains($receiptView, $fragment)) {
        fwrite(STDERR, "[ERROR] Vista de recepción no muestra evidencia funcional: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach ([
    'UNIQUE KEY uq_inventory_movements_source_key',
    "WHEN im.direction = 'IN' THEN im.quantity",
    "WHEN im.direction = 'OUT' THEN -im.quantity",
    'CREATE OR REPLACE VIEW v_inventory_stock',
] as $fragment) {
    if (!str_contains($inventorySchema, $fragment)) {
        fwrite(STDERR, "[ERROR] Esquema de kardex no garantiza stock derivado: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($productView, 'de las próximas fases')) {
    fwrite(STDERR, "[ERROR] El kardex todavía comunica funcionalidad futura obsoleta." . PHP_EOL);
    exit(1);
}

echo "[OK] Flujo Recepción → Inventario y evidencia visual validados." . PHP_EOL;

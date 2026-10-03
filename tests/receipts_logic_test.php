<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/ReceiptRepository.php');
$service = file_get_contents($root . '/app/Services/ReceiptService.php');
$form = file_get_contents($root . '/app/Views/receipts/form.php');
$catalog = file_get_contents($root . '/app/Repositories/CatalogRepository.php');
$router = file_get_contents($root . '/public/index.php');

foreach ([
    'repository' => $repository,
    'service' => $service,
    'form' => $form,
    'catalog' => $catalog,
    'router' => $router,
] as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

$repositoryRules = [
    "sprintf('REC-%06d'",
    "sprintf('LOT-%06d'",
    "'receipt'",
    "'IN'",
    "sprintf('receipt:%d:item:%d'",
    'ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)',
    'FOR UPDATE',
];

foreach ($repositoryRules as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta regla de recepción: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($form, 'name="receipt_code"') || str_contains($form, 'name="lot_code"')) {
    fwrite(STDERR, "[ERROR] Los códigos de recepción/lote no pueden ser editables." . PHP_EOL);
    exit(1);
}

if (!str_contains($catalog, 'public function listVariantOptions(): array')) {
    fwrite(STDERR, "[ERROR] El catálogo no expone variantes para recepción." . PHP_EOL);
    exit(1);
}

if (!str_contains($router, 'new ReceiptsController')) {
    fwrite(STDERR, "[ERROR] El módulo de recepción no está conectado al enrutador." . PHP_EOL);
    exit(1);
}

if (!str_contains($service, 'Una misma variante no puede repetirse')) {
    fwrite(STDERR, "[ERROR] No existe validación contra variantes repetidas." . PHP_EOL);
    exit(1);
}

echo "[OK] Lógica estática de recepción y trazabilidad validada." . PHP_EOL;

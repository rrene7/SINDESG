<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/CatalogRepository.php');
$service = file_get_contents($root . '/app/Services/CatalogService.php');
$productForm = file_get_contents($root . '/app/Views/inventory/product-form.php');
$receiptForm = file_get_contents($root . '/app/Views/receipts/form.php');
$controller = file_get_contents($root . '/app/Controllers/InventoryController.php');

foreach ([
    'repository' => $repository,
    'service' => $service,
    'productForm' => $productForm,
    'receiptForm' => $receiptForm,
    'controller' => $controller,
] as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

$requiredRepository = [
    'INSERT INTO product_variants',
    "sprintf('SND-V-%07d'",
    'initial_size_value',
    'initial_brand_name',
];

foreach ($requiredRepository as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta variante inicial automática: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($productForm, 'Primera variante incluida automáticamente')) {
    fwrite(STDERR, "[ERROR] El formulario no explica la variante inicial." . PHP_EOL);
    exit(1);
}

if (!str_contains($receiptForm, 'return_to=receipts')) {
    fwrite(STDERR, "[ERROR] Recepción no permite preparar el primer artículo y regresar." . PHP_EOL);
    exit(1);
}

if (!str_contains($controller, "=== 'receipts'")) {
    fwrite(STDERR, "[ERROR] El controlador no retorna a recepción después del alta." . PHP_EOL);
    exit(1);
}

echo "[OK] Alta de artículo stockable desde recepción validada." . PHP_EOL;

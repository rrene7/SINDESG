<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CatalogService;

$reflect = new ReflectionClass(CatalogService::class);
$service = $reflect->newInstanceWithoutConstructor();

$parsed = $service->parseNumericSizes(" 8 8.5;9\n9.5 10 8.5 ");
if ($parsed !== ['8', '8.5', '9', '9.5', '10']) {
    throw new RuntimeException('No se normalizaron/desduplicaron las tallas en lote.');
}

foreach (['8 8,5 9', '8 2.5', '20.5', 'ABC', '8.2', '0'] as $bad) {
    try {
        $service->parseNumericSizes($bad);
        throw new RuntimeException('Se aceptó entrada inválida: ' . $bad);
    } catch (InvalidArgumentException $exception) {
        // Correcto.
    }
}
$size40 = implode(' ', array_fill(0, 41, '8'));
try {
    $service->parseNumericSizes($size40);
    throw new RuntimeException('No se respetó el límite de 40 tallas.');
} catch (InvalidArgumentException $exception) {
    // Correcto.
}

$root = dirname(__DIR__);
$repo = (string) file_get_contents($root . '/app/Repositories/CatalogRepository.php');
$controller = (string) file_get_contents($root . '/app/Controllers/InventoryController.php');
$listing = (string) file_get_contents($root . '/app/Views/inventory/index.php');
$product = (string) file_get_contents($root . '/app/Views/inventory/product-show.php');

foreach ([
    'prepareFootwearCatalog', 'addNumericSizesBulk',
    'BOTAS TÁCTICAS', 'ZAPATOS DE CHAROL', 'ZAPATOS REGULARES',
    'CALZADO', "slug = :slug OR name = :name",
    'beginTransaction()', 'commit()', 'rollBack()',
    'SELECT id, status FROM products',
    'SELECT pv.id, pv.status, s.name AS size_name',
    'findOrCreateSize', 'SND-V-', 'WHERE TRIM(name) = :name'
] as $phrase) {
    if (!str_contains($repo, $phrase)) {
        throw new RuntimeException('Falta control de catálogo: ' . $phrase);
    }
}
foreach ([
    'prepare-footwear', 'save-sizes-bulk', 'confirm_footwear',
    'createNumericSizesBulk', 'csrf_valid',
] as $phrase) {
    if (!str_contains($controller, $phrase)) {
        throw new RuntimeException('Falta ruta o protección: ' . $phrase);
    }
}
if (str_contains($listing, 'catalog-footwear-panel')
    || str_contains($listing, 'Preparar catálogo de calzado')
    || !str_contains($listing, 'catalog-toolbar')
    || !str_contains($listing, 'category-strip')
    || !str_contains($product, 'sizes_bulk')
    || !str_contains($product, 'save-sizes-bulk')) {
    throw new RuntimeException('El catálogo debe conservar tallas seguras sin mostrar el panel redundante de calzado.');
}

// Las operaciones de alta en catálogo nunca deben crear existencias operativas.
// Se limita este contrato a los métodos nuevos para no restringir el repositorio
// de catálogo que ya contiene DML para productos y variantes.
$start = strpos($repo, 'public function prepareFootwearCatalog');
$end = strpos($repo, 'public function addProductImage', $start);
$newMethods = substr($repo, $start, $end - $start);
if (preg_match('/\b(?:inventory_movements|individual_deliveries|historical_stock_snapshots|rrhhRead)\b/i', $newMethods)) {
    throw new RuntimeException('Preparación calzado debe ser catálogo solamente.');
}
echo "[OK] Calzado institucional y tallas numéricas en lote sin kardex ni RRHH." . PHP_EOL;

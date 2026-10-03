<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repository = file_get_contents($root . '/app/Repositories/ProductionRepository.php');
$service = file_get_contents($root . '/app/Services/ProductionService.php');
$router = file_get_contents($root . '/public/index.php');
$form = file_get_contents($root . '/app/Views/production/form.php');

foreach ([
    'repository' => $repository,
    'service' => $service,
    'router' => $router,
    'form' => $form,
] as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

$repositoryRules = [
    "sprintf('OPR-%06d'",
    "sprintf('PLT-%06d'",
    "'production_input'",
    "'production_output'",
    "'OUT'",
    "'IN'",
    "sprintf('production:%d:input:%d'",
    "sprintf('production:%d:output:%d'",
    'ON DUPLICATE KEY UPDATE source_key = VALUES(source_key)',
    'FOR UPDATE',
    'Stock insuficiente para el insumo',
];

foreach ($repositoryRules as $fragment) {
    if (!str_contains($repository, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta regla de producción: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (!str_contains($service, 'Agregue al menos un producto terminado.')) {
    fwrite(STDERR, "[ERROR] No se exige producto terminado." . PHP_EOL);
    exit(1);
}

if (!str_contains($form, 'INSUMOS · OPCIONAL')) {
    fwrite(STDERR, "[ERROR] Los insumos no están presentados como opcionales." . PHP_EOL);
    exit(1);
}

if (!str_contains($router, 'new ProductionController')) {
    fwrite(STDERR, "[ERROR] Producción no está conectada al enrutador." . PHP_EOL);
    exit(1);
}

echo "[OK] Lógica estática de producción interna validada." . PHP_EOL;

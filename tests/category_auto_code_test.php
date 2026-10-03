<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents($root . '/database/schema/004_category_codes.sql');
$form = file_get_contents($root . '/app/Views/inventory/category-form.php');
$repository = file_get_contents($root . '/app/Repositories/CatalogRepository.php');
$service = file_get_contents($root . '/app/Services/CatalogService.php');

foreach ([
    'schema' => $schema,
    'form' => $form,
    'repository' => $repository,
    'service' => $service,
] as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

$requiredSchema = [
    "CONCAT('CAT-', LPAD(id, 6, '0'))",
    'sort_order = id * 10',
    "VALUES ('004_category_codes')",
];

foreach ($requiredSchema as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta regla de secuencia: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains($form, 'name="code"')) {
    fwrite(STDERR, "[ERROR] El formulario todavía permite introducir el código manualmente." . PHP_EOL);
    exit(1);
}

if (!str_contains($form, 'Código de categoría generado por SINDESG')) {
    fwrite(STDERR, "[ERROR] El formulario no informa que el código es automático." . PHP_EOL);
    exit(1);
}

$repositoryNeedle = "sprintf('CAT-%06d', " . '$categoryId' . ")";
if (!str_contains($repository, $repositoryNeedle)) {
    fwrite(STDERR, "[ERROR] El repositorio no genera el código desde el ID permanente." . PHP_EOL);
    exit(1);
}

$manualCodeNeedle = '$input' . "['code']";
if (str_contains($service, $manualCodeNeedle)) {
    fwrite(STDERR, "[ERROR] El servicio todavía acepta código manual desde el usuario." . PHP_EOL);
    exit(1);
}

echo "[OK] Códigos automáticos de categoría validados." . PHP_EOL;

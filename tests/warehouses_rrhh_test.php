<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$rrhh = file_get_contents($root . '/app/Repositories/RrhhRepository.php');
$api = file_get_contents($root . '/public/api/units.php');
$form = file_get_contents($root . '/app/Views/warehouses/form.php');

foreach (compact('rrhh', 'api', 'form') as $name => $content) {
    if ($content === false) {
        fwrite(STDERR, "[ERROR] No se pudo leer {$name}." . PHP_EOL);
        exit(1);
    }
}

foreach (['searchUnits', 'unitsByIds', 'organization_units_current', 'rrhhRead'] as $fragment) {
    if (!str_contains($rrhh, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta consulta RRHH para P4: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

foreach (['organization_unit_id', 'responsible_employee_id', 'data-unit-picker', 'data-p4-employee-picker'] as $fragment) {
    if (!str_contains($form, $fragment)) {
        fwrite(STDERR, "[ERROR] Formulario P4 incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (stripos($rrhh, 'INSERT INTO organization_units') !== false
    || stripos($rrhh, 'UPDATE organization_units') !== false
    || stripos($rrhh, 'DELETE FROM organization_units') !== false) {
    fwrite(STDERR, "[ERROR] La integración P4 intenta escribir en RRHH." . PHP_EOL);
    exit(1);
}

$controller = file_get_contents($root . '/app/Controllers/WarehousesController.php');
$service = file_get_contents($root . '/app/Services/WarehouseService.php');
$picker = file_get_contents($root . '/public/assets/js/warehouses.js');

if ($controller === false || $service === false || $picker === false) {
    fwrite(STDERR, "[ERROR] No se pudieron leer dependencias del selector P4." . PHP_EOL);
    exit(1);
}

foreach ([
    [$controller, 'public/api/units.php?scope=p4'],
    [$api, "['scope']"],
    [$api, 'searchUnits($query, 30, $p4Only)'],
    [$rrhh, 'AND ou.unit_type IN ('],
    [$service, 'RrhhRepository::P4_ELIGIBLE_UNIT_TYPES'],
    [$picker, "searchUrl.searchParams.set('q', query)"],
] as [$content, $expected]) {
    if (!str_contains($content, $expected)) {
        fwrite(STDERR, "[ERROR] Falta filtro de seguridad P4: {$expected}" . PHP_EOL);
        exit(1);
    }
}

require_once $root . '/app/Repositories/RrhhRepository.php';
$types = \\App\\Repositories\\RrhhRepository::P4_ELIGIBLE_UNIT_TYPES;
foreach (['direccion_nacional', 'direccion_general', 'direccion', 'zona_policial'] as $type) {
    if (!in_array($type, $types, true)) {
        fwrite(STDERR, "[ERROR] Falta tipo P4 autorizado: {$type}" . PHP_EOL);
        exit(1);
    }
}
foreach (['dependencia', 'zona', 'sector_policial'] as $type) {
    if (in_array($type, $types, true)) {
        fwrite(STDERR, "[ERROR] Tipo duplicado/no elegible permitido en P4: {$type}" . PHP_EOL);
        exit(1);
    }
}

echo "[OK] Integración RRHH de Almacenes/P4 validada." . PHP_EOL;

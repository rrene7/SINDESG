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

echo "[OK] Integración RRHH de Almacenes/P4 validada." . PHP_EOL;
